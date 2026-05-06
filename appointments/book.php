<?php
require_once('../includes/auth.php');
require_roles(['Patient']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$msg = ""; $error = "";
$patient_id = $_SESSION['person_id'];

echo "<!-- DEBUG POST: " . print_r($_POST, true) . " -->";
echo "<!-- DEBUG GET: " . print_r($_GET, true) . " -->";

// Get selected doctor and date from POST (form submission) or GET (calendar links)
$selected_doctor = $_POST['doctor_id'] ?? $_GET['doctor_id'] ?? 0;
$selected_date = $_POST['appointment_date'] ?? $_GET['appointment_date'] ?? '';

// If doctor changes, reset date
if(isset($_POST['doctor_id_changed']) || isset($_GET['doctor_id_changed'])) {
    $selected_date = '';
}

// Generate ALL 24 hourly slots (01:00 to 00:00 next day)
function generate_all_slots() {
    $slots = [];
    for ($hour = 1; $hour <= 24; $hour++) {
        if ($hour == 24) {
            $time = "00:00";
            $display = "12:00 AM (Midnight)";
        } elseif ($hour == 12) {
            $time = "12:00";
            $display = "12:00 PM";
        } elseif ($hour < 12) {
            $time = sprintf("%02d:00", $hour);
            $display = sprintf("%02d:00 AM", $hour);
        } else {
            $time = sprintf("%02d:00", $hour);
            $display = sprintf("%02d:00 PM", $hour - 12);
        }
        $slots[$time] = $display;
    }
    return $slots;
}

// Get doctor's available hours from DB (if set)
$doctor_hours = [];
if ($selected_doctor > 0) {
    $doc_info = $conn->query("SELECT available_hours FROM PERSON WHERE person_id = $selected_doctor")->fetch_assoc();
    if ($doc_info && !empty($doc_info['available_hours'])) {
        $hours = explode(',', $doc_info['available_hours']);
        foreach ($hours as $h) {
            $h = trim($h);
            // Normalize to HH:MM (remove :00 seconds if present)
            $h = substr($h, 0, 5);
            if (preg_match('/^\d{2}:\d{2}$/', $h)) {
                $doctor_hours[] = $h;
            }
        }
    }
    // If available_hours is empty, $doctor_hours stays empty = show ALL slots
}

// Get booked slots for selected doctor+date
$booked_slots = [];
if ($selected_doctor > 0 && !empty($selected_date)) {
    $booked = $conn->query("SELECT appointment_time FROM APPOINTMENT 
        WHERE person_id_doctor = $selected_doctor 
        AND appointment_date = '$selected_date' 
        AND status != 'Cancelled'");
    if ($booked) {
        while ($slot = $booked->fetch_assoc()) {
            // Normalize time to HH:MM (handle both HH:MM and HH:MM:SS)
            $normalized_time = substr($slot['appointment_time'], 0, 5);
            $booked_slots[] = $normalized_time;
        }
    }
}

// Handle booking - FIXED: Correct column order matching schema
if (isset($_POST['book'])) {
    $doctor_id = intval($_POST['doctor_id']);
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['selected_time_slot'] ?? $_POST['time_slot_final'] ?? '';
    $problem = $_POST['patient_problem'] ?? '';
    
    if (empty($problem)) {
        $error = "Please describe your health problem!";
    } elseif (empty($doctor_id) || $doctor_id === 0) {
        $error = "Please select a doctor!";
    } elseif (empty($date)) {
        $error = "Please select a date!";
    } elseif (empty($time)) {
        $error = "Please select a time slot!";
    } else {
        // Check if slot is already booked
        $check = $conn->query("SELECT * FROM APPOINTMENT 
            WHERE person_id_doctor = $doctor_id 
            AND appointment_date = '$date' 
            AND status != 'Cancelled'");
        
        $slot_booked = false;
        if ($check) {
            while ($row = $check->fetch_assoc()) {
                $normalized_time = substr($row['appointment_time'], 0, 5);
                if ($normalized_time == $time) {
                    $slot_booked = true;
                    break;
                }
            }
        }
        
        if ($slot_booked) {
            $error = "This slot is already booked! Please choose another time.";
        } else {
            $apt_id = rand(10000, 99999);
            $problem_escaped = $conn->real_escape_string($problem);
            
            // CORRECT COLUMN ORDER from schema: appointment_id, appointment_date, appointment_time, status, is_emergency, patient_problem, person_id_patient, person_id_doctor
            $sql = "INSERT INTO APPOINTMENT (appointment_id, appointment_date, appointment_time, status, is_emergency, patient_problem, person_id_patient, person_id_doctor) 
                  VALUES ($apt_id, '$date', '$time', 'Pending', FALSE, '$problem_escaped', $patient_id, $doctor_id)";
            
            if ($conn->query($sql)) {
                $msg = "Appointment booked successfully!";
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

// Generate time slot dropdown options (PURE PHP)
function generate_time_options($all_slots, $doctor_hours, $booked_slots, $selected_time = '') {
    $options = '<option value="">-- Select Time Slot --</option>';
    
    foreach ($all_slots as $time => $display) {
        // If doctor has available_hours set, ONLY show those
        if (!empty($doctor_hours) && !in_array($time, $doctor_hours)) {
            continue; // Skip this slot - not in doctor's available hours
        }
        
        $is_booked = in_array($time, $booked_slots);
        $disabled = $is_booked ? 'disabled' : '';
        $suffix = $is_booked ? ' (Booked)' : '';
        $selected_attr = ($time == $selected_time) ? 'selected' : '';
        
        $options .= "<option value=\"$time\" $disabled $selected_attr>$display$suffix</option>";
    }
    
    return $options;
}

$all_slots = generate_all_slots();
$time_options = generate_time_options($all_slots, $doctor_hours, $booked_slots, $_POST['selected_time_slot'] ?? $_POST['time_slot_final'] ?? $_GET['selected_time_slot'] ?? $_GET['time_slot_final'] ?? '');

$doctors = $conn->query("SELECT * FROM PERSON WHERE person_type = 'Doctor' ORDER BY specialization");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - MediTrack BD</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .nav-link { color: white; padding: 12px 20px; border-radius: 10px; margin: 5px 0; display: block; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); text-decoration: none; color: white; }
        .time-slot-select { max-height: 300px; overflow-y: auto; }
        .time-slot-select option:disabled { color: #999; background: #f8f9fa; }

        /* Calendar Styles */
        .calendar-container { max-width: 280px; margin: 0 auto; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .calendar-header h6 { margin: 0; font-weight: bold; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; text-align: center; }
        .calendar-day { padding: 6px; border-radius: 5px; font-size: 12px; cursor: pointer; }
        .calendar-day.header { font-weight: bold; color: #666; cursor: default; }
        .calendar-day.available { background: #d4edda; color: #155724; }
        .calendar-day.available:hover { background: #c3e6cb; }
        .calendar-day.unavailable { background: #f8f9fa; color: #aaa; cursor: not-allowed; }
        .calendar-day.selected { background: #667eea; color: white; }
        .calendar-day.today { border: 2px solid #dc3545; }
        .calendar-day.disabled { opacity: 0.4; pointer-events: none; }

        /* Time Slot Styles */
        .time-slots-container { margin-top: 15px; }
        .time-slot-btn { padding: 8px 12px; margin: 3px; border-radius: 5px; font-size: 13px; }
        .time-slot-btn.available { background: #d4edda; border: 1px solid #28a745; color: #155724; }
        .time-slot-btn.available:hover { background: #c3e6cb; }
        .time-slot-btn.booked { background: #f8d7da; border: 1px solid #dc3545; color: #721c24; cursor: not-allowed; opacity: 0.6; }
        .time-slot-btn.selected { background: #667eea; color: white; border-color: #667eea; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once('../includes/sidebar.php'); ?>
            <div class="col-md-10 p-4">
                <h2><i class="bi bi-calendar-plus"></i> Book Appointment</h2>
                <p class="text-muted">Select your preferred doctor and describe your health problem.</p>
                
                <?php if($msg): ?>
                <div class="alert alert-success"><?php echo $msg; ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" class="card p-4">
                    <div class="mb-3">
                            <label><strong>Select Doctor</strong></label>
                            <select name="doctor_id" class="form-select form-select-lg" required>
                                <option value="">-- Select Doctor --</option>
                                <?php while($doc = $doctors->fetch_assoc()): ?>
                                <option value="<?php echo $doc['person_id']; ?>" 
                                    <?php echo ($selected_doctor == $doc['person_id']) ? 'selected' : ''; ?>>
                                    <?php echo $doc['name']; ?> - <?php echo $doc['specialization']; ?>
                                    (Fee: ৳<?php echo $doc['consultation_fee']; ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        <small class="text-muted">Select doctor, then click "Update Slots" to see available times.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label><strong>Describe Your Health Problem</strong></label>
                        <textarea name="patient_problem" class="form-control form-control-lg" rows="4" placeholder="Describe your health problem. Example: Headache for 2 days, fever, stomach pain, etc." required><?php echo htmlspecialchars($_POST['patient_problem'] ?? $_GET['patient_problem'] ?? ''); ?></textarea>
                    </div>
                    
<div class="row">
                        <div class="col-md-4 mb-3">
                            <label><strong>Preferred Date</strong></label>
                            <?php if($selected_doctor > 0):
                                $doc_info = $conn->query("SELECT available_days FROM PERSON WHERE person_id = $selected_doctor")->fetch_assoc();
                                $available_days = $doc_info['available_days'] ?? '';
                            ?>
                            <div class="calendar-container card p-3">
                                <div class="calendar-header">
                                    <a href="?doctor_id=<?php echo $selected_doctor; ?>&appointment_date=<?php echo date('Y-m-d', strtotime($selected_date . ' -1 month')); ?>&patient_problem=<?php echo urlencode($_POST['patient_problem'] ?? $_GET['patient_problem'] ?? ''); ?>&selected_time_slot=<?php echo $_POST['selected_time_slot'] ?? $_POST['time_slot_final'] ?? $_GET['selected_time_slot'] ?? $_GET['time_slot_final'] ?? ''; ?>" class="btn btn-sm btn-outline-secondary">&lt;</a>
                                    <h6><?php echo date('F Y', strtotime($selected_date ?: 'now')); ?></h6>
                                    <a href="?doctor_id=<?php echo $selected_doctor; ?>&appointment_date=<?php echo date('Y-m-d', strtotime($selected_date . ' +1 month')); ?>&patient_problem=<?php echo urlencode($_POST['patient_problem'] ?? $_GET['patient_problem'] ?? ''); ?>&selected_time_slot=<?php echo $_POST['selected_time_slot'] ?? $_POST['time_slot_final'] ?? $_GET['selected_time_slot'] ?? $_GET['time_slot_final'] ?? ''; ?>" class="btn btn-sm btn-outline-secondary">&gt;</a>
                                </div>
                                <div class="calendar-grid">
                                    <div class="calendar-day header">Su</div>
                                    <div class="calendar-day header">Mo</div>
                                    <div class="calendar-day header">Tu</div>
                                    <div class="calendar-day header">We</div>
                                    <div class="calendar-day header">Th</div>
                                    <div class="calendar-day header">Fr</div>
                                    <div class="calendar-day header">Sa</div>
                                    <?php
                                    $current_month = date('Y-m-01', strtotime($selected_date ?: 'now'));
                                    $days_in_month = date('t', strtotime($current_month));
                                    $first_day = date('w', strtotime($current_month));
                                    $today = date('Y-m-d');

                                    // Parse available days
                                    $avail_days_map = ['Sun'=>0, 'Mon'=>1, 'Tue'=>2, 'Wed'=>3, 'Thu'=>4, 'Fri'=>5, 'Sat'=>6];
                                    $allowed_days = [];
                                    if($available_days) {
                                        $days = explode('-', $available_days);
                                        foreach($days as $d) {
                                            $d = trim($d);
                                            if(isset($avail_days_map[$d])) $allowed_days[] = $avail_days_map[$d];
                                        }
                                    }

                                    // Empty cells before first day
                                    for($i=0; $i<$first_day; $i++): ?>
                                        <div class="calendar-day"></div>
                                    <?php endfor; ?>

                                    <?php for($day=1; $day<=$days_in_month; $day++):
                                        $date_str = date('Y-m-d', strtotime($current_month . ' +' . ($day-1) . ' days'));
                                        $day_of_week = date('w', strtotime($date_str));
                                        $is_past = $date_str < $today;
                                        $is_available = empty($allowed_days) || in_array($day_of_week, $allowed_days);
                                        $is_selected = $selected_date == $date_str;
                                        $is_today = $date_str == $today;
                                    ?>
                                        <?php if($is_past || !$is_available): ?>
                                        <div class="calendar-day unavailable <?php echo $is_today ? 'today' : ''; ?>" title="<?php echo $is_past ? 'Past date' : 'Not available'; ?>"><?php echo $day; ?></div>
                                        <?php else: ?>
                                        <a href="?doctor_id=<?php echo $selected_doctor; ?>&appointment_date=<?php echo $date_str; ?>&patient_problem=<?php echo urlencode($_POST['patient_problem'] ?? $_GET['patient_problem'] ?? ''); ?>&selected_time_slot=<?php echo $_POST['selected_time_slot'] ?? $_POST['time_slot_final'] ?? $_GET['selected_time_slot'] ?? $_GET['time_slot_final'] ?? ''; ?>" class="calendar-day available <?php echo $is_selected ? 'selected' : ''; ?> <?php echo $is_today ? 'today' : ''; ?>"><?php echo $day; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <input type="date" name="appointment_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo $selected_date; ?>">
                            <small class="text-muted">Select a doctor to see calendar</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label><strong>Selected Date</strong></label>
                            <input type="text" class="form-control" value="<?php echo $selected_date ? date('d M Y', strtotime($selected_date)) : 'Not selected'; ?>" readonly>
                            <input type="hidden" name="appointment_date" value="<?php echo $selected_date; ?>">
                            <?php if($selected_doctor > 0):
                                $doc_info = $conn->query("SELECT available_days FROM PERSON WHERE person_id = $selected_doctor")->fetch_assoc();
                                if($doc_info['available_days']): ?>
                            <small class="text-success"><i class="bi bi-check-circle"></i> Available: <?php echo $doc_info['available_days']; ?></small>
                            <?php endif; endif; ?>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label><strong>Preferred Time</strong></label>
                            <?php if($selected_doctor > 0 && $selected_date): ?>
                            <div class="time-slots-container">
                                <p class="text-muted small mb-2">
                                    <?php if(!empty($doctor_hours)): ?>
                                    <i class="bi bi-clock"></i> Doctor's available hours shown
                                    <?php else: ?>
                                    <i class="bi bi-info-circle"></i> All slots available
                                    <?php endif; ?>
                                </p>
                                <div class="d-flex flex-wrap gap-1">
                                <?php
                                $all_hours = ['01:00','02:00','03:00','04:00','05:00','06:00','07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00','00:00'];
                                $display_hours = !empty($doctor_hours) ? $doctor_hours : $all_hours;

                                foreach($display_hours as $time):
                                    $is_booked = in_array($time, $booked_slots);
                                    $time_12h = date('h:00 A', strtotime($time));
                                    $selected_time = $_GET['selected_time_slot'] ?? $_GET['time_slot_final'] ?? '';
                                ?>
                                    <?php if($is_booked): ?>
                                    <button type="button" class="btn btn-sm time-slot-btn booked" disabled title="Already booked"><?php echo $time_12h; ?></button>
                                    <?php else: ?>
                                    <button type="button" onclick="selectTimeSlot('<?php echo $time; ?>', this)" class="btn btn-sm time-slot-btn available <?php echo $selected_time == $time ? 'selected' : ''; ?>" data-time="<?php echo $time; ?>"><?php echo $time_12h; ?></button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="selected_time_slot" id="selectedTime" value="<?php echo $_POST['selected_time_slot'] ?? $_POST['time_slot_final'] ?? ''; ?>">
                                <input type="hidden" name="time_slot_final" id="timeSlotFinal" value="<?php echo $_POST['selected_time_slot'] ?? $_POST['time_slot_final'] ?? ''; ?>">
                            </div>
                            <?php else: ?>
                            <select name="time_slot_final" class="form-select time-slot-select" required>
                                <?php echo $time_options; ?>
                            </select>
                            <small class="text-muted">
                                Select doctor and date to see time slots
                            </small>
<?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" name="book" class="btn btn-primary btn-lg w-100"><i class="bi bi-check-lg"></i> Book Appointment</button>
                </form>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5><i class="bi bi-info-circle"></i> Tips</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>When booking:</strong></p>
                        <ul>
                            <li>Select doctor, date, and time slot</li>
                            <li>Describe your problem clearly so the doctor can prepare</li>
                            <li>Choose your preferred time slot (hourly only, 1:00 AM - 12:00 AM)</li>
                            <li>Slots with "(Booked)" are unavailable for that doctor+date</li>
                            <li>If doctor has no available hours set, all 24 slots are shown as available</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    function selectTimeSlot(time, btn) {
        var timeInput = document.getElementById('selectedTime');
        var finalInput = document.getElementById('timeSlotFinal');
        
        // Store as STRING
        timeInput.value = String(time);
        finalInput.value = String(time);
        
        // Update button states
        document.querySelectorAll('.time-slot-btn.available').forEach(function(b) {
            b.classList.remove('selected');
        });
        btn.classList.add('selected');
    }

    // Handle doctor change - reload with reset date
    document.querySelector('select[name="doctor_id"]').addEventListener('change', function() {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'doctor_id_changed';
        input.value = '1';
        this.form.appendChild(input);
        this.form.submit();
    });

    // Ensure form has all values before submit - prevent default to debug
    document.querySelector('form').addEventListener('submit', function(e) {
        var timeInput = document.getElementById('selectedTime');
        var finalInput = document.getElementById('timeSlotFinal');
        var dropdown = document.querySelector('select[name="time_slot_final"]');
        
        // Debug: log values
        console.log('Before submit - timeInput:', timeInput.value);
        console.log('Before submit - finalInput:', finalInput.value);
        console.log('Before submit - dropdown:', dropdown ? dropdown.value : 'none');
        
        // Ensure time value is stored as string
        var selectedTime = '';
        
        // Priority: hidden input > dropdown > selected button
        if (timeInput.value) {
            selectedTime = String(timeInput.value);
        } else if (dropdown && dropdown.value) {
            selectedTime = String(dropdown.value);
        } else {
            var selectedBtn = document.querySelector('.time-slot-btn.selected');
            if (selectedBtn) {
                selectedTime = String(selectedBtn.getAttribute('data-time'));
            }
        }
        
        // Set both inputs
        timeInput.value = selectedTime;
        finalInput.value = selectedTime;
        
        console.log('Submitting with time:', selectedTime);
        
        // Don't prevent default - let form submit
    });
    </script>
</body>
</html>
