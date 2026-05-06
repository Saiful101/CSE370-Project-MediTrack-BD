<?php
session_start();
if(!isset($_SESSION['person_id']) && !isset($_SESSION['admin_id'])) { 
    header("Location: http://localhost/MediTrackBD/index.php"); 
    exit; 
}

$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$id = $_GET['id'] ?? 0;

// Get prescription details
$rx = $conn->query("SELECT p.*, pat.name as patient_name, pat.date_of_birth, pat.gender, pat.blood_group,
    d.name as doctor_name, d.specialization, d.consultation_fee, d.chamber_address
    FROM PRESCRIPTION p
    JOIN PERSON pat ON p.person_id_patient = pat.person_id
    JOIN PERSON d ON p.person_id_doctor = d.person_id
    WHERE p.prescription_id = $id")->fetch_assoc();

if(!$rx) {
    echo "Prescription not found!";
    exit;
}

// Get medicines
$medicines = $conn->query("SELECT m.name, c.dosage_level, c.daily_frequency, c.duration, m.price
    FROM CONTAINS c
    JOIN MEDICINE m ON c.medicine_id = m.medicine_id
    WHERE c.prescription_id = $id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription #<?php echo $id; ?> - MediTrack BD</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
        .header { text-align: center; border-bottom: 3px solid #667eea; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #667eea; margin: 0; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .info-box { flex: 1; padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin: 0 10px; }
        .info-box h4 { margin: 0 0 10px 0; color: #667eea; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #667eea; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .footer { margin-top: 50px; text-align: center; color: #666; font-size: 12px; }
        .diagnosis { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .signature { text-align: right; margin-top: 50px; }
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" class="btn" style="background: #667eea; color: white; padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer;">
            <i class="bi bi-printer"></i> Print / Save as PDF
        </button>
        <button onclick="window.close()" class="btn" style="background: #6c757d; color: white; padding: 10px 30px; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>

    <div class="header">
        <h1>MediTrack BD</h1>
        <p>Healthcare Management System</p>
        <p>Phone: +880-123-456-789 | Email: info@meditrackbd.com</p>
    </div>

    <div class="info-row">
        <div class="info-box">
            <h4>Patient Information</h4>
            <p><strong>Name:</strong> <?php echo $rx['patient_name']; ?></p>
            <p><strong>Age:</strong> <?php echo date_diff(date_create($rx['date_of_birth']), date_create('today'))->y; ?> Years</p>
            <p><strong>Gender:</strong> <?php echo $rx['gender']; ?></p>
            <p><strong>Blood Group:</strong> <?php echo $rx['blood_group'] ?? 'N/A'; ?></p>
        </div>
        <div class="info-box">
            <h4>Doctor Information</h4>
            <p><strong>Name:</strong> <?php echo (strpos($rx['doctor_name'], 'Dr.') === 0) ? $rx['doctor_name'] : 'Dr. ' . $rx['doctor_name']; ?></p>
            <p><strong>Specialization:</strong> <?php echo $rx['specialization']; ?></p>
            <p><strong>Consultation Fee:</strong> ৳<?php echo $rx['consultation_fee']; ?></p>
            <p><strong>Chamber:</strong> <?php echo $rx['chamber_address'] ?? 'N/A'; ?></p>
        </div>
    </div>

    <div class="diagnosis">
        <h4>Diagnosis</h4>
        <p><?php echo $rx['diagnosis']; ?></p>
        <?php if($rx['notes']): ?>
        <h4>Additional Notes</h4>
        <p><?php echo $rx['notes']; ?></p>
        <?php endif; ?>
    </div>

    <h3>Prescribed Medicines</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Medicine Name</th>
                <th>Dosage</th>
                <th>Frequency</th>
                <th>Duration</th>
                <th>Price (BDT)</th>
            </tr>
        </thead>
        <tbody>
            <?php $count = 1; $total = 0; ?>
            <?php while($med = $medicines->fetch_assoc()): ?>
            <tr>
                <td><?php echo $count++; ?></td>
                <td><strong><?php echo $med['name']; ?></strong></td>
                <td><?php echo $med['dosage_level']; ?></td>
                <td><?php echo $med['daily_frequency']; ?></td>
                <td><?php echo $med['duration']; ?></td>
                <td>৳<?php echo $med['price']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="signature">
        <p>_______________________</p>
        <p><strong><?php echo (strpos($rx['doctor_name'], 'Dr.') === 0) ? $rx['doctor_name'] : 'Dr. ' . $rx['doctor_name']; ?></strong></p>
        <p><?php echo $rx['specialization']; ?></p>
        <p>Date: <?php echo date('d M Y', strtotime($rx['created_at'])); ?></p>
    </div>

    <div class="footer">
        <p>Prescription generated on <?php echo date('d M Y, h:i A'); ?> | MediTrack BD Healthcare System</p>
        <p>This is a computer-generated prescription. Please consult your doctor for any queries.</p>
    </div>
</body>
</html>
