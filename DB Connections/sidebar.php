<?php
$base = "http://localhost/MediTrackBD";
$is_admin = isset($_SESSION['admin_id']);
$is_doctor = ($_SESSION['user_type'] ?? '') === 'Doctor';
$is_patient = ($_SESSION['user_type'] ?? '') === 'Patient';
$person_id = $_SESSION['person_id'] ?? 0;
$name = $_SESSION['name'] ?? 'User';
$user_role = $is_admin ? 'Admin' : ($_SESSION['user_type'] ?? 'Guest');

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>

<div class="col-md-2 sidebar p-3 min-vh-100">
    <h4 class="text-white text-center mb-4">MediTrack<span>BD</span></h4>
    <p class="text-white text-center small"><?php echo $name; ?> (<?php echo $user_role; ?>)</p>
    <ul class="nav flex-column">
        <!-- Dashboard for all -->
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_page == 'dashboard.php') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $is_admin ? $base.'/admin/dashboard.php' : $base.'/patient/dashboard.php'; ?>">
               <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>

        <?php if($is_admin): ?>
        <!-- ADMIN ONLY: Analytics, Pharmacy, Hospitals, Users, etc. -->
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'analytics') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/analytics/index.php">
               <i class="bi bi-graph-up"></i> Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'pharmacy') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/pharmacy/index.php">
               <i class="bi bi-capsule"></i> Pharmacy
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'appointments') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/appointments/list.php">
               <i class="bi bi-calendar-check"></i> Appointments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'prescription') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/prescription/create.php">
               <i class="bi bi-file-medical"></i> Prescriptions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_page == 'history.php') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/patient/history.php">
               <i class="bi bi-people"></i> Patients
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'emergency') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/emergency/index.php">
               <i class="bi bi-exclamation-triangle"></i> Emergency
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'bloodbank') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/bloodbank/index.php">
               <i class="bi bi-droplet"></i> Blood Bank
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_page == 'hospitals.php') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/admin/hospitals.php">
               <i class="bi bi-hospital"></i> Hospitals
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_page == 'ambulances.php') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/admin/ambulances.php">
               <i class="bi bi-ambulance"></i> Ambulances
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_page == 'users.php') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/admin/users.php">
               <i class="bi bi-person-badge"></i> Users
            </a>
        </li>

        <?php elseif($is_doctor): ?>
        <!-- DOCTOR ONLY: Appointments, Prescriptions, Patient History, Emergency -->
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'appointments') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/appointments/list.php">
               <i class="bi bi-calendar-check"></i> My Appointments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'prescription') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/prescription/create.php">
               <i class="bi bi-file-medical"></i> Write Prescription
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_page == 'history.php') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/patient/history.php">
               <i class="bi bi-people"></i> Patient History
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'emergency') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/emergency/index.php">
               <i class="bi bi-exclamation-triangle"></i> Emergency
            </a>
        </li>

        <?php elseif($is_patient): ?>
        <!-- PATIENT ONLY: Book Appointment, My Appointments, Prescriptions, Emergency, Blood Bank, Pharmacy -->
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'appointments' && $current_page == 'book.php') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/appointments/book.php">
               <i class="bi bi-calendar-plus"></i> Book Appointment
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'emergency') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/emergency/index.php">
               <i class="bi bi-exclamation-triangle"></i> Emergency
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'appointments' && $current_page == 'list.php') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/appointments/list.php">
               <i class="bi bi-list-check"></i> My Appointments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_page == 'history.php') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/patient/history.php">
               <i class="bi bi-file-medical"></i> Prescriptions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'bloodbank') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/bloodbank/index.php">
               <i class="bi bi-droplet"></i> Blood Bank
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-white <?php echo ($current_dir == 'pharmacy') ? 'active fw-bold bg-dark bg-opacity-25' : ''; ?>" 
               href="<?php echo $base; ?>/pharmacy/index.php">
               <i class="bi bi-capsule"></i> Pharmacy
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-3">
            <a class="nav-link text-white" href="<?php echo $base; ?>/index.php?logout=1">
               <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </li>
    </ul>
</div>
