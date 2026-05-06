<?php
session_start();
if(!isset($_SESSION['person_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

// Get notifications for pharmacy (all notifications)
$notifications = $conn->query("
    SELECT n.*, p.diagnosis, p.created_at as prescription_date,
           pat.name as patient_name, d.name as doctor_name
    FROM PHARMACY_NOTIFICATIONS n
    JOIN PRESCRIPTION p ON n.prescription_id = p.prescription_id
    JOIN PERSON pat ON n.patient_id = pat.person_id
    JOIN PERSON d ON p.person_id_doctor = d.person_id
    ORDER BY n.created_at DESC
    LIMIT 50
");

$result = [];
while($n = $notifications->fetch_assoc()) {
    // Calculate time ago
    $time_ago = time() - strtotime($n['created_at']);
    if($time_ago < 60) $time_text = $time_ago . " sec ago";
    elseif($time_ago < 3600) $time_text = floor($time_ago/60) . " min ago";
    elseif($time_ago < 86400) $time_text = floor($time_ago/3600) . " hr ago";
    else $time_text = floor($time_ago/86400) . " days ago";
    
    $result[] = [
        'id' => $n['notification_id'],
        'prescription_id' => $n['prescription_id'],
        'patient_name' => $n['patient_name'],
        'doctor_name' => $n['doctor_name'],
        'diagnosis' => substr($n['diagnosis'], 0, 50) . (strlen($n['diagnosis']) > 50 ? '...' : ''),
        'status' => $n['status'],
        'is_read' => $n['is_read'],
        'time_ago' => $time_text,
        'created_at' => $n['created_at']
    ];
}

// Get unread count
$unread_count = $conn->query("SELECT COUNT(*) FROM PHARMACY_NOTIFICATIONS WHERE is_read = FALSE")->fetch_row()[0];

echo json_encode([
    'notifications' => $result,
    'unread_count' => $unread_count
]);
?>
