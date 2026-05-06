<?php
require_once('../includes/auth.php');
// Patient and Admin can access blood bank (Doctor cannot based on requirements)
require_roles(['Patient', 'Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$base = "http://localhost/MediTrackBD";
$is_admin = is_admin();
$is_patient = is_patient();

// Doctors should not see blood bank in sidebar (handled in sidebar.php)
// Only patients can register as donors and search
// Only admins can see all donors and manage them

$msg = "";
$error = "";

// Handle marking donation completed
if(isset($_POST['mark_donated'])) {
    $donor_id = $_POST['update_donor_id'];
    $conn->query("UPDATE BLOOD_DONOR SET last_donation_date = CURDATE(), is_eligible = FALSE WHERE donor_id = $donor_id");
    $msg = "Donation recorded! Donor marked as ineligible for next 3 months.";
}

// Handle registration
if(isset($_POST['register_donor'])) {
    $name = $_POST['name'];
    $blood_group = $_POST['blood_group'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $person_id = $_SESSION['person_id'] ?? 0;

    // Check if already registered as donor
    $check = $conn->query("SELECT * FROM BLOOD_DONOR WHERE person_id = $person_id");
    if($check->num_rows > 0) {
        $error = "You are already registered as a blood donor!";
    } else {
        $donor_id = rand(100, 999);
        if($conn->query("INSERT INTO BLOOD_DONOR VALUES ($donor_id, '$name', '$blood_group', '$phone', '$address', NULL, TRUE, $person_id)")) {
            $msg = "Registered as blood donor successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Handle search
$search_results = null;
if(isset($_POST['search'])) {
    $blood_group = $_POST['search_blood_group'];
    // Only show eligible donors (last donation was 3+ months ago OR never donated)
    $search_results = $conn->query("SELECT * FROM BLOOD_DONOR
        WHERE blood_group = '$blood_group'
        AND (last_donation_date IS NULL OR last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 90 DAY))");
}

// Get donor's own history
$my_history = null;
if(isset($_SESSION['person_id'])) {
    $my_id = $_SESSION['person_id'];
    $my_history = $conn->query("SELECT * FROM BLOOD_DONOR WHERE person_id = $my_id");
    $my_donations = $conn->query("SELECT * FROM BLOOD_DONOR WHERE person_id = $my_id");
}

// Get all donors for admin
$all_donors = $conn->query("SELECT * FROM BLOOD_DONOR ORDER BY blood_group, name");

// Get statistics
$total_donors = $conn->query("SELECT COUNT(*) FROM BLOOD_DONOR")->fetch_row()[0]; 
$eligible_donors = $conn->query("SELECT COUNT(*) FROM BLOOD_DONOR WHERE is_eligible = TRUE")->fetch_row()[0];

$blood_stats = [];
foreach(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg) {
    $count = $conn->query("SELECT COUNT(*) FROM BLOOD_DONOR WHERE blood_group = '$bg' AND is_eligible = TRUE")->fetch_row()[0];
    $blood_stats[$bg] = $count;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank - MediTrack BD</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .nav-link { color: white; padding: 12px 20px; border-radius: 10px; margin: 5px 0; display: block; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); text-decoration: none; color: white; }
        .feature-card { border-radius: 15px; padding: 20px; text-align: center; }
        .blood-stock-container { max-width: 800px; margin: 0 auto; }
        .blood-card { padding: 10px 5px; text-align: center; border-radius: 8px; }
        .blood-card h5 { font-size: 14px; margin: 0; font-weight: bold; }
        .blood-card p { font-size: 11px; margin: 0; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once('../includes/sidebar.php'); ?>
            <div class="col-md-10 p-4">
                <h2><i class="bi bi-droplet"></i> Blood Bank & Donor Management</h2>
                
                <?php if($msg): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $msg; ?></div>
                <?php endif; ?>
                <?php if($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Blood Statistics -->
                <h5 class="mt-4 mb-3"><i class="bi bi-bar-chart"></i> Available Blood Stock</h5>
                <div class="blood-stock-container">
                    <div class="row g-2 justify-content-center">
                        <?php foreach($blood_stats as $group => $count): ?>
                        <div class="col-3 col-sm-2 col-md-2">
                            <div class="card blood-card bg-<?php echo ($count > 0 ? 'danger' : 'secondary'); ?> text-white">
                                <h5><?php echo $group; ?></h5>
                                <p><?php echo $count; ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="row">
                    <!-- Register as Donor (Patient only, since Doctor can't access this page) -->
                    <?php if(!$is_admin): ?>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5><i class="bi bi-person-plus"></i> Register as Blood Donor</h5>
                            </div>
                            <div class="card-body">
                                <?php if($my_donations && $my_donations->num_rows > 0): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> You are already registered as a donor!
                                </div>
                                <h6>Your Donation History:</h6>
                                <?php while($don = $my_donations->fetch_assoc()): ?>
                                <p><strong>Blood Group:</strong> <?php echo $don['blood_group']; ?></p>
                                <p><strong>Last Donation:</strong> <?php echo $don['last_donation_date'] ?? 'Never donated'; ?></p>
                                <p><strong>Status:</strong>
                                    <span class="badge bg-<?php echo $don['is_eligible'] ? 'success' : 'warning'; ?>">
                                        <?php echo $don['is_eligible'] ? 'Eligible to donate' : 'Not eligible yet'; ?>
                                    </span>
                                </p>
                                <?php if($don['last_donation_date']): ?>
                                <p><small class="text-muted">Next donation date: <?php echo date('Y-m-d', strtotime($don['last_donation_date'] . ' + 90 days')); ?></small></p>
                                <?php endif; ?>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label>Name</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo $_SESSION['name'] ?? ''; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Blood Group</label>
                                        <select name="blood_group" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="A+">A+</option>
                                            <option value="A-">A-</option>
                                            <option value="B+">B+</option>
                                            <option value="B-">B-</option>
                                            <option value="O+">O+</option>
                                            <option value="O-">O-</option>
                                            <option value="AB+">AB+</option>
                                            <option value="AB-">AB-</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label>Phone</label>
                                        <input type="tel" name="phone" class="form-control" pattern="[0-9]{11}" maxlength="11" required placeholder="01712345678">
                                    </div>
                                    <div class="mb-3">
                                        <label>Address</label>
                                        <textarea name="address" class="form-control" rows="2" required placeholder="Your current address"></textarea>
                                    </div>
                                    <button type="submit" name="register_donor" class="btn btn-danger w-100">
                                        <i class="bi bi-heart"></i> Register as Donor
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Search Blood Donors -->
                    <div class="<?php echo ($is_admin || $is_doctor) ? 'col-md-12' : 'col-md-8'; ?>">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5><i class="bi bi-search"></i> Search Blood Donors</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-3">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <select name="search_blood_group" class="form-select form-select-lg" required>
                                                <option value="">Select Blood Group to Search</option>
                                                <option value="A+">A+</option>
                                                <option value="A-">A-</option>
                                                <option value="B+">B+</option>
                                                <option value="B-">B-</option>
                                                <option value="O+">O+</option>
                                                <option value="O-">O-</option>
                                                <option value="AB+">AB+</option>
                                                <option value="AB-">AB-</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" name="search" class="btn btn-primary btn-lg w-100">
                                                <i class="bi bi-search"></i> Search
                                            </button>
                                        </div>
                                    </div>
                                </form>

                                <?php if($search_results): ?>
                                <h5>Eligible Donors for Blood Group: <span class="badge bg-danger"><?php echo $_POST['search_blood_group']; ?></span></h5>
                                <p class="text-muted"><small><i class="bi bi-info-circle"></i> Only showing donors eligible to donate (last donation was 3+ months ago)</small></p>

                                <?php if($search_results->num_rows > 0): ?>
                                <table class="table table-hover">
                                    <thead class="table-danger">
                                        <tr>
                                            <th>Name</th>
                                            <th>Blood Group</th>
                                            <th>Phone</th>
                                            <th>Address</th>
                                            <th>Last Donation</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($donor = $search_results->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo $donor['name']; ?></strong></td>
                                            <td><span class="badge bg-danger"><?php echo $donor['blood_group']; ?></span></td>
                                            <td><i class="bi bi-phone"></i> <?php echo $donor['phone']; ?></td>
                                            <td><?php echo $donor['address']; ?></td>
                                            <td><?php echo $donor['last_donation_date'] ?? 'Never'; ?></td>
                                            <td><span class="badge bg-success"><i class="bi bi-check-circle"></i> Eligible</span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i> Found <?php echo $search_results->num_rows; ?> eligible donor(s). Contact them directly for blood donation.
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> No eligible donors found for this blood group at the moment.
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if($is_admin): ?>
                        <!-- All Donors Table for Admin -->
                        <div class="card mt-4">
                            <div class="card-header bg-dark text-white">
                                <h5><i class="bi bi-list"></i> All Registered Donors</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Blood Group</th>
                                            <th>Phone</th>
                                            <th>Address</th>
                                            <th>Last Donation</th>
                                            <th>Eligible</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($d = $all_donors->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $d['donor_id']; ?></td>
                                            <td><?php echo $d['name']; ?></td>
                                            <td><span class="badge bg-danger"><?php echo $d['blood_group']; ?></span></td>
                                            <td><?php echo $d['phone']; ?></td>
                                            <td><?php echo $d['address']; ?></td>
                                            <td><?php echo $d['last_donation_date'] ?? 'Never'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $d['is_eligible'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $d['is_eligible'] ? 'Yes' : 'No'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if(isset($_GET['update_donation']) && $_GET['update_donation'] == $d['donor_id']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="update_donor_id" value="<?php echo $d['donor_id']; ?>">
                                                    <button type="submit" name="mark_donated" class="btn btn-sm btn-success">Confirm Donation</button>
                                                </form>
                                                <?php else: ?>
                                                <a href="?update_donation=<?php echo $d['donor_id']; ?>" class="btn btn-sm btn-primary">Mark Donation</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
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