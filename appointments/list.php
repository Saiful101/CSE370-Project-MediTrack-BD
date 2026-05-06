<?php
require_once('../includes/auth.php');
// Allow Patient, Doctor, and Admin to view appointments
require_roles(['Patient', 'Doctor', 'Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$user_id = $_SESSION['person_id'] ?? 0;
$user_type = $_SESSION['user_type'] ?? '';

// Handle Confirm/Reject
if(isset($_GET['confirm'])) {
    $apt_id = $_GET['confirm'];
    $conn->query("UPDATE APPOINTMENT SET status = 'Confirmed' WHERE appointment_id = $apt_id");
    header("Location: list.php"); 
    exit;
}
if(isset($_GET['reject'])) {
    $apt_id = $_GET['reject'];
    $conn->query("UPDATE APPOINTMENT SET status = 'Cancelled' WHERE appointment_id = $apt_id");
    header("Location: list.php"); 
    exit;
}

// Handle Cancel Appointment (Patient)
if(isset($_GET['cancel'])) {
    $apt_id = $_GET['cancel'];
    $conn->query("UPDATE APPOINTMENT SET status = 'Cancelled' WHERE appointment_id = $apt_id AND person_id_patient = $user_id");
    header("Location: list.php"); 
    exit;
}

// Handle Reschedule - Show next available slot
$reschedule_msg = "";
if(isset($_GET['suggest'])) {
    $apt_id = intval($_GET['suggest']);
    $apt = $conn->query("SELECT * FROM APPOINTMENT WHERE appointment_id = $apt_id")->fetch_assoc();
    
    if($apt) {
        $doc_id = $apt['person_id_doctor'];
        $current_date = $apt['appointment_date'];
        
        // Try to find available slot on same date first
        $taken = $conn->query("SELECT appointment_time FROM APPOINTMENT WHERE person_id_doctor = $doc_id AND appointment_date = '$current_date' AND status != 'Cancelled' AND appointment_id != $apt_id");
        $busy_slots = [];
        while($t = $taken->fetch_assoc()) {
            $normalized = substr($t['appointment_time'], 0, 5);
            $busy_slots[] = $normalized;
        }
        
        // Use hourly slots like in book.php
        $all_slots = ['09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'];
        $suggest = null;
        
        foreach($all_slots as $slot) {
            if(!in_array($slot, $busy_slots)) {
                $suggest = $slot;
                break;
            }
        }
        
        // If no slot on same date, try next 3 days
        if(!$suggest) {
            for($day = 1; $day <= 3; $day++) {
                $next_date = date('Y-m-d', strtotime($current_date . ' +' . $day . ' days'));
                $taken2 = $conn->query("SELECT appointment_time FROM APPOINTMENT WHERE person_id_doctor = $doc_id AND appointment_date = '$next_date' AND status != 'Cancelled'");
                $busy_slots2 = [];
                while($t2 = $taken2->fetch_assoc()) {
                    $busy_slots2[] = substr($t2['appointment_time'], 0, 5);
                }
                
                foreach($all_slots as $slot) {
                    if(!in_array($slot, $busy_slots2)) {
                        $suggest = $slot;
                        $new_date = $next_date;
                        break;
                    }
                }
                if($suggest) break;
            }
        }
        
        if($suggest) {
            $new_date = $new_date ?? $current_date;
            $conn->query("UPDATE APPOINTMENT SET appointment_date = '$new_date', appointment_time = '$suggest' WHERE appointment_id = $apt_id");
            header("Location: list.php?rescheduled=1"); 
            exit;
        } else {
            $reschedule_msg = "No available slots found in the next 3 days. Please book a new appointment.";
        }
    }
}

if($user_type == 'Doctor') {
    // Doctor sees their appointments
    $appointments = $conn->query("SELECT a.*, p.name as patient_name, p.blood_group, p.emergency_contact
        FROM APPOINTMENT a 
        JOIN PERSON p ON a.person_id_patient = p.person_id 
        WHERE a.person_id_doctor = $user_id 
        ORDER BY a.appointment_date ASC, a.appointment_time ASC");
} elseif($user_type == 'Patient') {
    // Patient sees their own appointments
    $appointments = $conn->query("SELECT a.*, d.name as doctor_name, d.specialization, d.consultation_fee,
        p.name as patient_name, p.blood_group
        FROM APPOINTMENT a 
        JOIN PERSON d ON a.person_id_doctor = d.person_id 
        JOIN PERSON p ON a.person_id_patient = p.person_id 
        WHERE a.person_id_patient = $user_id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC");
} else {
    // Admin sees ALL appointments
    $appointments = $conn->query("SELECT a.*, d.name as doctor_name, d.specialization, d.consultation_fee,
        p.name as patient_name, p.blood_group
        FROM APPOINTMENT a 
        JOIN PERSON d ON a.person_id_doctor = d.person_id 
        JOIN PERSON p ON a.person_id_patient = p.person_id 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - MediTrack BD</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .nav-link { color: white; padding: 12px 20px; border-radius: 10px; margin: 5px 0; display: block; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); text-decoration: none; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once('../includes/sidebar.php'); ?>
            <div class="col-md-10 p-4">
                <?php if(isset($_GET['rescheduled'])): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> Appointment rescheduled successfully!</div>
                <?php endif; ?>
                
                <?php if(!empty($reschedule_msg)): ?>
                <div class="alert alert-warning"><?php echo $reschedule_msg; ?></div>
                <?php endif; ?>
                
                <?php if($user_type == 'Doctor'): ?>
                <h2><i class="bi bi-calendar-check"></i> My Patient Appointments</h2>
                
                <?php if($appointments->num_rows == 0): ?>
                <div class="alert alert-info">No appointments yet.</div>
                <?php else: ?>
                <table class="table table-hover bg-white rounded">
                    <thead class="table-dark">
                        <tr><th>Patient</th><th>Health Problem</th><th>Blood</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php while($apt = $appointments->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $apt['patient_name']; ?></strong><br><small><?php echo $apt['emergency_contact'] ?? ''; ?></small></td>
                            <td><small><?php echo $apt['patient_problem'] ?? 'N/A'; ?></small></td>
                            <td><span class="badge bg-danger"><?php echo $apt['blood_group'] ?? 'N/A'; ?></span></td>
                            <td><?php echo $apt['appointment_date']; ?></td>
                            <td><?php echo $apt['appointment_time']; ?></td>
                            <td><span class="badge bg-<?php echo $apt['status']=='Confirmed'?'success':($apt['status']=='Cancelled'?'danger':'warning'); ?>"><?php echo $apt['status']; ?></span></td>
                            <td>
                        <?php if($apt['status'] == 'Pending'): ?>
                        <a href="list.php?confirm=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-success">Confirm</a>
                        <a href="list.php?reject=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject?')">Reject</a>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?php echo $apt['status']; ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php else: ?>
        <h2><i class="bi bi-calendar-check"></i> My Appointments</h2>
        
        <?php if($appointments->num_rows == 0): ?>
        <div class="alert alert-info">No appointments yet. <a href="book.php">Book an appointment</a></div>
        <?php else: ?>
        <table class="table table-hover bg-white rounded">
            <thead class="table-dark">
                <tr><th>Doctor</th><th>Specialization</th><th>Date & Time</th><th>Problem</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php while($apt = $appointments->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (strpos($apt['doctor_name'], 'Dr.') === 0) ? $apt['doctor_name'] : 'Dr. ' . $apt['doctor_name']; ?><br><small class="text-muted">Fee: ৳<?php echo $apt['consultation_fee'] ?? '0'; ?></small></td>
                    <td><?php echo $apt['specialization']; ?></td>
                    <td><strong><?php echo date('d M Y', strtotime($apt['appointment_date'])); ?></strong><br><small><?php echo $apt['appointment_time']; ?></small></td>
                    <td><small><?php echo $apt['patient_problem'] ?? 'N/A'; ?></small></td>
                    <td><span class="badge bg-<?php echo $apt['status']=='Confirmed'?'success':($apt['status']=='Cancelled'?'danger':'warning'); ?>"><?php echo $apt['status']; ?></span></td>
                    <td>
                        <?php if($apt['status'] == 'Pending'): ?>
                        <a href="list.php?suggest=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-info" title="Auto-reschedule to next available slot">
                            <i class="bi bi-arrow-clockwise"></i> Reschedule
                        </a>
                        <a href="list.php?cancel=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this appointment?')">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                        <?php elseif($apt['status'] == 'Confirmed'): ?>
                        <a href="list.php?suggest=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-info" title="Auto-reschedule to next available slot">
                            <i class="bi bi-arrow-clockwise"></i> Reschedule
                        </a>
                        <a href="list.php?cancel=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this appointment?')">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?php echo $apt['status']; ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
</html>