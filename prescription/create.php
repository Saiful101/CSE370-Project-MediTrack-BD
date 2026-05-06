<?php
require_once('../includes/auth.php');
// Only Doctor and Admin can create prescriptions
require_roles(['Doctor', 'Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$msg = ""; 
$warnings = []; 
$error = "";
$is_doctor = is_doctor();
$is_admin = is_admin();
$doctor_id = $_SESSION['person_id'] ?? 0;

// Handle send to pharmacy
if(isset($_POST['send_to_pharmacy'])) {
    $prescription_id = $_POST['prescription_id'];
    $quantity = $_POST['quantity'] ?? 1;
    
    // Get medicines from prescription
    $contains = $conn->query("SELECT c.medicine_id, m.price, m.stock_quantity, m.name, m.reorder_level
        FROM CONTAINS c 
        JOIN MEDICINE m ON c.medicine_id = m.medicine_id 
        WHERE c.prescription_id = $prescription_id");
    
    if($contains->num_rows > 0) {
        while($med = $contains->fetch_assoc()) {
            $med_id = $med['medicine_id'];
            $price = $med['price'];
            $total = $price * $quantity;
            $new_stock = $med['stock_quantity'] - $quantity;
            
            // Check stock
            if($new_stock < 0) {
                $error = "Not enough stock for " . $med['name'] . " (Available: " . $med['stock_quantity'] . ")";
                continue;
            }
            
            // Update stock
            $conn->query("UPDATE MEDICINE SET stock_quantity = $new_stock WHERE medicine_id = $med_id");
            
            // Create sales record
            $sale_id = rand(10000, 99999);
            $conn->query("INSERT INTO PHARMACY_SALES (sale_id, quantity_sold, total_price, prescription_id, medicine_id)
                VALUES ($sale_id, $quantity, $total, $prescription_id, $med_id)");
        }
        
        if(empty($error)) {
            $msg = "Prescription #$prescription_id sent to pharmacy! Medicines dispensed and stock updated.";
        }
    }
}

// Handle create prescription

if(isset($_POST['create_prescription'])) {
    $patient_id = $_POST['patient_id'];
    $diagnosis = $_POST['diagnosis'];
    $notes = $_POST['notes'];
    
    // Get selected medicines
    $selected_meds = [];
    if(!empty($_POST['medicine1'])) $selected_meds[] = $_POST['medicine1'];
    if(!empty($_POST['medicine2'])) $selected_meds[] = $_POST['medicine2'];
    if(!empty($_POST['medicine3'])) $selected_meds[] = $_POST['medicine3'];
    
    // Remove empty values first
    $selected_meds = array_filter($selected_meds);
    
    // Check for duplicate medicines
    if(count($selected_meds) !== count(array_unique($selected_meds))) {
        $error = "Duplicate medicine is not allowed in a prescription!";
    } elseif(empty($selected_meds)) {
        $error = "Please select at least one medicine!";
    } else {
        $prescription_id = rand(10000, 99999);
        
        // Insert prescription
        $sql = "INSERT INTO PRESCRIPTION (prescription_id, diagnosis, notes, person_id_patient, person_id_doctor) 
                VALUES ($prescription_id, '$diagnosis', '$notes', $patient_id, $doctor_id)";
        $conn->query($sql);
        
        // Add medicines to prescription
        $dosage = $_POST['dosage'] ?? '';
        $frequency = $_POST['frequency'] ?? '';
        $duration = $_POST['duration'] ?? '';
        
        foreach($selected_meds as $med_id) {
            $conn->query("INSERT INTO CONTAINS (prescription_id, medicine_id, dosage_level, daily_frequency, duration) 
                        VALUES ($prescription_id, $med_id, '$dosage', '$frequency', '$duration')");
        }
        
        // Check drug interactions
        if(count($selected_meds) >= 2) {
            foreach($selected_meds as $i => $m1) {
                foreach($selected_meds as $j => $m2) {
                    if($i < $j) {
$check = $conn->query("SELECT * FROM INTERACTS_WITH WHERE
                             (prime_medicine_id = $m1 AND secondary_medicine_id = $m2)
                             OR (prime_medicine_id = $m2 AND secondary_medicine_id = $m1)");
                        
                        if($check && $check->num_rows > 0) {
                            while($row = $check->fetch_assoc()) {
                                $warnings[] = $row;
                            }
                        }
                    }
                }
            }
        }
        
        if(count($warnings) > 0) {
            $msg = "Prescription created with interaction warnings!";
        } else {
            $msg = "Prescription created successfully! ID: " . $prescription_id;
        }
    }
}

$patients = $conn->query("SELECT * FROM PERSON WHERE person_type = 'Patient' ORDER BY name");

// Get all known interactions for display
$all_interactions = $conn->query("SELECT i.*, m1.name as med1, m2.name as med2 
    FROM INTERACTS_WITH i 
    JOIN MEDICINE m1 ON i.prime_medicine_id = m1.medicine_id 
    JOIN MEDICINE m2 ON i.secondary_medicine_id = m2.medicine_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Prescription - MediTrack BD</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .nav-link { color: white; padding: 12px 20px; border-radius: 10px; margin: 5px 0; display: block; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); text-decoration: none; color: white; }
        .warning-high { border-left: 5px solid #dc3545; background: #f8d7da; }
        .warning-medium { border-left: 5px solid #fd7e14; background: #ffe3c3; }
        .warning-low { border-left: 5px solid #ffc107; background: #fff3cd; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once('../includes/sidebar.php'); ?>
            <div class="col-md-10 p-4">
                <h2><i class="bi bi-file-medical"></i> Create Digital Prescription</h2>
        
                <?php if(!empty($warnings)): ?>
                <div class="alert alert-danger mt-3">
                    <h4><i class="bi bi-exclamation-triangle-fill"></i> Drug Interaction Warning!</h4>
                    <p><strong>These medicines should NOT be taken together:</strong></p>
                    <?php foreach($warnings as $w): ?>
                    <div class="warning-<?php echo strtolower($w['severity']); ?> p-3 mb-2 rounded">
                        <strong class="text-<?php echo $w['severity']=='High'?'danger':'dark'; ?>">
                            <?php echo $w['severity']; ?> Risk:
                        </strong> <?php echo $w['description']; ?>
                    </div>
                    <?php endforeach; ?>
                    <p class="mt-2">Please reconsider the prescription!</p>
        </div>
        <?php endif; ?>
        
        <?php if($msg && empty($warnings)): ?>
        <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" class="card p-4">
            <div class="mb-3">
                <label><strong>Select Patient</strong></label>
                <select name="patient_id" class="form-select form-select-lg" required>
                    <option value="">-- Select Patient --</option>
                    <?php while($p = $patients->fetch_assoc()): ?>
                    <option value="<?php echo $p['person_id']; ?>" <?php echo (isset($_POST['patient_id']) && $_POST['patient_id'] == $p['person_id']) ? 'selected' : ''; ?>><?php echo $p['name']; ?> (<?php echo $p['blood_group'] ?? 'N/A'; ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label><strong>Diagnosis</strong></label>
                <textarea name="diagnosis" class="form-control form-control-lg" rows="2" placeholder="Enter diagnosis..." required><?php echo $_POST['diagnosis'] ?? ''; ?></textarea>
            </div>
            <div class="mb-3">
                <label>Notes (Optional)</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes..."><?php echo $_POST['notes'] ?? ''; ?></textarea>
            </div>
            
            <hr>
            <h4>Select Medicines (Choose 2 to test Drug Interaction)</h4>
            <p class="text-muted">Try: Paracetamol (Medicine 1) + Aspirin (Medicine 2) = Warning!</p>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label><strong>Medicine 1</strong></label>
                    <select name="medicine1" class="form-select">
                        <option value="">-- Select --</option>
                        <?php
                        $meds = $conn->query("SELECT * FROM MEDICINE ORDER BY name");
                        while($m = $meds->fetch_assoc()): ?>
                        <option value="<?php echo $m['medicine_id']; ?>" <?php echo (isset($_POST['medicine1']) && $_POST['medicine1'] == $m['medicine_id']) ? 'selected' : ''; ?>><?php echo $m['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label><strong>Medicine 2</strong></label>
                    <select name="medicine2" class="form-select">
                        <option value="">-- Select --</option>
                        <?php
                        $meds = $conn->query("SELECT * FROM MEDICINE ORDER BY name");
                        while($m = $meds->fetch_assoc()): ?>
                        <option value="<?php echo $m['medicine_id']; ?>" <?php echo (isset($_POST['medicine2']) && $_POST['medicine2'] == $m['medicine_id']) ? 'selected' : ''; ?>><?php echo $m['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Medicine 3 (Optional)</label>
                    <select name="medicine3" class="form-select">
                        <option value="">-- Select --</option>
                        <?php
                        $meds = $conn->query("SELECT * FROM MEDICINE ORDER BY name");
                        while($m = $meds->fetch_assoc()): ?>
                        <option value="<?php echo $m['medicine_id']; ?>" <?php echo (isset($_POST['medicine3']) && $_POST['medicine3'] == $m['medicine_id']) ? 'selected' : ''; ?>><?php echo $m['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label>Dosage</label>
                    <input type="text" name="dosage" class="form-control" placeholder="e.g. 500mg" value="<?php echo $_POST['dosage'] ?? ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Frequency</label>
                    <input type="text" name="frequency" class="form-control" placeholder="e.g. 3 times daily" value="<?php echo $_POST['frequency'] ?? ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Duration</label>
                    <input type="text" name="duration" class="form-control" placeholder="e.g. 5 days" value="<?php echo $_POST['duration'] ?? ''; ?>">
                </div>
            </div>
            <hr>
            <button type="submit" name="create_prescription" class="btn btn-success btn-lg w-100">
                <i class="bi bi-check-lg"></i> Create Prescription
            </button>
        </form>
        
        <!-- Recent Prescriptions with Send to Pharmacy -->
        <?php
        if($is_doctor):
            $my_prescriptions = $conn->query("SELECT p.*, pat.name as patient_name
                FROM PRESCRIPTION p
                JOIN PERSON pat ON p.person_id_patient = pat.person_id
                WHERE p.person_id_doctor = $doctor_id
                ORDER BY p.created_at DESC
                LIMIT 10");
        else:
            $my_prescriptions = $conn->query("SELECT p.*, d.name as doctor_name
                FROM PRESCRIPTION p
                JOIN PERSON d ON p.person_id_doctor = d.person_id
                ORDER BY p.created_at DESC
                LIMIT 10");
        endif;
        ?>
        
        <?php if($my_prescriptions && $my_prescriptions->num_rows > 0): ?>
        <div class="card mt-4">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-shop"></i> Recent Prescriptions - Send to Pharmacy</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Rx ID</th>
                            <th><?php echo $is_doctor ? 'Patient' : 'Doctor'; ?></th>
                            <th>Diagnosis</th>
                            <th>Medicines</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($rx = $my_prescriptions->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $rx['prescription_id']; ?></strong>
                                <br><small class="text-muted"><?php echo date('d M Y', strtotime($rx['created_at'])); ?></small>
                            </td>
                            <td><?php echo $is_doctor ? $rx['patient_name'] : 'Dr. ' . $rx['doctor_name']; ?></td>
                            <td><?php echo $rx['diagnosis']; ?></td>
                            <td>
                                <?php
                                $rx_meds = $conn->query("SELECT m.name, c.dosage_level, c.daily_frequency, c.duration
                                    FROM CONTAINS c
                                    JOIN MEDICINE m ON c.medicine_id = m.medicine_id
                                    WHERE c.prescription_id = {$rx['prescription_id']}");
                                while($m = $rx_meds->fetch_assoc()) {
                                    echo "<small>{$m['name']} - {$m['dosage_level']}, {$m['daily_frequency']}, {$m['duration']}</small><br>";
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $sold = $conn->query("SELECT COUNT(*) FROM PHARMACY_SALES WHERE prescription_id = {$rx['prescription_id']}")->fetch_row()[0];
                                if($sold > 0): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Sent to Pharmacy</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($sold == 0): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="prescription_id" value="<?php echo $rx['prescription_id']; ?>">
                                    <div class="input-group input-group-sm" style="width: 120px;">
                                        <input type="number" name="quantity" class="form-control" value="1" min="1" max="10">
                                        <button type="submit" name="send_to_pharmacy" class="btn btn-warning">Send</button>
                                    </div>
                                </form>
                                <?php else: ?>
                                <small class="text-success">Dispensed</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5>Known Drug Interactions in Database</h5>
            </div>
                <?php if($all_interactions && $all_interactions->num_rows > 0): ?>
                <table class="table table-striped">
                    <thead><tr><th>Medicine 1</th><th>Medicine 2</th><th>Severity</th><th>Description</th></tr></thead>
                    <tbody>
                        <?php while($ai = $all_interactions->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $ai['med1']; ?></td>
                            <td><?php echo $ai['med2']; ?></td>
                            <td><span class="badge bg-<?php echo $ai['severity']=='High'?'danger':($ai['severity']=='Medium'?'warning':'info'); ?>"><?php echo $ai['severity']; ?></span></td>
                            <td><?php echo $ai['description']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted">No drug interactions in database.</p>
                <?php endif; ?>
            </div>
        </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>