<?php
require_once('../includes/auth.php');
// All roles can access emergency (with different views)
require_roles(['Patient', 'Doctor', 'Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$base = "http://localhost/MediTrackBD";
$is_admin = is_admin();
$is_doctor = is_doctor();
$is_patient = is_patient();
$person_id = $_SESSION['person_id'] ?? 0;
$msg = "";
$error = "";

// Handle patient emergency request
if(isset($_POST['request_emergency'])) {
    $location = $_POST['location'];
    $specialization = $_POST['specialization'];
    $condition_level = $_POST['condition_level'];
    $need_ambulance = isset($_POST['need_ambulance']) ? 1 : 0;
    $emergency_id = rand(10000, 99999);
    $patient_id = $_SESSION['person_id'];

    if($need_ambulance) {
        $ambulance = $conn->query("SELECT ambulance_id FROM AMBULANCE WHERE status = 'Available' LIMIT 1");
        $amb_id = $ambulance->num_rows > 0 ? $ambulance->fetch_assoc()['ambulance_id'] : NULL;
        
        $conn->query("INSERT INTO EMERGENCY_REQUEST (emergency_id, patient_location, specialization_needed, condition_level, doctor_id, ambulance_id, request_status, person_id_patient)
            VALUES ($emergency_id, '$location', '$specialization', '$condition_level', NULL, " . ($amb_id ? $amb_id : "NULL") . ", " . ($amb_id ? "'Dispatched'" : "'Requested'") . ", $patient_id)");
        
        if($amb_id) {
            $conn->query("UPDATE AMBULANCE SET status = 'Busy' WHERE ambulance_id = $amb_id");
            $msg = "Emergency request #" . $emergency_id . " submitted! Ambulance dispatched.";
        } else {
            $msg = "Emergency submitted! No ambulance available. Request sent to doctors.";
        }
    } else {
        $conn->query("INSERT INTO EMERGENCY_REQUEST (emergency_id, patient_location, specialization_needed, condition_level, doctor_id, ambulance_id, person_id_patient)
            VALUES ($emergency_id, '$location', '$specialization', '$condition_level', NULL, NULL, $patient_id)");
        $msg = "Emergency request #" . $emergency_id . " submitted! Doctors will respond.";
    }
}

// Handle doctor response - can accept only if specialization matches
if(isset($_GET['respond'])) {
    $emergency_id = $_GET['respond'];
    $action = $_GET['action'];
    $doctor_id = $_SESSION['person_id'];
    $doc_info = $conn->query("SELECT specialization FROM PERSON WHERE person_id = $doctor_id")->fetch_assoc();
    
    $em = $conn->query("SELECT * FROM EMERGENCY_REQUEST WHERE emergency_id = $emergency_id")->fetch_assoc();
    
    if($action == 'accept') {
        $conn->query("UPDATE EMERGENCY_REQUEST SET doctor_response = 'Accepted', doctor_id = $doctor_id, request_status = 'Dispatched' WHERE emergency_id = $emergency_id");
        $msg = "You accepted Emergency #$emergency_id! Patient will be notified.";
    } elseif($action == 'reject') {
        $conn->query("UPDATE EMERGENCY_REQUEST SET doctor_response = 'Unavailable' WHERE emergency_id = $emergency_id");
        $msg = "Request declined.";
    }
}

// Handle admin status update
if(isset($_POST['update_status'])) {
    $emergency_id = $_POST['emergency_id'];
    $new_status = $_POST['new_status'];
    $ambulance_id = $_POST['ambulance_id'] ?? NULL;

    if($new_status == 'Completed') {
        // Free the ambulance when completed
        $conn->query("UPDATE EMERGENCY_REQUEST SET request_status = '$new_status' WHERE emergency_id = $emergency_id");
        if($ambulance_id) {
            $conn->query("UPDATE AMBULANCE SET status = 'Available' WHERE ambulance_id = $ambulance_id");
        }
    } else {
        $conn->query("UPDATE EMERGENCY_REQUEST SET request_status = '$new_status', ambulance_id = " . ($ambulance_id ? $ambulance_id : "NULL") . " WHERE emergency_id = $emergency_id");
        if($ambulance_id) {
            $conn->query("UPDATE AMBULANCE SET status = 'Busy' WHERE ambulance_id = $ambulance_id");
        }
    }
    $msg = "Status updated to: " . $new_status;
}

// Fetch emergencies - different views for different users
if($is_admin) {
    $emergencies = $conn->query("SELECT e.*, pat.name as patient_name, 
        d.name as doctor_name,
        a.driver_name as ambulance_driver, a.vehicle_number
        FROM EMERGENCY_REQUEST e
        LEFT JOIN PERSON pat ON e.person_id_patient = pat.person_id
        LEFT JOIN PERSON d ON e.doctor_id IS NOT NULL AND d.person_id = e.doctor_id
        LEFT JOIN AMBULANCE a ON e.ambulance_id IS NOT NULL AND a.ambulance_id = e.ambulance_id
        ORDER BY e.emergency_id DESC");
} elseif($is_doctor) {
    // Doctor sees pending requests in their specialization
    $doc_specialization = $conn->query("SELECT specialization FROM PERSON WHERE person_id = $person_id")->fetch_assoc();
    $spec = $doc_specialization['specialization'] ?? '';
    
    $emergencies = $conn->query("SELECT e.*, pat.name as patient_name,
        a.driver_name as ambulance_driver, a.vehicle_number
        FROM EMERGENCY_REQUEST e
        LEFT JOIN PERSON pat ON e.person_id_patient = pat.person_id
        LEFT JOIN AMBULANCE a ON e.ambulance_id = a.ambulance_id
        WHERE e.request_status != 'Completed'
        AND (e.specialization_needed = '$spec' OR e.specialization_needed = 'General' OR e.specialization_needed = 'Emergency')
        ORDER BY 
            CASE e.condition_level WHEN 'Critical' THEN 1 WHEN 'Serious' THEN 2 ELSE 3 END,
            e.emergency_id DESC");
} else {
    // Patient sees their own requests only
    $emergencies = $conn->query("SELECT e.*, a.driver_name as ambulance_driver, a.vehicle_number,
        d.name as doctor_name
        FROM EMERGENCY_REQUEST e
        LEFT JOIN PERSON d ON e.doctor_id IS NOT NULL AND d.person_id = e.doctor_id
        LEFT JOIN AMBULANCE a ON e.ambulance_id = a.ambulance_id
        WHERE e.person_id_patient = $person_id
        ORDER BY e.emergency_id DESC");
}

$available_ambulances = $conn->query("SELECT * FROM AMBULANCE WHERE status = 'Available'");
$available_doctors = $conn->query("SELECT * FROM PERSON WHERE person_type = 'Doctor' ORDER BY specialization");

// Get pending emergency count for notification badge
$pending_count = $conn->query("SELECT COUNT(*) FROM EMERGENCY_REQUEST WHERE request_status = 'Requested'")->fetch_row()[0];

// Get recent emergencies for notification dropdown (last 5 pending)
$notif_query = "SELECT e.*, pat.name as patient_name
    FROM EMERGENCY_REQUEST e
    LEFT JOIN PERSON pat ON e.person_id_patient = pat.person_id
    WHERE e.request_status IN ('Requested', 'Dispatched')";

if ($is_doctor && !$is_admin) {
    $doc_spec = $conn->query("SELECT specialization FROM PERSON WHERE person_id = $person_id")->fetch_assoc();
    $spec = $doc_spec['specialization'] ?? '';
    $notif_query .= " AND (e.specialization_needed = '$spec' OR e.specialization_needed = 'General' OR e.specialization_needed = 'Emergency')";
}

$notif_query .= " ORDER BY
    CASE e.condition_level WHEN 'Critical' THEN 1 WHEN 'Serious' THEN 2 ELSE 3 END,
    e.request_time DESC
    LIMIT 5";

$notification_emergencies = $conn->query($notif_query);
$dispatched_count = $conn->query("SELECT COUNT(*) FROM EMERGENCY_REQUEST WHERE request_status = 'Dispatched'")->fetch_row()[0];
$completed_count = $conn->query("SELECT COUNT(*) FROM EMERGENCY_REQUEST WHERE request_status = 'Completed'")->fetch_row()[0];
$available_amb_count = $available_ambulances->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>Emergency System - MediTrack BD</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .nav-link { color: white; padding: 12px 20px; border-radius: 10px; margin: 5px 0; display: block; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); text-decoration: none; color: white; }
        .emergency-card { border-radius: 15px; padding: 20px; }
        .status-requested { background: #dc3545; color: white; }
        .status-dispatched { background: #fd7e14; color: white; }
        .status-completed { background: #198754; color: white; }
.condition-critical { border-left: 5px solid #dc3545; }
        .condition-serious { border-left: 5px solid #fd7e14; }
        .condition-moderate { border-left: 5px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once('../includes/sidebar.php'); ?>
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <?php if($is_admin): ?>
                        <i class="bi bi-exclamation-triangle-fill text-danger"></i> Emergency Request Management
                        <?php elseif($is_doctor): ?>
                        <i class="bi bi-exclamation-triangle text-warning"></i> Incoming Emergency Requests
                        <?php else: ?>
                        <i class="bi bi-heart-pulse text-danger"></i> Emergency Service
                        <?php endif; ?>
                    </h2>
                    <?php if($is_admin || $is_doctor): ?>
                    <div class="d-flex align-items-center gap-2">
                        <?php if($pending_count > 0): ?>
                        <span class="badge bg-danger" style="font-size: 14px; padding: 8px 12px;">
                            <i class="bi bi-bell-fill"></i> <?php echo $pending_count; ?> New Emergency<?php echo $pending_count > 1 ? 's' : ''; ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary" style="font-size: 14px; padding: 8px 12px;">
                            <i class="bi bi-bell"></i> No New Emergency
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if($msg): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $msg; ?></div>
                <?php endif; ?>
                <?php if($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <?php if(!$is_admin && !$is_doctor): ?>
                <!-- Patient Emergency Request Form -->
                <div class="card emergency-card mb-4 condition-critical">
                    <div class="card-header bg-danger text-white">
                        <h4><i class="bi bi-phone"></i> Trigger Emergency Request</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Your Current Location <span class="text-danger">*</span></label>
                                    <textarea name="location" class="form-control" rows="2" required placeholder="Enter your exact address where help is needed"></textarea>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label>Condition Level <span class="text-danger">*</span></label>
                                    <select name="condition_level" class="form-select" required>
                                        <option value="Critical">Critical - Life Threatening</option>
                                        <option value="Serious">Serious - Urgent Care Needed</option>
                                        <option value="Moderate">Moderate - Needs Attention</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label>Specialization Needed <span class="text-danger">*</span></label>
                                    <select name="specialization" class="form-select" required>
                                        <option value="General">General Medicine</option>
                                        <option value="Cardiology">Cardiology (Heart)</option>
                                        <option value="Neurology">Neurology (Brain/Nerve)</option>
                                        <option value="Orthopedics">Orthopedics (Bone)</option>
                                        <option value="Pediatrics">Pediatrics (Child)</option>
                                        <option value="Surgery">Surgery</option>
                                        <option value="Emergency">Emergency</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="need_ambulance" id="needAmbulance" value="1">
                                        <label class="form-check-label text-danger" for="needAmbulance">
                                            <i class="bi bi-ambulance"></i> <strong>I also need an ambulance dispatched to my location</strong>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" name="request_emergency" class="btn btn-danger btn-lg w-100">
                                        <i class="bi bi-send"></i> Submit Emergency Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($is_admin || $is_doctor): ?>
                <!-- Live Monitoring Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card text-center border-danger">
                            <h2 class="text-danger"><?php echo $pending_count; ?></h2>
                            <p class="text-muted mb-0"><i class="bi bi-exclamation-circle"></i> Pending</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center border-warning">
                            <h2 class="text-warning"><?php echo $dispatched_count; ?></h2>
                            <p class="text-muted mb-0"><i class="bi bi-truck"></i> Dispatched</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center border-success">
                            <h2 class="text-success"><?php echo $completed_count; ?></h2>
                            <p class="text-muted mb-0"><i class="bi bi-check-circle"></i> Completed</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center border-info">
                            <h2 class="text-info"><?php echo $available_amb_count; ?></h2>
                            <p class="text-muted mb-0"><i class="bi bi-ambulance"></i> Available Ambulances</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Emergency Cases List -->
                <h4><i class="bi bi-list"></i> <?php echo $is_admin ? 'All Emergency Cases' : 'My Emergency Requests'; ?></h4>
                <?php if($emergencies->num_rows > 0): ?>
                <div class="row">
                    <?php while($e = $emergencies->fetch_assoc()): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card emergency-card condition-<?php echo strtolower($e['condition_level']); ?>">
                            <div class="card-header bg-dark text-white">
                                <div class="d-flex justify-content-between">
                                    <strong>Emergency #<?php echo $e['emergency_id']; ?></strong>
                                    <span class="badge <?php
                                        if($e['request_status'] == 'Requested') echo 'bg-danger';
                                        elseif($e['request_status'] == 'Dispatched') echo 'bg-warning';
                                        else echo 'bg-success';
                                    ?>"><?php echo $e['request_status']; ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <?php if(isset($e['patient_name'])): ?>
                                        <p><i class="bi bi-person text-primary"></i> <strong>Patient:</strong> <?php echo $e['patient_name']; ?></p>
                                        <?php endif; ?>
                                        <p><i class="bi bi-geo-alt text-danger"></i> <strong>Location:</strong><br><small><?php echo $e['patient_location']; ?></small></p>
                                        <p><i class="bi bi-activity text-warning"></i> <strong>Condition:</strong> <?php echo $e['condition_level']; ?></p>
                                        <p><i class="bi bi-calendar"></i> <strong>Specialization:</strong> <?php echo $e['specialization_needed']; ?></p>
                                    </div>
                                    <div class="col-6">
                                        <p><i class="bi bi-clock"></i> <strong>Time:</strong><br><small><?php echo $e['request_time']; ?></small></p>
                                        <?php if($e['doctor_id'] && isset($e['doctor_name'])): ?>
                                        <p><i class="bi bi-person-check text-success"></i> <strong>Doctor:</strong> <?php echo (strpos($e['doctor_name'], 'Dr.') === 0) ? $e['doctor_name'] : 'Dr. ' . $e['doctor_name']; ?></p>
                                        <?php elseif($is_doctor): ?>
                                        <p><i class="bi bi-person-x text-muted"></i> <strong>Doctor:</strong> Not assigned yet</p>
                                        <?php else: ?>
                                        <p><i class="bi bi-person-x text-muted"></i> <strong>Doctor:</strong> Pending</p>
                                        <?php endif; ?>
                                        <?php if($e['ambulance_id'] && isset($e['vehicle_number'])): ?>
                                        <p><i class="bi bi-ambulance text-info"></i> <strong>Ambulance:</strong> <?php echo $e['vehicle_number']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <hr>

                                <!-- Status Update Form (Admin) -->
                                <?php if($is_admin): ?>
                                <form method="POST" class="row g-2">
                                    <input type="hidden" name="emergency_id" value="<?php echo $e['emergency_id']; ?>">
                                    <div class="col-md-4">
                                        <select name="new_status" class="form-select form-select-sm">
                                            <option value="Requested" <?php echo $e['request_status'] == 'Requested' ? 'selected' : ''; ?>>Requested</option>
                                            <option value="Dispatched" <?php echo $e['request_status'] == 'Dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                                            <option value="Completed">Completed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="ambulance_id" class="form-select form-select-sm">
                                            <option value="">Select Ambulance</option>
                                            <?php
                                            $amb = $conn->query("SELECT * FROM AMBULANCE WHERE status = 'Available'");
                                            while($a = $amb->fetch_assoc()):
                                            ?>
                                            <option value="<?php echo $a['ambulance_id']; ?>"><?php echo $a['vehicle_number']; ?> - <?php echo $a['driver_name']; ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" name="update_status" class="btn btn-sm btn-primary w-100">Update Status</button>
                                    </div>
                                </form>
                                <?php endif; ?>

                                <!-- Doctor Response -->
                                <?php if(!$is_admin && $_SESSION['user_type'] == 'Doctor' && !$e['doctor_id']): ?>
                                <div class="mt-2">
                                    <a href="?respond=<?php echo $e['emergency_id']; ?>&action=accept" class="btn btn-success btn-sm"><i class="bi bi-check"></i> Accept</a>
                                    <a href="?respond=<?php echo $e['emergency_id']; ?>&action=reject" class="btn btn-secondary btn-sm"><i class="bi bi-x"></i> Decline</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No emergency requests found.
            </div>
            <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>