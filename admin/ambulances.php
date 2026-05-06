<?php
require_once('../includes/auth.php');
// Only Admin can manage ambulances
require_roles(['Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$msg = ""; $error = "";

// Handle Add Ambulance
if(isset($_POST['add_ambulance'])) {
    $driver = $_POST['driver_name'];
    $phone = $_POST['phone'];
    $vehicle = $_POST['vehicle_number'];
    $location = $_POST['location'];
    
    $id = rand(100, 999);
    $sql = "INSERT INTO AMBULANCE (ambulance_id, driver_name, phone, vehicle_number, current_location, status) 
            VALUES ($id, '$driver', '$phone', '$vehicle', '$location', 'Available')";
    
    if($conn->query($sql)) {
        $msg = "Ambulance added successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Handle Status Update
if(isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $conn->query("UPDATE AMBULANCE SET status = IF(status='Available', 'Busy', 'Available') WHERE ambulance_id = $id");
    header("Location: ambulances.php"); exit;
}

// Handle Delete
if(isset($_GET['delete'])) {
    $conn->query("DELETE FROM AMBULANCE WHERE ambulance_id = " . $_GET['delete']);
    header("Location: ambulances.php"); exit;
}

$ambulances = $conn->query("SELECT * FROM AMBULANCE ORDER BY ambulance_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ambulances - MediTrack BD Admin</title>
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
                <h2><i class="bi bi-ambulance"></i> Manage Ambulances</h2>
                
                <?php if($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="bi bi-plus-circle"></i> Add New Ambulance</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <input type="text" name="driver_name" class="form-control" placeholder="Driver Name" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <input type="tel" name="phone" class="form-control" placeholder="Phone" maxlength="11" pattern="[0-9]{11}" title="Enter 11 digit phone number">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <input type="text" name="vehicle_number" class="form-control" placeholder="Vehicle Number" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <input type="text" name="location" class="form-control" placeholder="Current Location">
                                </div>
                            </div>
                            <button type="submit" name="add_ambulance" class="btn btn-danger mt-2">Add Ambulance</button>
                        </form>
                    </div>
                </div>
                
                <h4>All Ambulances</h4>
                <table class="table table-hover bg-white rounded">
                    <thead class="table-dark">
                        <tr><th>ID</th><th>Driver</th><th>Phone</th><th>Vehicle</th><th>Location</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php while($a = $ambulances->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $a['ambulance_id']; ?></td>
                            <td><?php echo $a['driver_name']; ?></td>
                            <td><?php echo $a['phone']; ?></td>
                            <td><?php echo $a['vehicle_number']; ?></td>
                            <td><?php echo $a['current_location']; ?></td>
                            <td><span class="badge bg-<?php echo $a['status']=='Available'?'success':'warning'; ?>"><?php echo $a['status']; ?></span></td>
                            <td>
                                <a href="ambulances.php?toggle=<?php echo $a['ambulance_id']; ?>" class="btn btn-sm btn-warning"><?php echo $a['status']=='Available'?'Set Busy':'Set Available'; ?></a>
                                <a href="ambulances.php?delete=<?php echo $a['ambulance_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                            </td>
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