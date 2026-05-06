<?php
$conn = new mysqli("localhost", "root", "", "meditrack_bd");

// Test login query
$email = 'dr.ahmed@meditrack.com';
$password = 'doctor123';

$result = $conn->query("SELECT p.person_id, p.name, p.person_type, p.login_password 
    FROM PERSON p INNER JOIN EMAIL e ON p.person_id = e.person_id 
    WHERE e.email = '$email' AND p.login_password = '$password'");

echo "Query Result:<br>";
if($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "SUCCESS! Found user:<br>";
    echo "ID: " . $row['person_id'] . "<br>";
    echo "Name: " . $row['name'] . "<br>";
    echo "Type: " . $row['person_type'] . "<br>";
    echo "Password: " . $row['login_password'] . "<br>";
} else {
    echo "No results found!<br>";
    echo "Checking if email exists...<br>";
    $check = $conn->query("SELECT p.person_id, p.name, p.login_password FROM PERSON p INNER JOIN EMAIL e ON p.person_id = e.person_id WHERE e.email = '$email'");
    if($check && $check->num_rows > 0) {
        $c = $check->fetch_assoc();
        echo "Email exists, but password might be wrong. Stored password: " . $c['login_password'] . "<br>";
    } else {
        echo "Email doesn't exist in database!<br>";
    }
}

$conn->close();
?>