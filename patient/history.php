<?php
require_once('../includes/auth.php');
// Patient, Doctor, and Admin can access (with different views)
require_roles(['Patient', 'Doctor', 'Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$base = "http://localhost/MediTrackBD";
$msg = "";
$error = "";

// Handle send to pharmacy from patient side
if(isset($_POST['send_to_pharmacy'])) {
    $prescription_id = $_POST['prescription_id'];
    
    $contains = $conn->query("SELECT c.medicine_id, c.daily_frequency, c.duration, m.price, m.stock_quantity, m.name
        FROM CONTAINS c 
        JOIN MEDICINE m ON c.medicine_id = m.medicine_id 
        WHERE c.prescription_id = $prescription_id");
    
    if($contains->num_rows > 0) {
        $all_ok = true;
        while($med = $contains->fetch_assoc()) {
            $med_id = $med['medicine_id'];
            $price = $med['price'];
            
            // Calculate quantity needed from dosage/frequency/duration
            preg_match('/\d+/', $med['daily_frequency'], $freq_match);
            preg_match('/\d+/', $med['duration'], $dur_match);
            $frequency = isset($freq_match[0]) ? intval($freq_match[0]) : 1;
            $duration_days = isset($dur_match[0]) ? intval($dur_match[0]) : 1;
            $quantity_needed = $frequency * $duration_days;
            
            $total = $price * $quantity_needed;
            $new_stock = $med['stock_quantity'] - $quantity_needed;
            
            if($new_stock < 0) {
                $error = "Not enough stock for " . $med['name'] . ". Need $quantity_needed, have " . $med['stock_quantity'];
                $all_ok = false;
                continue;
            }
            
            $conn->query("UPDATE MEDICINE SET stock_quantity = $new_stock WHERE medicine_id = $med_id");
            $sale_id = rand(10000, 99999);
            $conn->query("INSERT INTO PHARMACY_SALES (sale_id, quantity_sold, total_price, prescription_id, medicine_id)
                VALUES ($sale_id, $quantity_needed, $total, $prescription_id, $med_id)");
        }
        
        if($all_ok && empty($error)) {
            $msg = "Prescription sent to pharmacy! Medicines dispensed.";
        }
    }
}

$user_type = $_SESSION['user_type'] ?? '';

$view_patient_id = $_GET['patient_id'] ?? 0;
if($view_patient_id) {
    $person_id = $view_patient_id;
} else {
    $person_id = $_SESSION['person_id'] ?? 0;
}

$profile = $conn->query("SELECT * FROM PERSON WHERE person_id = $person_id")->fetch_assoc();
$prescriptions = $conn->query("SELECT p.*, d.name as doctor_name, d.specialization 
    FROM PRESCRIPTION p 
    JOIN PERSON d ON p.person_id_doctor = d.person_id 
    WHERE p.person_id_patient = $person_id 
    ORDER BY p.created_at DESC");
$appointments = $conn->query("SELECT a.*, d.name as doctor_name 
    FROM APPOINTMENT a 
    JOIN PERSON d ON a.person_id_doctor = d.person_id 
    WHERE a.person_id_patient = $person_id 
    ORDER BY a.appointment_date DESC");

$all_patients = $conn->query("SELECT * FROM PERSON WHERE person_type = 'Patient' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - MediTrack BD</title>
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
                <h2><i class="bi bi-file-medical"></i> My Prescriptions & Appointments</h2>
        
        <?php if($msg): ?>
        <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($user_type == 'Admin' && !$view_patient_id): ?>
        <table class="table table-hover bg-white rounded">
            <thead class="table-dark">
                <tr><th>ID</th><th>Name</th><th>Blood Group</th><th>Allergies</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php while($p = $all_patients->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $p['person_id']; ?></td>
                    <td><strong><?php echo $p['name']; ?></strong></td>
                    <td><span class="badge bg-danger"><?php echo $p['blood_group'] ?? 'N/A'; ?></span></td>
                    <td><?php echo $p['allergy'] ?? 'None'; ?></td>
                    <td><a href="history.php?patient_id=<?php echo $p['person_id']; ?>" class="btn btn-sm btn-primary">View History</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4><?php echo $profile['name']; ?> - Health Profile</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Blood Group:</strong> <span class="badge bg-danger"><?php echo $profile['blood_group'] ?? 'N/A'; ?></span></p>
                        <p><strong>Date of Birth:</strong> <?php echo $profile['date_of_birth'] ?? 'N/A'; ?></p>
                        <p><strong>Gender:</strong> <?php echo $profile['gender'] ?? 'N/A'; ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Allergies:</strong> <?php echo $profile['allergy'] ?? 'None'; ?></p>
                        <p><strong>Chronic Diseases:</strong> <?php echo $profile['chronic_disease'] ?? 'None'; ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Emergency Contact:</strong> <?php echo $profile['emergency_contact'] ?? 'N/A'; ?></p>
                        <p><strong>City:</strong> <?php echo $profile['city'] ?? 'N/A'; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <h4>Prescriptions</h4>
        
        <!-- Quick Action: Send All to Pharmacy -->
        <?php
        $pending_rx = $conn->query("SELECT DISTINCT p.prescription_id 
            FROM PRESCRIPTION p 
            LEFT JOIN PHARMACY_SALES ps ON p.prescription_id = ps.prescription_id 
            WHERE p.person_id_patient = $person_id AND ps.prescription_id IS NULL");
        if($pending_rx && $pending_rx->num_rows > 0):
        ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">
            <div>
                <strong><i class="bi bi-exclamation-circle"></i> You have <?php echo $pending_rx->num_rows; ?> prescription(s) pending to send to pharmacy.</strong>
            </div>
            <a href="<?php echo $base; ?>/pharmacy/index.php" class="btn btn-warning btn-lg">
                <i class="bi bi-shop"></i> Go to Pharmacy
            </a>
        </div>
        <?php endif; ?>
        
        <?php if($prescriptions->num_rows > 0): ?>
            <?php while($rx = $prescriptions->fetch_assoc()): ?>
            <div class="card mb-3 border-<?php 
                $sent = $conn->query("SELECT COUNT(*) FROM PHARMACY_SALES WHERE prescription_id = {$rx['prescription_id']}")->fetch_row()[0];
                echo $sent > 0 ? 'success' : 'warning'; 
            ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5><i class="bi bi-file-medical"></i> <?php echo (strpos($rx['doctor_name'], 'Dr.') === 0) ? $rx['doctor_name'] : 'Dr. ' . $rx['doctor_name']; ?> (<?php echo $rx['specialization']; ?>)</h5>
                            <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($rx['created_at'])); ?></small>
                        </div>
                        <div class="btn-group-vertical gap-2">
                            <a href="<?php echo $base; ?>/prescription/print.php?id=<?php echo $rx['prescription_id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-printer"></i> Download PDF
                            </a>
                            <?php if($sent == 0): ?>
                            <form method="POST">
                                <input type="hidden" name="prescription_id" value="<?php echo $rx['prescription_id']; ?>">
                                <button type="submit" name="send_to_pharmacy" class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-cart-plus"></i> Send to Pharmacy
                                </button>
                            </form>
                            <?php else: ?>
                            <a href="<?php echo $base; ?>/pharmacy/index.php" class="btn btn-secondary btn-sm">
                                <i class="bi bi-bag-check"></i> Sent to Pharmacy
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p><strong>Diagnosis:</strong> <?php echo $rx['diagnosis']; ?></p>
                    <?php if($rx['notes']): ?>
                    <p><strong>Notes:</strong> <?php echo $rx['notes']; ?></p>
                    <?php endif; ?>
                    <h6>Medicines Prescribed:</h6>
                    <table class="table table-bordered table-sm">
                        <thead class="table-warning"><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th></tr></thead>
                        <tbody>
                            <?php 
                            $rx_meds = $conn->query("SELECT m.name, c.dosage_level, c.daily_frequency, c.duration 
                                FROM CONTAINS c JOIN MEDICINE m ON c.medicine_id = m.medicine_id 
                                WHERE c.prescription_id = {$rx['prescription_id']}");
                            while($rm = $rx_meds->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $rm['name']; ?></strong></td>
                                <td><?php echo $rm['dosage_level']; ?></td>
                                <td><?php echo $rm['daily_frequency']; ?></td>
                                <td><?php echo $rm['duration']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
        <div class="alert alert-info">No prescriptions found.</div>
        <?php endif; ?>
        
        <h4 class="mt-4"><i class="bi bi-calendar-check"></i> My Appointments</h4>
        <?php if($appointments && $appointments->num_rows > 0): ?>
        <div class="row">
            <?php while($apt = $appointments->fetch_assoc()): ?>
            <div class="col-md-6 mb-3">
                <div class="card border-<?php echo $apt['status']=='Confirmed'?'success':($apt['status']=='Cancelled'?'danger':'warning'); ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5><i class="bi bi-person-badge"></i> <?php echo (strpos($apt['doctor_name'], 'Dr.') === 0) ? $apt['doctor_name'] : 'Dr. ' . $apt['doctor_name']; ?></h5>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('d M Y', strtotime($apt['appointment_date'])); ?></p>
                                <p class="mb-1"><strong>Time:</strong> <?php echo $apt['appointment_time']; ?></p>
                                <p class="mb-0"><strong>Problem:</strong> <?php echo $apt['patient_problem'] ?? 'Not specified'; ?></p>
                            </div>
                            <div>
                                <span class="badge bg-<?php echo $apt['status']=='Confirmed'?'success':($apt['status']=='Cancelled'?'danger':'warning'); ?> fs-6">
                                    <?php echo $apt['status']; ?>
                                </span>
                            </div>
                        </div>
                     </div>
                 </div>
             </div>
             <?php endwhile; ?>
         </div>
         <?php else: ?>
         <div class="alert alert-info">No appointments found. <a href="<?php echo $base; ?>/appointments/book.php">Book an appointment</a></div>
         <?php endif; ?>
         <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>