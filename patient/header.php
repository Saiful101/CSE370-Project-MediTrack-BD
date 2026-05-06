<?php
$base = "http://localhost/MediTrackBD";
?>
<nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="<?php echo $base; ?>/patient/dashboard.php">MediTrack<span>BD</span></a>
        <button class="navbar-toggler bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base; ?>/patient/dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base; ?>/prescription/create.php">Prescription</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base; ?>/appointments/list.php">Appointments</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base; ?>/patient/history.php">Patients</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base; ?>/pharmacy/index.php">Pharmacy</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base; ?>/bloodbank/index.php">Blood Bank</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="<?php echo $base; ?>/index.php?logout=1">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>