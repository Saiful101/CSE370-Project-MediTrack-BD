<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = "http://localhost/MediTrackBD";

// Check if user is authenticated
if (!isset($_SESSION['person_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: $base/index.php?error=Please login to access this page");
    exit;
}

// Determine user role
$user_role = '';
if (isset($_SESSION['admin_id'])) {
    $user_role = 'Admin';
} elseif (isset($_SESSION['person_id'])) {
    $user_role = $_SESSION['user_type'] ?? '';
}

// Function to restrict page access by role
function require_roles($allowed_roles) {
    global $user_role, $base;
    if (!in_array($user_role, $allowed_roles)) {
        if ($user_role === 'Admin') {
            $dashboard = $base . '/admin/dashboard.php';
        } elseif ($user_role === 'Doctor') {
            $dashboard = $base . '/patient/dashboard.php'; // Doctor dashboard is patient/dashboard.php
        } else {
            $dashboard = $base . '/patient/dashboard.php';
        }
        // Set error message in session
        $_SESSION['error_message'] = "Access Denied: You don't have permission to access this page";
        header("Location: $dashboard");
        exit;
    }
}

// Function to get current user ID
function get_user_id() {
    if (isset($_SESSION['admin_id'])) return $_SESSION['admin_id'];
    if (isset($_SESSION['person_id'])) return $_SESSION['person_id'];
    return 0;
}

// Role check helpers
function is_patient() { global $user_role; return $user_role === 'Patient'; }
function is_doctor() { global $user_role; return $user_role === 'Doctor'; }
function is_admin() { global $user_role; return $user_role === 'Admin'; }

// Display error message and clear it
function display_error() {
    if (isset($_SESSION['error_message'])) {
        $msg = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        return '<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle"></i> ' . $msg . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    return '';
}

$GLOBALS['user_role'] = $user_role;
?>
