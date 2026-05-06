<?php
session_start();

$base = "http://localhost/MediTrackBD";

// Logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: $base/index.php");
    exit;
}

// Already logged in?
if(isset($_SESSION['person_id']) || isset($_SESSION['admin_id'])) {
    if(isset($_SESSION['admin_id'])) {
        header("Location: $base/admin/dashboard.php");
    } else {
        header("Location: $base/patient/dashboard.php");
    }
    exit;
}

$error = "";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "", "meditrack_bd");
    $conn->set_charset("utf8mb4");

    $email = $_POST['email'];
    $password = $_POST['password'];

    // First try Patient/Doctor login
    $stmt = $conn->prepare("SELECT p.person_id, p.name, p.person_type
        FROM PERSON p INNER JOIN EMAIL e ON p.person_id = e.person_id
        WHERE e.email = ? AND p.login_password = ?");
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['person_id'] = $row['person_id'];
        $_SESSION['name'] = $row['name'];
        $_SESSION['user_type'] = $row['person_type'];
        $_SESSION['login_success'] = true;
        header("Location: $base/patient/dashboard.php");
        exit;
    } else {
        // Then try Admin login
        $stmt2 = $conn->prepare("SELECT admin_id, name FROM ADMIN WHERE email = ? AND password = ?");
        $stmt2->bind_param("ss", $email, $password);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if($result2 && $result2->num_rows > 0) {
            $row2 = $result2->fetch_assoc();
            $_SESSION['admin_id'] = $row2['admin_id'];
            $_SESSION['name'] = $row2['name'];
            $_SESSION['user_type'] = 'Admin';
            $_SESSION['login_success'] = true;
            header("Location: $base/admin/dashboard.php");
            exit;
        }
        $error = "Invalid email or password!";
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>MediTrack BD - Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: white; border-radius: 20px; padding: 40px; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
    </style>
</head>
<body>
    <div class="card">
        <h3 class="text-center mb-3" style="color: #667eea;">MediTrack<strong style="color: #764ba2;">BD</strong></h3>
        <p class="text-center text-muted mb-4">Integrated Healthcare Management</p>
        <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="text-center mt-3">
            <a href="register.php" class="text-muted">Create New Account</a>
        </div>
    </div>
</body>
</html>
