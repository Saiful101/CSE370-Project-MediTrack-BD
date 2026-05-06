<?php
$conn = new mysqli("localhost", "root", "", "meditrack_bd");

// Check if doctors have emails
$doctors = $conn->query("SELECT p.person_id, p.name, p.person_type, e.email
    FROM PERSON p
    LEFT JOIN EMAIL e ON p.person_id = e.person_id
    WHERE p.person_type = 'Doctor'");

echo "<h3>Doctor Emails Check:</h3>";
while($d = $doctors->fetch_assoc()) {
    echo "Doctor: " . $d['name'] . " - Email: " . ($d['email'] ?? 'NO EMAIL') . "<br>";
}

// If no emails, insert them
$check = $conn->query("SELECT COUNT(*) FROM EMAIL WHERE person_id = 1");
if($check->fetch_row()[0] == 0) {
    echo "<br>Inserting emails for doctors...<br>";
    $conn->query("INSERT INTO EMAIL VALUES (1, 'dr.ahmed@meditrack.com')");
    $conn->query("INSERT INTO EMAIL VALUES (2, 'dr.sarah@meditrack.com')");
    $conn->query("INSERT INTO EMAIL VALUES (3, 'dr.rahman@meditrack.com')");
    echo "Done!";
}

$conn->close();
?>