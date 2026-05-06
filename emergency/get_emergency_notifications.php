<?php
session_start();
require_once('../includes/auth.php');

$conn = new mysqli("localhost", "root", "", "meditrack_bd");
$conn->set_charset("utf8mb4");

$is_admin = is_admin();
$is_doctor = is_doctor();
$person_id = $_SESSION['person_id'] ?? 0;

// Get pending emergency count
$count_query = "SELECT COUNT(*) FROM EMERGENCY_REQUEST WHERE request_status IN ('Requested', 'Dispatched')";
$count = $conn->query($count_query)->fetch_row()[0];

// Get recent emergencies for notification dropdown
$notification_query = "SELECT e.*, pat.name as patient_name
    FROM EMERGENCY_REQUEST e
    LEFT JOIN PERSON pat ON e.person_id_patient = pat.person_id
    WHERE e.request_status IN ('Requested', 'Dispatched')";

if ($is_doctor && !$is_admin) {
    $doc_spec = $conn->query("SELECT specialization FROM PERSON WHERE person_id = $person_id")->fetch_assoc();
    $spec = $doc_spec['specialization'] ?? '';
    $notification_query .= " AND (e.specialization_needed = '$spec' OR e.specialization_needed = 'General' OR e.specialization_needed = 'Emergency')";
}

$notification_query .= " ORDER BY
    CASE e.condition_level WHEN 'Critical' THEN 1 WHEN 'Serious' THEN 2 ELSE 3 END,
    e.request_time DESC
    LIMIT 5";

$notifications = $conn->query($notification_query);

$notification_list = [];
while ($row = $notifications->fetch_assoc()) {
    $notification_list[] = $row;
}

echo json_encode([
    'count' => $count,
    'notifications' => $notification_list
]);

$conn->close();
?>