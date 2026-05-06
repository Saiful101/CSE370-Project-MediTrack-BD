<?php
require_once('../includes/auth.php');
// Only Admin can manage users
require_roles(['Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$msg = ""; $error = "";

// Handle Delete User
if(isset($_GET['delete'])) {
    $conn->query("DELETE FROM PERSON WHERE person_id = " . $_GET['delete']);
    header("Location: users.php"); exit;
}

// Handle Delete Admin
if(isset($_GET['delete_admin'])) {
    $conn->query("DELETE FROM ADMIN WHERE admin_id = " . $_GET['delete_admin']);
    header("Location: users.php"); exit;
}

// Handle Add Admin
if(isset($_POST['add_admin'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    if($conn->query("INSERT INTO ADMIN (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')")) {
        $msg = "Admin added successfully!";
    } else {
        $error = "Email already exists!";
    }
}

$patients = $conn->query("SELECT * FROM PERSON WHERE person_type = 'Patient' ORDER BY name");
$doctors = $conn->query("SELECT * FROM PERSON WHERE person_type = 'Doctor' ORDER BY name");
$admins = $conn->query("SELECT * FROM ADMIN ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - MediTrack BD Admin</title>
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
                <h2><i class="bi bi-people"></i> Manage Users</h2>
                
                <?php if($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5><i class="bi bi-person-plus"></i> Add New Admin</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <input type="text" name="name" class="form-control" placeholder="Name" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <select name="role" class="form-select">
                                        <option value="Manager">Manager</option>
                                        <option value="SuperAdmin">SuperAdmin</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <button type="submit" name="add_admin" class="btn btn-success">Add Admin</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <h4>All Patients</h4>
                <table class="table table-hover bg-white rounded">
                    <thead class="table-dark">
                        <tr><th>ID</th><th>Name</th><th>Type</th><th>Blood</th><th>Allergies</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php while($p = $patients->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $p['person_id']; ?></td>
                            <td><strong><?php echo $p['name']; ?></strong></td>
                            <td><span class="badge bg-info"><?php echo $p['person_type']; ?></span></td>
                            <td><span class="badge bg-danger"><?php echo $p['blood_group'] ?? 'N/A'; ?></span></td>
                            <td><?php echo $p['allergy'] ?? 'None'; ?></td>
                            <td><a href="users.php?delete=<?php echo $p['person_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <h4 class="mt-4">All Doctors</h4>
                <table class="table table-hover bg-white rounded">
                    <thead class="table-dark">
                        <tr><th>ID</th><th>Name</th><th>Specialization</th><th>Fee</th><th>Experience</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php while($d = $doctors->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $d['person_id']; ?></td>
                            <td><strong>Dr. <?php echo $d['name']; ?></strong></td>
                            <td><?php echo $d['specialization']; ?></td>
                            <td>৳<?php echo $d['consultation_fee']; ?></td>
                            <td><?php echo $d['experience_year']; ?> Years</td>
                            <td><a href="users.php?delete=<?php echo $d['person_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <h4 class="mt-4">All Admins</h4>
                <table class="table table-hover bg-white rounded">
                    <thead class="table-dark">
                        <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php while($a = $admins->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $a['admin_id']; ?></td>
                            <td><strong><?php echo $a['name']; ?></strong></td>
                            <td><?php echo $a['email']; ?></td>
                            <td><span class="badge bg-primary"><?php echo $a['role']; ?></span></td>
                            <td><a href="users.php?delete_admin=<?php echo $a['admin_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a></td>
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