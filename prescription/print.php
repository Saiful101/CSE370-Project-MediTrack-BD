<?php
session_start();
if(!isset($_SESSION['person_id']) && !isset($_SESSION['admin_id'])) { 
    header("Location: http://localhost/MediTrackBD/index.php"); 
    exit; 
}
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$rx_id = $_GET['id'] ?? 0;

if(!$rx_id) {
    die("Prescription ID required");
}

$rx = $conn->query("SELECT p.*, 
    pat.name as patient_name, pat.blood_group as patient_bg, pat.gender as patient_gender,
    d.name as doctor_name, d.specialization, d.chamber_address, d.consultation_fee
    FROM PRESCRIPTION p
    JOIN PERSON pat ON p.person_id_patient = pat.person_id
    JOIN PERSON d ON p.person_id_doctor = d.person_id
    WHERE p.prescription_id = $rx_id")->fetch_assoc();

if(!$rx) {
    die("Prescription not found");
}

$medicines = $conn->query("SELECT m.name, m.category, c.dosage_level, c.daily_frequency, c.duration
    FROM CONTAINS c
    JOIN MEDICINE m ON c.medicine_id = m.medicine_id
    WHERE c.prescription_id = $rx_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription #<?php echo $rx_id; ?> - MediTrack BD</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .card { border: 2px solid #000 !important; }
        }
        body { font-family: 'Courier New', Courier, monospace; }
        .rx-header { 
            border-bottom: 3px solid #000; 
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .rx-box {
            border: 2px solid #000;
            padding: 20px;
            margin: 20px 0;
            background: #f9f9f9;
        }
        .medicine-row {
            border-bottom: 1px dashed #ccc;
            padding: 10px 0;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print / Save as PDF
            </button>
            <a href="javascript:window.close()" class="btn btn-secondary">Close</a>
        </div>

        <div class="rx-box" style="max-width: 800px; margin: 0 auto;">
            <!-- Header -->
            <div class="rx-header">
                <div class="row">
                    <div class="col-md-8">
                        <h2 style="margin: 0;"><strong>MediTrack BD</strong></h2>
                        <p style="margin: 5px 0;">Integrated Healthcare Management System</p>
                        <p style="margin: 0; font-size: 12px;">www.meditrackbd.com</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <h4 style="border: 2px solid #000; padding: 10px; display: inline-block;">
                            <strong>Rx #<?php echo $rx_id; ?></strong>
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Doctor Info -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <p><strong><?php echo (strpos($rx['doctor_name'], 'Dr.') === 0) ? $rx['doctor_name'] : 'Dr. ' . $rx['doctor_name']; ?></strong></p>
                    <p><?php echo $rx['specialization']; ?> Specialist</p>
                    <p>Chamber: <?php echo $rx['chamber_address'] ?? 'Meditrack Hospital'; ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <p>Date: <?php echo date('d M Y, h:i A', strtotime($rx['created_at'])); ?></p>
                </div>
            </div>

            <!-- Patient Info -->
            <div class="card bg-light mb-4" style="border: 1px solid #000;">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Patient Name:</strong> <?php echo $rx['patient_name']; ?></p>
                            <p><strong>Age/Gender:</strong> 
                                <?php echo isset($rx['date_of_birth']) ? (date('Y') - date('Y', strtotime($rx['date_of_birth']))) : 'N/A'; ?>
                                / <?php echo $rx['patient_gender'] ?? 'N/A'; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Blood Group:</strong> <?php echo $rx['patient_bg'] ?? 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Diagnosis -->
            <div class="mb-4">
                <p><strong>Diagnosis:</strong> <?php echo $rx['diagnosis']; ?></p>
                <?php if($rx['notes']): ?>
                <p><strong>Notes:</strong> <?php echo $rx['notes']; ?></p>
                <?php endif; ?>
            </div>

            <!-- Medicines -->
            <h5 style="border-bottom: 2px solid #000; padding-bottom: 5px;">Prescribed Medicines</h5>
            <table class="table" style="border: 1px solid #000;">
                <thead>
                    <tr style="background: #eee;">
                        <th style="border: 1px solid #000;">#</th>
                        <th style="border: 1px solid #000;">Medicine</th>
                        <th style="border: 1px solid #000;">Dosage</th>
                        <th style="border: 1px solid #000;">Frequency</th>
                        <th style="border: 1px solid #000;">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while($med = $medicines->fetch_assoc()): ?>
                    <tr>
                        <td style="border: 1px solid #000;"><?php echo $i++; ?></td>
                        <td style="border: 1px solid #000;"><strong><?php echo $med['name']; ?></strong></td>
                        <td style="border: 1px solid #000;"><?php echo $med['dosage_level']; ?></td>
                        <td style="border: 1px solid #000;"><?php echo $med['daily_frequency']; ?></td>
                        <td style="border: 1px solid #000;"><?php echo $med['duration']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Footer -->
            <div class="mt-4 pt-4" style="border-top: 1px solid #000;">
                <div class="row">
                    <div class="col-md-6">
                        <p class="text-muted" style="font-size: 11px;">
                            This prescription is valid for 7 days from the date of issue.
                            <br>Always follow the dosage instructions.
                            <br>Consult doctor if any adverse reaction occurs.
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="signature-line d-inline-block">
                            <p style="margin: 0; font-size: 11px;">Doctor's Signature</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <p style="font-size: 11px; color: #666;">
                    Generated by MediTrack BD | Prescription ID: <?php echo $rx_id; ?>
                </p>
            </div>
        </div>
    </div>
</body>
</html>