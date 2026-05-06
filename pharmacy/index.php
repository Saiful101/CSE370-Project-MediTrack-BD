<?php
require_once('../includes/auth.php');
// All roles can access pharmacy (with different permissions)
require_roles(['Patient', 'Doctor', 'Admin']);
$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$base = "http://localhost/MediTrackBD";
$is_admin = is_admin();
$is_doctor = is_doctor();
$is_patient = is_patient();

$msg = ""; $error = "";

// Handle Edit Stock
if(isset($_POST['update_stock'])) {
    $medicine_id = intval($_POST['medicine_id']);
    $new_stock = intval($_POST['new_stock']);

    if($new_stock < 0) {
        $error = "Stock must be a positive number!";
    } else {
        $conn->query("UPDATE MEDICINE SET stock_quantity = $new_stock WHERE medicine_id = $medicine_id");
        $msg = "Stock updated successfully!";
    }
}

// ========== ANALYTICS MINI CARDS ==========
// Total Sales Today
$sales_today = $conn->query("SELECT COUNT(*) as count, SUM(total_price) as revenue 
    FROM PHARMACY_SALES 
    WHERE DATE(sale_date) = CURDATE()")->fetch_assoc();
$total_sales_today = $sales_today['count'] ?? 0;
$revenue_today = $sales_today['revenue'] ?? 0;

// Most Sold Medicine Today
$most_sold = $conn->query("SELECT m.name, SUM(ps.quantity_sold) as total_sold 
    FROM PHARMACY_SALES ps 
    JOIN MEDICINE m ON ps.medicine_id = m.medicine_id 
    WHERE DATE(ps.sale_date) = CURDATE() 
    GROUP BY ps.medicine_id 
    ORDER BY total_sold DESC LIMIT 1")->fetch_assoc();
$most_sold_name = $most_sold['name'] ?? 'N/A';
$most_sold_qty = $most_sold['total_sold'] ?? 0;

// Low Stock Count
$low_stock_count = $conn->query("SELECT COUNT(*) FROM MEDICINE WHERE stock_quantity <= reorder_level")->fetch_row()[0];

// ========== SEARCH & FILTER ==========
$search_name = $_GET['search_name'] ?? '';
$search_doctor = $_GET['search_doctor'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build search query
$search_condition = "";
if($search_name) {
    $search_condition .= " AND pat.name LIKE '%$search_name%'";
}
if($search_doctor) {
    $search_condition .= " AND d.name LIKE '%$search_doctor%'";
}
if($date_from && $date_to) {
    $search_condition .= " AND DATE(p.created_at) BETWEEN '$date_from' AND '$date_to'";
}

// Handle fulfill prescription (pharmacy fills it)
if(isset($_POST['fulfill'])) {
    $prescription_id = $_POST['prescription_id'];
    
    $contains = $conn->query("SELECT c.medicine_id, c.daily_frequency, c.duration, m.price, m.stock_quantity, m.name, m.reorder_level
        FROM CONTAINS c 
        JOIN MEDICINE m ON c.medicine_id = m.medicine_id 
        WHERE c.prescription_id = $prescription_id");
    
    if($contains->num_rows > 0) {
        $all_ok = true;
        while($med = $contains->fetch_assoc()) {
            $med_id = $med['medicine_id'];
            $price = $med['price'];
            
            // Calculate quantity needed: extract number from daily_frequency and duration
            preg_match('/\d+/', $med['daily_frequency'], $freq_match);
            preg_match('/\d+/', $med['duration'], $dur_match);
            $frequency = isset($freq_match[0]) ? intval($freq_match[0]) : 1;
            $duration_days = isset($dur_match[0]) ? intval($dur_match[0]) : 1;
            $quantity_needed = $frequency * $duration_days;
            
            $total = $price * $quantity_needed;
            $new_stock = $med['stock_quantity'] - $quantity_needed;
            
            if($new_stock < 0) {
                $error .= "Not enough stock for " . $med['name'] . ". Need $quantity_needed, have " . $med['stock_quantity'] . ". ";
                $all_ok = false;
                continue;
            }
            
            $conn->query("UPDATE MEDICINE SET stock_quantity = $new_stock WHERE medicine_id = $med_id");
            $sale_id = rand(10000, 99999);
            $conn->query("INSERT INTO PHARMACY_SALES (sale_id, quantity_sold, total_price, prescription_id, medicine_id)
                VALUES ($sale_id, $quantity_needed, $total, $prescription_id, $med_id)");
        }
        
        if($all_ok && empty($error)) {
            $msg = "Prescription #$prescription_id fulfilled! Stock updated.";
        }
    }
}

// Get pending prescriptions (not yet fulfilled)
$pending_sql = "SELECT DISTINCT p.prescription_id, p.diagnosis, p.created_at, 
    pat.name as patient_name, d.name as doctor_name, d.specialization
    FROM PRESCRIPTION p
    JOIN PERSON pat ON p.person_id_patient = pat.person_id
    JOIN PERSON d ON p.person_id_doctor = d.person_id
    LEFT JOIN PHARMACY_SALES ps ON p.prescription_id = ps.prescription_id
    WHERE ps.prescription_id IS NULL $search_condition
    ORDER BY p.created_at DESC";
$pending_rx = $conn->query($pending_sql);

// Get fulfilled prescriptions
$fulfilled_rx = $conn->query("SELECT DISTINCT p.prescription_id, p.diagnosis, p.created_at, 
    pat.name as patient_name, d.name as doctor_name
    FROM PRESCRIPTION p
    JOIN PERSON pat ON p.person_id_patient = pat.person_id
    JOIN PERSON d ON p.person_id_doctor = d.person_id
    JOIN PHARMACY_SALES ps ON p.prescription_id = ps.prescription_id
    GROUP BY p.prescription_id
    ORDER BY p.created_at DESC
    LIMIT 20");

if(isset($_POST['add_medicine'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $manufacturer = $_POST['manufacturer'];
    $price = $_POST['price'];
    $stock = $_POST['stock_quantity'];
    $reorder = $_POST['reorder_level'];
    $expiry = $_POST['expiry_date'];
    $medicine_id = rand(100, 999);
    
    $sql = "INSERT INTO MEDICINE VALUES ($medicine_id, '$name', '$category', '$manufacturer', $price, $stock, $reorder, '$expiry')";
    
    if($conn->query($sql)) {
        $msg = "Medicine added successfully!";
    } else {
        $error = "Error adding medicine!";
    }
}

if(isset($_GET['delete'])) {
    $conn->query("DELETE FROM MEDICINE WHERE medicine_id = " . $_GET['delete']);
    header("Location: index.php"); exit;
}

$medicines = $conn->query("SELECT * FROM MEDICINE ORDER BY name");
$low_stock = $conn->query("SELECT * FROM MEDICINE WHERE stock_quantity <= reorder_level");
$expiring = $conn->query("SELECT * FROM MEDICINE WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE()");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Inventory - MediTrack BD</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .nav-link { color: white; padding: 12px 20px; border-radius: 10px; margin: 5px 0; display: block; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); text-decoration: none; color: white; }
        .analytics-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .analytics-icon { font-size: 40px; opacity: 0.8; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once('../includes/sidebar.php'); ?>
            <div class="col-md-10 p-4">
                <h2><i class="bi bi-capsule"></i> Pharmacy & Medical Inventory</h2>
                
                <?php if($msg): ?>
                <div class="alert alert-success"><?php echo $msg; ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Analytics Mini Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $total_sales_today; ?></h3>
                                    <p class="text-muted mb-0">Sales Today</p>
                                </div>
                                <i class="bi bi-cart-check analytics-icon text-primary"></i>
                            </div>
                            <small class="text-muted">Revenue: ৳<?php echo number_format($revenue_today, 2); ?></small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $most_sold_qty; ?></h3>
                                    <p class="text-muted mb-0">Most Sold Today</p>
                                </div>
                                <i class="bi bi-star analytics-icon text-warning"></i>
                            </div>
                            <small class="text-muted"><?php echo $most_sold_name; ?></small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?php echo $low_stock_count; ?></h3>
                                    <p class="text-muted mb-0">Low Stock Items</p>
                                </div>
                                <i class="bi bi-exclamation-triangle analytics-icon text-danger"></i>
                            </div>
                            <small class="text-muted">Needs restocking</small>
                        </div>
                    </div>
                </div>
                
                <!-- Stock Alert Banner -->
                <?php if($low_stock->num_rows > 0): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2" style="font-size: 24px;"></i>
                    <div>
                        <h5 class="mb-1">Low Stock Alert!</h5>
                        <p class="mb-0">The following medicines need restocking:</p>
                        <ul class="mb-0 mt-2">
                            <?php while($ls = $low_stock->fetch_assoc()): ?>
                            <li><strong><?php echo $ls['name']; ?></strong> - Current Stock: <span class="badge bg-warning"><?php echo $ls['stock_quantity']; ?></span> (Reorder Level: <?php echo $ls['reorder_level']; ?>)</li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Expiry Alert -->
                <?php if($expiring->num_rows > 0): ?>
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="bi bi-clock-fill me-2" style="font-size: 24px;"></i>
                    <div>
                        <h5 class="mb-1">Expiring Soon!</h5>
                        <p class="mb-0">These medicines expire within 30 days:</p>
                        <ul class="mb-0 mt-2">
                            <?php while($exp = $expiring->fetch_assoc()): ?>
                            <li><strong><?php echo $exp['name']; ?></strong> - Expires: <?php echo $exp['expiry_date']; ?></li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Search & Filter -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="bi bi-funnel"></i> Search & Filter Prescriptions</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Patient Name</label>
                                <input type="text" name="search_name" class="form-control" placeholder="Search by name..." value="<?php echo $_GET['search_name'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Doctor Name</label>
                                <input type="text" name="search_doctor" class="form-control" placeholder="Search by doctor..." value="<?php echo $_GET['search_doctor'] ?? ''; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $_GET['date_from'] ?? ''; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $_GET['date_to'] ?? ''; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100 mb-2"><i class="bi bi-search"></i> Filter</button>
                            </div>
                        </form>
                        <?php if($search_name || $search_doctor || ($date_from && $date_to)): ?>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary mt-2"><i class="bi bi-x-circle"></i> Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Pending Prescriptions for Pharmacy -->
                <?php if($pending_rx && $pending_rx->num_rows > 0): ?>
                <div class="card mb-4 border-warning">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="bi bi-shop"></i> Pending Prescriptions to Fill</h4>
                        <span class="badge bg-danger"><?php echo $pending_rx->num_rows; ?> New</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php while($rx = $pending_rx->fetch_assoc()): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-warning">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between">
                                            <strong>Rx #<?php echo $rx['prescription_id']; ?></strong>
                                            <small class="text-muted"><?php echo date('d M Y h:i A', strtotime($rx['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Patient:</strong> <?php echo $rx['patient_name']; ?></p>
                                        <p><strong>Doctor:</strong> <?php echo (strpos($rx['doctor_name'], 'Dr.') === 0) ? $rx['doctor_name'] : 'Dr. ' . $rx['doctor_name']; ?> (<?php echo $rx['specialization']; ?>)</p>
                                        <p><strong>Diagnosis:</strong> <?php echo $rx['diagnosis']; ?></p>
                                        
                                        <h6>Medicines Required:</h6>
                                        <table class="table table-sm table-bordered">
                                            <thead><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>In Stock</th></tr></thead>
                                            <tbody>
                                                <?php
                                                $rx_meds = $conn->query("SELECT m.name, c.dosage_level, c.daily_frequency, c.duration, m.stock_quantity
                                                    FROM CONTAINS c
                                                    JOIN MEDICINE m ON c.medicine_id = m.medicine_id
                                                    WHERE c.prescription_id = {$rx['prescription_id']}");
                                                while($m = $rx_meds->fetch_assoc()): ?>
                                                <tr class="<?php echo $m['stock_quantity'] < 5 ? 'table-danger' : ''; ?>">
                                                    <td><?php echo $m['name']; ?></td>
                                                    <td><?php echo $m['dosage_level']; ?></td>
                                                    <td><?php echo $m['daily_frequency']; ?></td>
                                                    <td><?php echo $m['duration']; ?></td>
                                                    <td>
                                                        <?php if($m['stock_quantity'] > 0): ?>
                                                        <span class="text-success">✅ <?php echo $m['stock_quantity']; ?></span>
                                                        <?php else: ?>
                                                        <span class="text-danger">❌ Out of Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                        
                                        <form method="POST" class="mt-2">
                                            <input type="hidden" name="prescription_id" value="<?php echo $rx['prescription_id']; ?>">
                                            <button type="submit" name="fulfill" class="btn btn-warning" onclick="return confirm('Dispense all medicines and reduce stock?')">
                                                <i class="bi bi-check2"></i> Dispense Medicines
                                            </button>
                                            <a href="print_prescription.php?id=<?php echo $rx['prescription_id']; ?>" target="_blank" class="btn btn-primary">
                                                <i class="bi bi-printer"></i> Print PDF
                                            </a>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
        
        <!-- Recently Fulfilled -->
        <?php if($fulfilled_rx && $fulfilled_rx->num_rows > 0): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5><i class="bi bi-check-circle"></i> Recently Dispensed</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr><th>Rx ID</th><th>Patient</th><th>Doctor</th><th>Diagnosis</th><th>Date</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php while($rx = $fulfilled_rx->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $rx['prescription_id']; ?></td>
                            <td><?php echo $rx['patient_name']; ?></td>
                            <td><?php echo (strpos($rx['doctor_name'], 'Dr.') === 0) ? $rx['doctor_name'] : 'Dr. ' . $rx['doctor_name']; ?></td>
                            <td><?php echo $rx['diagnosis']; ?></td>
                            <td><?php echo date('d M Y', strtotime($rx['created_at'])); ?></td>
                            <td>
                                <a href="print_prescription.php?id=<?php echo $rx['prescription_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-printer"></i> Print
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if($msg): ?>
        <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($low_stock->num_rows > 0): ?>
        <div class="alert alert-warning">
            <h5><i class="bi bi-exclamation-triangle"></i> Low Stock Alert!</h5>
            <p>The following medicines need restocking:</p>
            <ul>
                <?php while($ls = $low_stock->fetch_assoc()): ?>
                <li><?php echo $ls['name']; ?> - Current Stock: <?php echo $ls['stock_quantity']; ?> (Reorder: <?php echo $ls['reorder_level']; ?>)</li>
                <?php endwhile; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if($expiring->num_rows > 0): ?>
        <div class="alert alert-danger">
            <h5><i class="bi bi-exclamation-triangle-fill"></i> Expiring Soon!</h5>
            <p>These medicines expire within 30 days:</p>
            <ul>
                <?php while($exp = $expiring->fetch_assoc()): ?>
                <li><?php echo $exp['name']; ?> - Expires: <?php echo $exp['expiry_date']; ?></li>
                <?php endwhile; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-plus-circle"></i> Add New Medicine</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <input type="text" name="name" class="form-control" placeholder="Medicine Name" required>
                        </div>
                        <div class="col-md-2 mb-2">
                            <input type="text" name="category" class="form-control" placeholder="Category">
                        </div>
                        <div class="col-md-2 mb-2">
                            <input type="text" name="manufacturer" class="form-control" placeholder="Manufacturer">
                        </div>
                        <div class="col-md-2 mb-2">
                            <input type="number" step="0.01" name="price" class="form-control" placeholder="Price (BDT)" required>
                        </div>
                        <div class="col-md-1 mb-2">
                            <input type="number" name="stock_quantity" class="form-control" placeholder="Stock" required>
                        </div>
                        <div class="col-md-1 mb-2">
                            <input type="number" name="reorder_level" class="form-control" placeholder="Reorder" required>
                        </div>
                        <div class="col-md-2 mb-2">
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>
                    <button type="submit" name="add_medicine" class="btn btn-primary">Add Medicine</button>
                </form>
            </div>
        </div>
        
        <h4>All Medicines</h4>
        <table class="table table-hover bg-white rounded">
            <thead class="table-dark">
                <tr><th>ID</th><th>Name</th><th>Category</th><th>Manufacturer</th><th>Price</th><th>Stock</th><th>Expiry</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php while($med = $medicines->fetch_assoc()): ?>
                <tr class="<?php echo $med['stock_quantity'] <= $med['reorder_level'] ? 'table-warning' : ''; ?>">
                    <td><?php echo $med['medicine_id']; ?></td>
                    <td><?php echo $med['name']; ?></td>
                    <td><?php echo $med['category']; ?></td>
                    <td><?php echo $med['manufacturer']; ?></td>
                    <td>৳<?php echo $med['price']; ?></td>
                    <td>
                        <span class="<?php echo $med['stock_quantity'] <= $med['reorder_level'] ? 'text-danger fw-bold' : ''; ?>"><?php echo $med['stock_quantity']; ?></span>
                    </td>
                    <td><?php echo $med['expiry_date'] ?? 'N/A'; ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editStock<?php echo $med['medicine_id']; ?>">
                            <i class="bi bi-pencil"></i> Edit Stock
                        </button>
                        <a href="index.php?delete=<?php echo $med['medicine_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                    </td>
                </tr>

                <!-- Edit Stock Modal -->
                <div class="modal fade" id="editStock<?php echo $med['medicine_id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Stock</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <p><strong><?php echo $med['name']; ?></strong></p>
                                    <input type="hidden" name="medicine_id" value="<?php echo $med['medicine_id']; ?>">
                                    <label>New Stock Quantity:</label>
                                    <input type="number" name="new_stock" class="form-control" value="<?php echo $med['stock_quantity']; ?>" min="0" required>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" name="update_stock" class="btn btn-warning">Update Stock</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <?php if($medicines->num_rows == 0): ?>
        <div class="alert alert-info">No medicines in inventory. Add some above.</div>
        <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>