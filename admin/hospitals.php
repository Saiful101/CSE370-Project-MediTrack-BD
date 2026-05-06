<?php
require_once('../includes/auth.php');
// Only Admin can manage hospitals
require_roles(['Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$msg = ""; $error = "";

// Handle Add Hospital
if(isset($_POST['add_hospital'])) {
    $name = $_POST['name'];
    $location = $_POST['location'];
    $phone = $_POST['phone'];
    $type = $_POST['type'];
    $total_beds = $_POST['total_beds'];
    $city = $_POST['city'];
    $admin_id = $_SESSION['admin_id'];
    
    $sql = "INSERT INTO HOSPITAL (name_of_hospital, location, phone, type_of_hospital, total_beds, available_beds, city, admin_id) 
            VALUES ('$name', '$location', '$phone', '$type', $total_beds, $total_beds, '$city', $admin_id)";
    
    if($conn->query($sql)) {
        $msg = "Hospital added successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Handle Delete
if(isset($_GET['delete'])) {
    $conn->query("DELETE FROM HOSPITAL WHERE hospital_id = " . $_GET['delete']);
    header("Location: hospitals.php"); exit;
}

$hospitals = $conn->query("SELECT h.*, a.name as admin_name FROM HOSPITAL h LEFT JOIN ADMIN a ON h.admin_id = a.admin_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospitals - MediTrack BD Admin</title>
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
                <h2><i class="bi bi-hospital"></i> Manage Hospitals</h2>
        
        <?php if($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5><i class="bi bi-plus-circle"></i> Add New Hospital</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <input type="text" name="name" class="form-control" placeholder="Hospital Name" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" name="location" class="form-control" placeholder="Location">
                        </div>
                        <div class="col-md-2 mb-2">
                            <input type="tel" name="phone" class="form-control" placeholder="Phone" maxlength="11" pattern="[0-9]{11}" title="Enter phone number">
                        </div>
                        <div class="col-md-2 mb-2">
                            <select name="type" class="form-select">
                                <option value="Private">Private</option>
                                <option value="Government">Government</option>
                                <option value="Clinic">Clinic</option>
                            </select>
                        </div>
                        <div class="col-md-1 mb-2">
                            <input type="number" name="total_beds" class="form-control" placeholder="Beds">
                        </div>
                    </div>
                    <button type="submit" name="add_hospital" class="btn btn-primary">Add Hospital</button>
                </form>
            </div>
        </div>
        
        <table class="table table-hover bg-white rounded">
            <thead class="table-dark">
                <tr><th>ID</th><th>Name</th><th>Type</th><th>Location</th><th>Beds</th><th>Admin</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php while($h = $hospitals->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $h['hospital_id']; ?></td>
                    <td><?php echo $h['name_of_hospital']; ?></td>
                    <td><span class="badge bg-info"><?php echo $h['type_of_hospital']; ?></span></td>
                    <td><?php echo $h['location']; ?></td>
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