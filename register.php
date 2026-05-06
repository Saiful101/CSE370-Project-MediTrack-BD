<?php
session_start();
$msg = "";
$error = "";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db.php';
    
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $person_type = $_POST['person_type'];
    $gender = $_POST['gender'];
    $dob = $_POST['date_of_birth'];
    $blood_group = $_POST['blood_group'];
    $phone = $_POST['phone'];
    $allergy = $_POST['allergy'];
    $chronic = $_POST['chronic_disease'];
    
    // Doctor specific
    $specialization = $_POST['specialization'];
    $experience = $_POST['experience_year'];
    $fee = $_POST['consultation_fee'];
    $available_days = $_POST['available_days'];
    $chamber = $_POST['chamber_address'];
    
    $person_id = rand(1000, 9999);
    
    if($person_type == 'Doctor') {
        $sql = "INSERT INTO PERSON (person_id, name, date_of_birth, gender, login_password, city, country, created_at, person_type, specialization, experience_year, consultation_fee, available_days, chamber_address) 
                VALUES ($person_id, '$name', ";
        if($dob) $sql .= "'$dob', "; else $sql .= "NULL, ";
        if($gender) $sql .= "'$gender', "; else $sql .= "NULL, ";
        $sql .= "'$password', 'Dhaka', 'Bangladesh', NOW(), '$person_type', ";
        if($specialization) $sql .= "'$specialization', "; else $sql .= "NULL, ";
        if($experience) $sql .= "$experience, "; else $sql .= "NULL, ";
        if($fee) $sql .= "$fee, "; else $sql .= "NULL, ";
        if($available_days) $sql .= "'$available_days', "; else $sql .= "NULL, ";
        if($chamber) $sql .= "'$chamber')"; else $sql .= "NULL)";
    } else {
        $sql = "INSERT INTO PERSON (person_id, name, date_of_birth, gender, login_password, city, country, created_at, person_type, blood_group, allergy, chronic_disease, emergency_contact) 
                VALUES ($person_id, '$name', ";
        if($dob) $sql .= "'$dob', "; else $sql .= "NULL, ";
        if($gender) $sql .= "'$gender', "; else $sql .= "NULL, ";
        $sql .= "'$password', 'Dhaka', 'Bangladesh', NOW(), '$person_type', ";
        if($blood_group) $sql .= "'$blood_group', "; else $sql .= "NULL, ";
        if($allergy) $sql .= "'$allergy', "; else $sql .= "NULL, ";
        if($chronic) $sql .= "'$chronic', "; else $sql .= "NULL, ";
        if($phone) $sql .= "'$phone')"; else $sql .= "NULL)";
    }
    
    if($conn->query($sql)) {
        if($phone) {
            $conn->query("INSERT INTO PHONE VALUES ($person_id, '$phone')");
        }
        if($email) {
            $conn->query("INSERT INTO EMAIL VALUES ($person_id, '$email')");
        }
        $msg = "Registration successful! Please login with your email.";
    } else {
        $error = "Error: " . $conn->error;
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediTrack BD - Register</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 0; }
        .register-card { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); padding: 40px; max-width: 600px; margin: 0 auto; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .logo { font-size: 28px; font-weight: bold; color: #667eea; text-align: center; }
        .doctor-fields { display: none; }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="logo">MediTrack<span>BD</span> - Register</div>
        <?php if($msg): ?>
        <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">I am a</label>
                <select name="person_type" class="form-select" required onchange="toggleFields()">
                    <option value="Patient">Patient</option>
                    <option value="Doctor">Doctor</option>
                </select>
            </div>
            
            <div id="patientFields">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Blood Group</label>
                        <select name="blood_group" class="form-select">
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
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" class="form-control" placeholder="01712345678" required pattern="[0-9]{11}" maxlength="11" title="Enter 11 digit mobile number (e.g. 01712345678)">
                        <small class="text-muted">Enter 11 digit mobile number (e.g. 01712345678)</small>
                    </div>
                </div>
                <div class="mb-3"><label class="form-label">Allergies</label>
                    <textarea name="allergy" class="form-control" rows="2" placeholder="List any allergies..."></textarea>
                </div>
                <div class="mb-3"><label class="form-label">Chronic Diseases</label>
                    <textarea name="chronic_disease" class="form-control" rows="2" placeholder="List chronic conditions..."></textarea>
                </div>
            </div>
            
            <div id="doctorFields" class="doctor-fields">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Specialization</label>
                        <select name="specialization" class="form-select">
                            <option value="">Select</option>
                            <option value="Cardiology">Cardiology</option>
                            <option value="Neurology">Neurology</option>
                            <option value="General Medicine">General Medicine</option>
                            <option value="Pediatrics">Pediatrics</option>
                            <option value="Orthopedics">Orthopedics</option>
                            <option value="Dermatology">Dermatology</option>
                            <option value="Eye">Eye Specialist</option>
                            <option value="ENT">ENT</option>
                            <option value="Gynecology">Gynecology</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Experience (Years)</label>
                        <input type="number" name="experience_year" class="form-control" placeholder="e.g. 10">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Consultation Fee (BDT)</label>
                        <input type="number" name="consultation_fee" class="form-control" placeholder="e.g. 500">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Available Days</label>
                        <input type="text" name="available_days" class="form-control" placeholder="e.g. Sat-Thu">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Chamber Address</label>
                    <textarea name="chamber_address" class="form-control" rows="2" placeholder="Your chamber address..."></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
        <div class="text-center mt-3">
            <a href="index.php" class="text-muted">Already have account? Login</a>
        </div>
    </div>
    
    <script>
    function toggleFields() {
        var type = document.querySelector('select[name="person_type"]').value;
        if(type == 'Doctor') {
            document.getElementById('patientFields').style.display = 'none';
            document.getElementById('doctorFields').style.display = 'block';
        } else {
            document.getElementById('patientFields').style.display = 'block';
            document.getElementById('doctorFields').style.display = 'none';
        }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>