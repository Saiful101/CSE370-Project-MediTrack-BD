<?php
require_once('../includes/auth.php');
require_roles(['Admin']); // Only admins can access admin dashboard
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");
$admin_id = $_SESSION['admin_id'];
$name = $_SESSION['name'];
$admin_role = $_SESSION['user_type'] ?? 'Admin';

$base = "http://localhost/MediTrackBD";

// Get statistics
$total_patients = $conn->query("SELECT COUNT(*) FROM PERSON WHERE person_type = 'Patient'")->fetch_row()[0];
$total_doctors = $conn->query("SELECT COUNT(*) FROM PERSON WHERE person_type = 'Doctor'")->fetch_row()[0];
$total_appointments = $conn->query("SELECT COUNT(*) FROM APPOINTMENT")->fetch_row()[0];
$total_medicines = $conn->query("SELECT COUNT(*) FROM MEDICINE")->fetch_row()[0];
$total_donors = $conn->query("SELECT COUNT(*) FROM BLOOD_DONOR")->fetch_row()[0];
$total_hospitals = $conn->query("SELECT COUNT(*) FROM HOSPITAL")->fetch_row()[0];
$total_ambulances = $conn->query("SELECT COUNT(*) FROM AMBULANCE")->fetch_row()[0];
$total_prescriptions = $conn->query("SELECT COUNT(*) FROM PRESCRIPTION")->fetch_row()[0];

$recent_appointments = $conn->query("SELECT a.*, p.name as patient_name, d.name as doctor_name 
    FROM APPOINTMENT a 
    JOIN PERSON p ON a.person_id_patient = p.person_id 
    JOIN PERSON d ON a.person_id_doctor = d.person_id 
    ORDER BY a.appointment_id DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MediTrack BD</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .stat-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .feature-card { border-radius: 15px; padding: 20px; text-align: center; cursor: pointer; }
        .feature-card:hover { transform: translateY(-5px); transition: 0.3s; }
    </style>
</head>
<body>
    <?php include_once('../includes/header.php'); ?>

    <div class="container mt-4">
        <h2 class="mb-4"><i class="bi bi-heart-pulse"></i> Welcome to MediTrack BD</h2>
        
        <?php echo display_error(); ?>
        
        <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#profileModal">
            <i class="bi bi-person"></i> View My Information
        </button>

        <!-- Profile Modal -->
        <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 class="modal-title" id="profileModalLabel"><i class="bi bi-person-circle"></i> My Information</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Name</th>
                                <td><?php echo $name; ?> (<?php echo $admin_role; ?>)</td>
                            </tr>
                            <tr>
                                <th>Admin ID</th>
                                <td><?php echo $admin_id; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo $total_patients; ?></h3>
                            <p class="text-muted mb-0">Patients</p>
                        </div>
                        <i class="bi bi-people text-primary" style="font-size: 40px; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo $total_doctors; ?></h3>
                            <p class="text-muted mb-0">Doctors</p>
                        </div>
                        <i class="bi bi-person-badge text-success" style="font-size: 40px; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo $total_appointments; ?></h3>
                            <p class="text-muted mb-0">Appointments</p>
                        </div>
                        <i class="bi bi-calendar-check text-warning" style="font-size: 40px; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo $total_prescriptions; ?></h3>
                            <p class="text-muted mb-0">Prescriptions</p>
                        </div>
                        <i class="bi bi-file-medical text-info" style="font-size: 40px; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo $total_medicines; ?></h3>
                            <p class="text-muted mb-0">Medicines</p>
                        </div>
                        <i class="bi bi-capsule text-secondary" style="font-size: 40px; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo $total_donors; ?></h3>
                            <p class="text-muted mb-0">Blood Donors</p>
                        </div>
                        <i class="bi bi-droplet text-danger" style="font-size: 40px; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo $total_hospitals; ?></h3>
                            <p class="text-muted mb-0">Hospitals</p>
                        </div>
                        <i class="bi bi-hospital text-primary" style="font-size: 40px; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0"><?php echo $total_ambulances; ?></h3>
                            <p class="text-muted mb-0">Ambulances</p>
                        </div>
                        <i class="bi bi-ambulance text-success" style="font-size: 40px; opacity: 0.8;"></i>
                    </div>
                </div>
            </div>
        </div>

        <h4 class="mb-3">Quick Access</h4>
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card feature-card bg-primary text-white" onclick="location.href='<?php echo $base; ?>/analytics/index.php'">
                    <i class="bi bi-graph-up" style="font-size: 48px;"></i>
                    <h4 class="mt-3">Analytics</h4>
                    <p>View health reports</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card feature-card bg-warning text-dark" onclick="location.href='<?php echo $base; ?>/emergency/index.php'">
                    <i class="bi bi-exclamation-triangle" style="font-size: 48px;"></i>
                    <h4 class="mt-3">Emergency</h4>
                    <p>Manage emergencies</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card feature-card bg-success text-white" onclick="location.href='<?php echo $base; ?>/patient/history.php'">
                    <i class="bi bi-people" style="font-size: 48px;"></i>
                    <h4 class="mt-3">Patients</h4>
                    <p>View all patients</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card feature-card bg-info text-white" onclick="location.href='<?php echo $base; ?>/appointments/list.php'">
                    <i class="bi bi-calendar-check" style="font-size: 48px;"></i>
                    <h4 class="mt-3">Appointments</h4>
                    <p>View all appointments</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card feature-card bg-secondary text-white" onclick="location.href='<?php echo $base; ?>/prescription/create.php'">
                    <i class="bi bi-file-medical" style="font-size: 48px;"></i>
                    <h4 class="mt-3">Prescriptions</h4>
                    <p>Create prescriptions</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card feature-card bg-dark text-white" onclick="location.href='<?php echo $base; ?>/pharmacy/index.php'">
                    <i class="bi bi-capsule" style="font-size: 48px;"></i>
                    <h4 class="mt-3">Pharmacy</h4>
                    <p>Medicine inventory</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card feature-card bg-danger text-white" onclick="location.href='<?php echo $base; ?>/bloodbank/index.php'">
                    <i class="bi bi-droplet" style="font-size: 48px;"></i>
                    <h4 class="mt-3">Blood Bank</h4>
                    <p>Donor management</p>
                </div>
            </div>
        </div>

        <h4 class="mb-3">Recent Appointments</h4>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php while($apt = $recent_appointments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $apt['appointment_id']; ?></td>
                            <td><?php echo $apt['patient_name']; ?></td>
                            <td><?php echo (strpos($apt['doctor_name'], 'Dr.') === 0) ? $apt['doctor_name'] : 'Dr. ' . $apt['doctor_name']; ?></td>
                            <td><?php echo $apt['appointment_date']; ?></td>
                            <td><?php echo $apt['appointment_time']; ?></td>
                            <td><span class="badge bg-<?php 
                                if($apt['status'] == 'Confirmed') echo 'success';
                                elseif($apt['status'] == 'Pending') echo 'warning';
                                elseif($apt['status'] == 'Cancelled') echo 'danger';
                                else echo 'info';
                            ?>"><?php echo $apt['status']; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>