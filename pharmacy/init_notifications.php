<?php
session_start();
if(!isset($_SESSION['admin_id'])) { 
    echo "Unauthorized"; 
    exit; 
}

$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

// Create notification table if not exists
$sql = "CREATE TABLE IF NOT EXISTS PHARMACY_NOTIFICATIONS (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    patient_id INT NOT NULL,
    message TEXT,
    status ENUM('New','Processing','Completed') DEFAULT 'New',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES PRESCRIPTION(prescription_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES PERSON(person_id) ON DELETE CASCADE
)";

if($conn->query($sql)) {
    echo "Notification table created successfully! <a href='index.php'>Go to Pharmacy</a>";
} else {
    echo "Error: " . $conn->error;
}
?>
