<?php
$base = "http://localhost/MediTrackBD";
require_once('../includes/auth.php');
// Patient and Doctor can access this dashboard (Doctor uses it too)
require_roles(['Patient', 'Doctor']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");
$person_id = $_SESSION['person_id'];
$name = $_SESSION['name'];
$user_type = $_SESSION['user_type'];

$profile = $conn->query("SELECT * FROM PERSON WHERE person_id = $person_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediTrack BD - Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .nav-link { color: white; padding: 12px 20px; border-radius: 10px; margin: 5px 0; display: block; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); text-decoration: none; color: white; }
        .feature-card { border-radius: 15px; padding: 30px 20px; text-align: center; cursor: pointer; transition: 0.3s; }
        .feature-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once('../includes/sidebar.php'); ?>
             <div class="col-md-10 p-4">
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
                                    <?php if($user_type == 'Doctor'): ?>
                                    <tr>
                                        <th width="40%">Name</th>
                                        <td>Dr. <?php echo $name; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Specialization</th>
                                        <td><?php echo $profile['specialization'] ?? 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Experience</th>
                                        <td><?php echo $profile['experience_year'] ?? '0'; ?> Years</td>
                                    </tr>
                                    <tr>
                                        <th>Consultation Fee</th>
                                        <td>৳<?php echo $profile['consultation_fee'] ?? '0'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Available Days</th>
                                        <td><?php echo $profile['available_days'] ?? 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Chamber</th>
                                        <td><?php echo $profile['chamber_address'] ?? 'Not set'; ?></td>
                                    </tr>
                                    <?php else: ?>
                                    <tr>
                                        <th width="40%">Name</th>
                                        <td><?php echo $name; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Blood Group</th>
                                        <td><span class="badge bg-danger"><?php echo $profile['blood_group'] ?? 'N/A'; ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Date of Birth</th>
                                        <td><?php echo $profile['date_of_birth'] ?? 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Gender</th>
                                        <td><?php echo $profile['gender'] ?? 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Allergies</th>
                                        <td><?php echo $profile['allergy'] ?? 'None'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Chronic Diseases</th>
                                        <td><?php echo $profile['chronic_disease'] ?? 'None'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Emergency Contact</th>
                                        <td><?php echo $profile['emergency_contact'] ?? 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>City</th>
                                        <td><?php echo $profile['city'] ?? 'N/A'; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <?php if($user_type == 'Doctor'): ?>
                    <!-- Doctor Features -->
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-primary text-white" onclick="location.href='<?php echo $base; ?>/prescription/create.php'">
                            <i class="bi bi-file-medical" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Create Prescription</h4>
                            <p>Write digital prescriptions</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-success text-white" onclick="location.href='<?php echo $base; ?>/appointments/list.php'">
                            <i class="bi bi-calendar-check" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Appointments</h4>
                            <p>Manage patient appointments</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-warning text-dark" onclick="location.href='<?php echo $base; ?>/emergency/index.php'">
                            <i class="bi bi-exclamation-triangle" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Emergency</h4>
                            <p>Respond to emergencies</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-info text-white" onclick="location.href='<?php echo $base; ?>/patient/history.php'">
                            <i class="bi bi-people" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Patients</h4>
                            <p>View patient records</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-secondary text-white" onclick="location.href='<?php echo $base; ?>/pharmacy/index.php'">
                            <i class="bi bi-capsule" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Pharmacy</h4>
                            <p>Check medicine inventory</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Patient Features -->
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-primary text-white" onclick="location.href='<?php echo $base; ?>/appointments/book.php'">
                            <i class="bi bi-calendar-plus" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Book Appointment</h4>
                            <p>Schedule with doctors</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-danger text-white" onclick="location.href='<?php echo $base; ?>/emergency/index.php'">
                            <i class="bi bi-exclamation-triangle" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Emergency</h4>
                            <p>Request urgent help</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-success text-white" onclick="location.href='<?php echo $base; ?>/appointments/list.php'">
                            <i class="bi bi-list-check" style="font-size: 48px;"></i>
                            <h4 class="mt-3">My Appointments</h4>
                            <p>View your appointments</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-info text-white" onclick="location.href='<?php echo $base; ?>/patient/history.php'">
                            <i class="bi bi-file-medical" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Prescriptions</h4>
                            <p>View medical history</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-warning text-dark" onclick="location.href='<?php echo $base; ?>/bloodbank/index.php'">
                            <i class="bi bi-droplet" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Blood Bank</h4>
                            <p>Donate or request blood</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card feature-card bg-secondary text-white" onclick="location.href='<?php echo $base; ?>/pharmacy/index.php'">
                            <i class="bi bi-capsule" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Pharmacy</h4>
                            <p>Order medicines</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>