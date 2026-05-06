<?php
require_once('../includes/auth.php');
// Only Admin can access analytics
require_roles(['Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$base = "http://localhost/MediTrackBD";

// Get filters
$start_date = $_POST['start_date'] ?? date('Y-01-01');
$end_date = $_POST['end_date'] ?? date('Y-m-d');
$filter_doctor = $_POST['filter_doctor'] ?? '';
$filter_department = $_POST['filter_department'] ?? '';

// Build WHERE conditions for doctor and department
$doctor_filter = $filter_doctor ? "AND d.person_id = $filter_doctor" : "";
$dept_filter = $filter_department ? "AND d.specialization = '$filter_department'" : "";

// ========== STATISTICS ==========
$total_patients = $conn->query("SELECT COUNT(*) FROM PERSON WHERE person_type = 'Patient'")->fetch_row()[0];
$total_doctors = $conn->query("SELECT COUNT(*) FROM PERSON WHERE person_type = 'Doctor'")->fetch_row()[0];
$total_appointments = $conn->query("SELECT COUNT(*) FROM APPOINTMENT a JOIN PERSON d ON a.person_id_doctor = d.person_id WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date' $doctor_filter $dept_filter")->fetch_row()[0];
$total_prescriptions = $conn->query("SELECT COUNT(*) FROM PRESCRIPTION p 
    JOIN PERSON d ON p.person_id_doctor = d.person_id 
    WHERE p.created_at BETWEEN '$start_date' AND '$end_date' $doctor_filter $dept_filter")->fetch_row()[0];
$total_donors = $conn->query("SELECT COUNT(*) FROM BLOOD_DONOR")->fetch_row()[0];
$total_medicines = $conn->query("SELECT COUNT(*) FROM MEDICINE")->fetch_row()[0];

// Get all doctors for filter dropdown
$all_doctors = $conn->query("SELECT * FROM PERSON WHERE person_type = 'Doctor' ORDER BY name");
// Get all departments for filter dropdown
$departments = $conn->query("SELECT DISTINCT specialization FROM PERSON WHERE person_type = 'Doctor' AND specialization IS NOT NULL ORDER BY specialization");

// ========== REVENUE ==========
// Calculate admin revenue from appointments (only 10% of doctor consultation fee)
// 90% belongs to doctor, 10% goes to system/admin
// Include Confirmed and Completed (and Pending as fallback) appointments
$revenue = $conn->query("SELECT SUM(COALESCE(d.consultation_fee, 0) * 0.10) as total
    FROM APPOINTMENT a
    JOIN PERSON d ON a.person_id_doctor = d.person_id
    WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date'
    AND a.status IN ('Pending', 'Confirmed', 'Completed')")->fetch_assoc();
$appointment_revenue = floatval($revenue['total'] ?? 0);

// If no revenue from above, try any appointments with non-zero fee
if ($appointment_revenue == 0) {
    $revenue2 = $conn->query("SELECT SUM(COALESCE(d.consultation_fee, 0) * 0.10) as total
        FROM APPOINTMENT a
        JOIN PERSON d ON a.person_id_doctor = d.person_id
        WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc();
    $appointment_revenue = floatval($revenue2['total'] ?? 0);
}

// Calculate revenue from pharmacy sales
$sales = $conn->query("SELECT SUM(ps.total_price) as total
    FROM PHARMACY_SALES ps
    JOIN PRESCRIPTION pr ON ps.prescription_id = pr.prescription_id
    WHERE ps.sale_date BETWEEN '$start_date' AND '$end_date'");
$sales_revenue = $sales->fetch_assoc()['total'] ?? 0;
$total_revenue = floatval($appointment_revenue) + floatval($sales_revenue);

// ========== DISEASE TRENDS (from diagnoses) ==========
$disease_trends = $conn->query("SELECT p.diagnosis, COUNT(*) as count
    FROM PRESCRIPTION p
    WHERE p.created_at BETWEEN '$start_date' AND '$end_date'
    AND p.diagnosis IS NOT NULL
    GROUP BY p.diagnosis
    ORDER BY count DESC
    LIMIT 10");

// ========== DOCTOR-WISE PATIENT COUNT ==========
$doctor_stats = $conn->query("SELECT d.name, d.specialization, COUNT(a.appointment_id) as patient_count
    FROM PERSON d
    LEFT JOIN APPOINTMENT a ON d.person_id = a.person_id_doctor
        AND a.appointment_date BETWEEN '$start_date' AND '$end_date'
    WHERE d.person_type = 'Doctor'
    GROUP BY d.person_id
    ORDER BY patient_count DESC");

// ========== PHARMACY REPORTS ==========
$medicine_sales = $conn->query("SELECT m.name, m.category, SUM(ps.quantity_sold) as total_sold, SUM(ps.total_price) as revenue
    FROM PHARMACY_SALES ps
    JOIN MEDICINE m ON ps.medicine_id = m.medicine_id
    WHERE ps.sale_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY m.medicine_id
    ORDER BY total_sold DESC
    LIMIT 10");

$total_medicine_sold = $conn->query("SELECT SUM(quantity_sold) FROM PHARMACY_SALES WHERE sale_date BETWEEN '$start_date' AND '$end_date'")->fetch_row()[0];

// ========== MONTHLY BLOOD DONATION STATS ==========
$monthly_donations = $conn->query("SELECT DATE_FORMAT(last_donation_date, '%Y-%m') as month, COUNT(*) as count
    FROM BLOOD_DONOR
    WHERE last_donation_date IS NOT NULL
    AND last_donation_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY month
    ORDER BY month DESC");

// ========== MONTHLY APPOINTMENTS ==========
$monthly_appointments = $conn->query("SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month, COUNT(*) as count
    FROM APPOINTMENT
    WHERE appointment_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY month
    ORDER BY month DESC");

// ========== APPOINTMENTS BY DEPARTMENT ==========
$dept_stats = $conn->query("SELECT d.specialization, COUNT(a.appointment_id) as count
    FROM APPOINTMENT a
    JOIN PERSON d ON a.person_id_doctor = d.person_id
    WHERE a.appointment_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY d.specialization
    ORDER BY count DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Analytics - MediTrack BD</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f0f4f8; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .nav-link { color: white; padding: 12px 20px; border-radius: 10px; margin: 5px 0; display: block; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); text-decoration: none; color: white; }
        .stat-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .stat-icon { font-size: 40px; opacity: 0.8; }
        .chart-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once('../includes/sidebar.php'); ?>
            <div class="col-md-10 p-4">
                <h2><i class="bi bi-graph-up"></i> Health Analytics & Reports Dashboard</h2>

                <!-- Date Filter -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Reports</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-2">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-2">
                                <label>End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label>Doctor</label>
                                <select name="filter_doctor" class="form-select">
                                    <option value="">All Doctors</option>
                                    <?php while($doc = $all_doctors->fetch_assoc()): ?>
                                    <option value="<?php echo $doc['person_id']; ?>" <?php echo $filter_doctor == $doc['person_id'] ? 'selected' : ''; ?>>
                                        <?php echo $doc['name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Department</label>
                                <select name="filter_department" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php while($dept = $departments->fetch_assoc()): ?>
                                    <option value="<?php echo $dept['specialization']; ?>" <?php echo $filter_department == $dept['specialization'] ? 'selected' : ''; ?>>
                                        <?php echo $dept['specialization']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Overall Statistics -->
                <h4 class="mb-3"><i class="bi bi-pie-chart"></i> System Overview</h4>
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="bi bi-people stat-icon text-primary"></i>
                            <h3><?php echo $total_patients; ?></h3>
                            <p class="text-muted mb-0">Total Patients</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="bi bi-person-badge stat-icon text-success"></i>
                            <h3><?php echo $total_doctors; ?></h3>
                            <p class="text-muted mb-0">Total Doctors</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="bi bi-calendar-check stat-icon text-info"></i>
                            <h3><?php echo $total_appointments; ?></h3>
                            <p class="text-muted mb-0">Appointments</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="bi bi-file-medical stat-icon text-warning"></i>
                            <h3><?php echo $total_prescriptions; ?></h3>
                            <p class="text-muted mb-0">Prescriptions</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="bi bi-capsule stat-icon text-danger"></i>
                            <h3><?php echo $total_medicine_sold ?? 0; ?></h3>
                            <p class="text-muted mb-0">Meds Sold</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card text-center">
                            <i class="bi bi-droplet stat-icon text-secondary"></i>
                            <h3><?php echo $total_donors; ?></h3>
                            <p class="text-muted mb-0">Blood Donors</p>
                        </div>
                    </div>
                </div>

                <!-- Revenue Section -->
                <h4 class="mb-3"><i class="bi bi-currency-dollar"></i> Revenue Report</h4>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card bg-primary text-white">
                            <h2>৳<?php echo number_format($total_revenue, 2); ?></h2>
                            <p class="mb-0"><i class="bi bi-wallet"></i> Total Revenue</p>
                            <small>From <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h4 class="text-success">৳<?php echo number_format($appointment_revenue, 2); ?></h4>
                            <p class="mb-0 text-muted"><i class="bi bi-calendar"></i> Appointments Revenue</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h4 class="text-warning">৳<?php echo number_format($sales_revenue, 2); ?></h4>
                            <p class="mb-0 text-muted"><i class="bi bi-shop"></i> Pharmacy Revenue</p>
                        </div>
                    </div>
                </div>

                <!-- Disease Trends -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5><i class="bi bi-clipboard-pulse"></i> Disease Diagnosis Trends</h5>
                            <p class="text-muted">Most frequently diagnosed conditions</p>
                            <?php if($disease_trends->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr><th>Diagnosis</th><th>Count</th><th>Bar</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $max_count = 1;
                                        while($d = $disease_trends->fetch_assoc()) {
                                            $max_count = max($max_count, $d['count']);
                                        }
                                        $disease_trends->data_seek(0);
                                        while($d = $disease_trends->fetch_assoc()):
                                            $percentage = ($d['count'] / $max_count) * 100;
                                        ?>
                                        <tr>
                                            <td><?php echo $d['diagnosis']; ?></td>
                                            <td><strong><?php echo $d['count']; ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-danger" style="width: <?php echo $percentage; ?>%"><?php echo $d['count']; ?></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">No diagnosis data in selected date range.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Doctor-wise Patient Count -->
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5><i class="bi bi-person-check"></i> Doctor-wise Patient Count</h5>
                            <p class="text-muted">Patients seen by each doctor</p>
                            <?php if($doctor_stats->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Doctor</th><th>Specialization</th><th>Patients Seen</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php while($doc = $doctor_stats->fetch_assoc()): ?>
                                        <tr>
                                            <td><i class="bi bi-person-circle"></i> <?php echo $doc['name']; ?></td>
                                            <td><span class="badge bg-info"><?php echo $doc['specialization'] ?? 'General'; ?></span></td>
                                            <td><h4 class="mb-0"><span class="badge bg-success"><?php echo $doc['patient_count']; ?></span></h4></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">No appointment data in selected date range.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Pharmacy Reports -->
                <h4 class="mb-3"><i class="bi bi-capsule"></i> Pharmacy Reports</h4>
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="chart-card">
                            <h5><i class="bi bi-trophy"></i> Most Sold Medicines</h5>
                            <?php if($medicine_sales->num_rows > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr><th>Medicine</th><th>Category</th><th>Quantity Sold</th><th>Revenue</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($med = $medicine_sales->fetch_assoc()): ?>
                                    <tr>
                                        <td><i class="bi bi-capsule text-primary"></i> <?php echo $med['name']; ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $med['category'] ?? 'General'; ?></span></td>
                                        <td><strong><?php echo $med['total_sold']; ?></strong></td>
                                        <td>৳<?php echo number_format($med['revenue'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="alert alert-info">No pharmacy sales in selected date range.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-card text-center">
                            <h5><i class="bi bi-box-seam" style="font-size: 50px;"></i></h5>
                            <h2><?php echo $total_medicine_sold ?? 0; ?></h2>
                            <p class="text-muted">Total Medicines Sold</p>
                            <hr>
                            <p class="mb-0"><i class="bi bi-boxes"></i> <?php echo $total_medicines; ?> Unique Products</p>
                        </div>
                    </div>
                </div>

                <!-- Monthly Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5><i class="bi bi-calendar-month"></i> Monthly Appointments</h5>
                            <?php if($monthly_appointments->num_rows > 0): ?>
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Month</th><th>Appointments</th><th>Trend</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($m = $monthly_appointments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $m['month']; ?></td>
                                        <td><strong><?php echo $m['count']; ?></strong></td>
                                        <td>
                                            <?php
                                            $bar_width = min($m['count'] * 10, 100);
                                            echo "<div class='progress' style='height:15px;'><div class='progress-bar bg-primary' style='width:$bar_width%'>" . $m['count'] . "</div></div>";
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="alert alert-info">No appointments in selected date range.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-card">
                            <h5><i class="bi bi-droplet"></i> Monthly Blood Donations</h5>
                            <?php if($monthly_donations->num_rows > 0): ?>
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Month</th><th>Donations</th><th>Trend</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($md = $monthly_donations->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $md['month']; ?></td>
                                        <td><strong><?php echo $md['count']; ?></strong></td>
                                        <td>
                                            <?php
                                            $bar_width = min($md['count'] * 15, 100);
                                            echo "<div class='progress' style='height:15px;'><div class='progress-bar bg-danger' style='width:$bar_width%'>" . $md['count'] . "</div></div>";
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="alert alert-info">No donation records in selected date range.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Department-wise Stats -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="chart-card">
                            <h5><i class="bi bi-building"></i> Department-wise Appointment Statistics</h5>
                            <?php if($dept_stats->num_rows > 0): ?>
                            <div class="row">
                                <?php while($dept = $dept_stats->fetch_assoc()): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card border-primary">
                                        <div class="card-body text-center">
                                            <h3><?php echo $dept['count']; ?></h3>
                                            <p class="mb-0"><?php echo $dept['specialization'] ?? 'General'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">No department data in selected date range.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>