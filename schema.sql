-- ============================================================
--   MediTrack BD — MySQL Schema
--   Course: CSE370 — Database Management System
-- ============================================================

CREATE DATABASE IF NOT EXISTS meditrack_bd;
USE meditrack_bd;

-- ============================================================
-- 1. ADMIN
-- ============================================================
CREATE TABLE ADMIN (
    admin_id            INT             AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100)    NOT NULL,
    email               VARCHAR(100)    NOT NULL UNIQUE,
    password            VARCHAR(255)    NOT NULL,
    role                ENUM('SuperAdmin','Manager') NOT NULL
);

-- ============================================================
-- 2. AMBULANCE
-- ============================================================
CREATE TABLE AMBULANCE (
    ambulance_id        INT             PRIMARY KEY,
    driver_name         VARCHAR(100),
    phone               VARCHAR(15),
    vehicle_number      VARCHAR(20)     NOT NULL UNIQUE,
    current_location    TEXT,
    status              ENUM('Available','Busy') DEFAULT 'Available'
);

-- ============================================================
-- 3. MEDICINE
-- ============================================================
CREATE TABLE MEDICINE (
    medicine_id         INT             PRIMARY KEY,
    name                VARCHAR(100)    NOT NULL,
    category            VARCHAR(50),
    manufacturer        VARCHAR(100),
    price               DECIMAL(10,2),
    stock_quantity      INT             DEFAULT 0,
    reorder_level       INT             DEFAULT 10,
    expiry_date         DATE
);

-- ============================================================
-- 4. HOSPITAL
-- ============================================================
CREATE TABLE HOSPITAL (
    hospital_id         INT             AUTO_INCREMENT PRIMARY KEY,
    name_of_hospital    VARCHAR(100)    NOT NULL,
    location            TEXT,
    phone               VARCHAR(15),
    type_of_hospital    ENUM('Government','Private','Clinic') NOT NULL,
    total_beds          INT             DEFAULT 0,
    available_beds      INT             DEFAULT 0,
    specializations     TEXT,
    city                VARCHAR(50),
    district            VARCHAR(50),
    road_no             VARCHAR(50),
    ambulance_id        INT,
    admin_id            INT,
    FOREIGN KEY (admin_id) REFERENCES ADMIN(admin_id) ON DELETE SET NULL,
    FOREIGN KEY (ambulance_id) REFERENCES AMBULANCE(ambulance_id) ON DELETE SET NULL
);

-- ============================================================
-- 5. PERSON (CORE ENTITY)
-- ============================================================
CREATE TABLE PERSON (
    person_id           INT             PRIMARY KEY,
    name                VARCHAR(100)    NOT NULL,
    date_of_birth       DATE,
    gender              ENUM('Male','Female','Other'),
    login_password      VARCHAR(255)    NOT NULL,
    road                VARCHAR(100),
    city                VARCHAR(50),
    country             VARCHAR(50),
    created_at          DATETIME        DEFAULT CURRENT_TIMESTAMP,
    person_type         ENUM('Patient','Doctor') NOT NULL,
    blood_group         VARCHAR(5),
    allergy             TEXT,
    chronic_disease     TEXT,
    emergency_contact   VARCHAR(15),
    specialization      VARCHAR(100),
    experience_year     INT,
    consultation_fee    DECIMAL(10,2),
    available_days      VARCHAR(100),
    available_hours     VARCHAR(500),
    chamber_address     TEXT,
    hospital_id         INT,
    FOREIGN KEY (hospital_id) REFERENCES HOSPITAL(hospital_id) ON DELETE SET NULL
);

-- ============================================================
-- 6. PHONE (MULTI-VALUED ATTRIBUTE)
-- ============================================================
CREATE TABLE PHONE (
    person_id          INT             NOT NULL,
    phone              VARCHAR(15)     NOT NULL,
    PRIMARY KEY (person_id, phone),
    FOREIGN KEY (person_id) REFERENCES PERSON(person_id) ON DELETE CASCADE
);

-- ============================================================
-- 7. EMAIL (MULTI-VALUED ATTRIBUTE)
-- ============================================================
CREATE TABLE EMAIL (
    person_id          INT             NOT NULL,
    email              VARCHAR(100)    NOT NULL,
    PRIMARY KEY (person_id, email),
    FOREIGN KEY (person_id) REFERENCES PERSON(person_id) ON DELETE CASCADE
);

-- ============================================================
-- 8. EMERGENCY_REQUEST
-- ============================================================
CREATE TABLE EMERGENCY_REQUEST (
    emergency_id            INT         PRIMARY KEY,
    request_time            DATETIME    DEFAULT CURRENT_TIMESTAMP,
    patient_location        TEXT,
    specialization_needed   VARCHAR(100),
    condition_level         ENUM('Critical','Serious','Moderate') NOT NULL,
    doctor_response         ENUM('Accepted','Unavailable','Pending') DEFAULT 'Pending',
    request_status          ENUM('Requested','Dispatched','Completed') DEFAULT 'Requested',
    doctor_id               INT,
    ambulance_id            INT,
    person_id_patient       INT         NOT NULL,
    FOREIGN KEY (ambulance_id) REFERENCES AMBULANCE(ambulance_id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id) REFERENCES PERSON(person_id) ON DELETE SET NULL,
    FOREIGN KEY (person_id_patient) REFERENCES PERSON(person_id) ON DELETE CASCADE
);

-- ============================================================
-- 9. PRESCRIPTION
-- ============================================================
CREATE TABLE PRESCRIPTION (
    prescription_id     INT             PRIMARY KEY,
    diagnosis           TEXT,
    notes               TEXT,
    created_at          DATETIME        DEFAULT CURRENT_TIMESTAMP,
    person_id_patient   INT             NOT NULL,
    person_id_doctor    INT             NOT NULL,
    FOREIGN KEY (person_id_patient) REFERENCES PERSON(person_id) ON DELETE CASCADE,
    FOREIGN KEY (person_id_doctor) REFERENCES PERSON(person_id) ON DELETE CASCADE
);

-- ============================================================
-- 10. APPOINTMENT
-- ============================================================
CREATE TABLE APPOINTMENT (
    appointment_id      INT             PRIMARY KEY,
    appointment_date    DATE            NOT NULL,
    appointment_time    VARCHAR(20)     NOT NULL,
    status              ENUM('Pending','Confirmed','Cancelled','Completed') DEFAULT 'Pending',
    is_emergency        BOOLEAN         DEFAULT FALSE,
    patient_problem      TEXT,
    person_id_patient   INT             NOT NULL,
    person_id_doctor    INT             NOT NULL,
    prescription_id     INT,
    FOREIGN KEY (person_id_patient) REFERENCES PERSON(person_id) ON DELETE CASCADE,
    FOREIGN KEY (person_id_doctor) REFERENCES PERSON(person_id) ON DELETE CASCADE,
    FOREIGN KEY (prescription_id) REFERENCES PRESCRIPTION(prescription_id) ON DELETE SET NULL
);

-- ============================================================
-- 11. CONTAINS
-- ============================================================
CREATE TABLE CONTAINS (
    prescription_id     INT             NOT NULL,
    medicine_id         INT             NOT NULL,
    dosage_level        VARCHAR(50),
    daily_frequency     VARCHAR(50),
    duration            VARCHAR(50),
    PRIMARY KEY (prescription_id, medicine_id),
    FOREIGN KEY (prescription_id) REFERENCES PRESCRIPTION(prescription_id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES MEDICINE(medicine_id) ON DELETE CASCADE
);

-- ============================================================
-- 12. PHARMACY_SALES
-- ============================================================
CREATE TABLE PHARMACY_SALES (
    sale_id             INT             PRIMARY KEY,
    quantity_sold       INT             NOT NULL,
    sale_date           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    total_price         DECIMAL(10,2),
    prescription_id     INT             NOT NULL,
    medicine_id         INT             NOT NULL,
    FOREIGN KEY (prescription_id) REFERENCES PRESCRIPTION(prescription_id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES MEDICINE(medicine_id) ON DELETE CASCADE
);

-- ============================================================
-- 14. INTERACTS_WITH
-- ============================================================
CREATE TABLE INTERACTS_WITH (
    prime_medicine_id       INT         NOT NULL,
    secondary_medicine_id   INT         NOT NULL,
    severity                ENUM('Low','Medium','High') NOT NULL,
    description             TEXT,
    PRIMARY KEY (prime_medicine_id, secondary_medicine_id),
    FOREIGN KEY (prime_medicine_id) REFERENCES MEDICINE(medicine_id) ON DELETE CASCADE,
    FOREIGN KEY (secondary_medicine_id) REFERENCES MEDICINE(medicine_id) ON DELETE CASCADE
);

-- ============================================================
-- 15. PHARMACY_NOTIFICATIONS
-- ============================================================
CREATE TABLE PHARMACY_NOTIFICATIONS (
    notification_id     INT             AUTO_INCREMENT PRIMARY KEY,
    prescription_id     INT             NOT NULL,
    patient_id          INT             NOT NULL,
    message             TEXT,
    status              ENUM('New','Processing','Completed') DEFAULT 'New',
    is_read             BOOLEAN         DEFAULT FALSE,
    created_at          TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES PRESCRIPTION(prescription_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES PERSON(person_id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_is_read (is_read),
    INDEX idx_created (created_at)
);

-- ============================================================
-- 14. BLOOD_DONOR
-- ============================================================
CREATE TABLE BLOOD_DONOR (
    donor_id            INT             PRIMARY KEY,
    name                VARCHAR(100)    NOT NULL,
    blood_group         VARCHAR(5)      NOT NULL,
    phone               VARCHAR(15),
    address             TEXT,
    last_donation_date  DATE,
    is_eligible         BOOLEAN         DEFAULT TRUE,
    person_id           INT             NOT NULL,
    FOREIGN KEY (person_id) REFERENCES PERSON(person_id) ON DELETE CASCADE
);

-- ============================================================
-- SAMPLE DATA FOR TESTING
-- ============================================================

-- Admin
INSERT INTO ADMIN (name, email, password, role) VALUES ('Super Admin', 'system@meditrack.com', 'system123', 'SuperAdmin');

-- Ambulance
INSERT INTO AMBULANCE VALUES (1, 'John Doe', '01710000001', 'DHA-A-001', ' Dhaka', 'Available');
INSERT INTO AMBULANCE VALUES (2, 'Mike Smith', '01710000002', 'DHA-A-002', ' Dhaka', 'Available');

-- Hospital
INSERT INTO HOSPITAL VALUES (1, 'Meditrack Hospital', 'Dhaka', '02-1234567', 'Private', 100, 50, 'Cardiology,Medicine,Neurology', 'Dhaka', 'Dhaka', 'Road 5', NULL, 1);

-- Doctors (password: doctor123)
INSERT INTO PERSON VALUES (1, 'Dr. Ahmed Khan', '1980-05-15', 'Male', 'doctor123', 'Road 5', 'Dhaka', 'Bangladesh', NOW(), 'Doctor', NULL, NULL, NULL, NULL, 'Cardiology', 15, 500.00, 'Sat-Thu', '09:00,10:00,11:00,14:00,15:00,16:00', 'Meditrack Hospital', 1);
INSERT INTO PERSON VALUES (2, 'Dr. Sarah Islam', '1985-08-20', 'Female', 'doctor123', 'Road 10', 'Dhaka', 'Bangladesh', NOW(), 'Doctor', NULL, NULL, NULL, NULL, 'General Medicine', 10, 300.00, 'Sat-Thu', '10:00,11:00,14:00,15:00,16:00,17:00', 'Meditrack Hospital', 1);
INSERT INTO PERSON VALUES (3, 'Dr. Rahman Ali', '1978-03-10', 'Male', 'doctor123', 'Road 3', 'Dhaka', 'Bangladesh', NOW(), 'Doctor', NULL, NULL, NULL, NULL, 'Neurology', 20, 800.00, 'Sat-Thu', '09:00,10:00,11:00,14:00,15:00', 'Meditrack Hospital', 1);

-- Patients (password: patient123)
INSERT INTO PERSON VALUES (4, 'John Carter', '1990-06-12', 'Male', 'patient123', 'House 10, Mirpur', 'Dhaka', 'Bangladesh', NOW(), 'Patient', 'O+', 'Penicillin', 'None', '01710001111', NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO PERSON VALUES (5, 'Mary Sunny', '1985-11-25', 'Female', 'patient123', 'House 25, Dhanmondi', 'Dhaka', 'Bangladesh', NOW(), 'Patient', 'A+', 'None', 'Diabetes', '01710002222', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- Phone & Email for users
INSERT INTO PHONE VALUES (1, '01710000001');
INSERT INTO PHONE VALUES (2, '01710000002');
INSERT INTO PHONE VALUES (3, '01710000003');
INSERT INTO PHONE VALUES (4, '01710001111');
INSERT INTO PHONE VALUES (5, '01710002222');

INSERT INTO EMAIL VALUES (1, 'dr.ahmed@meditrack.com');
INSERT INTO EMAIL VALUES (2, 'dr.sarah@meditrack.com');
INSERT INTO EMAIL VALUES (3, 'dr.rahman@meditrack.com');
INSERT INTO EMAIL VALUES (4, 'john.carter@email.com');
INSERT INTO EMAIL VALUES (5, 'mary.sunny@email.com');

-- Medicines
INSERT INTO MEDICINE VALUES (1, 'Paracetamol', 'Pain Killer', 'Square Pharma', 5.00, 100, 20, '2025-12-31');
INSERT INTO MEDICINE VALUES (2, 'Aspirin', 'Pain Killer', 'Beximco', 3.50, 80, 15, '2025-12-31');
INSERT INTO MEDICINE VALUES (3, 'Amoxicillin', 'Antibiotic', 'ACI Pharma', 15.00, 50, 10, '2025-12-31');
INSERT INTO MEDICINE VALUES (4, 'Metformin', 'Diabetes', 'Square Pharma', 8.00, 60, 15, '2025-12-31');
INSERT INTO MEDICINE VALUES (5, 'Omeprazole', 'Acid Reducer', 'Incepta', 12.00, 40, 10, '2025-12-31');
INSERT INTO MEDICINE VALUES (6, 'Cetirizine', 'Allergy', 'Square Pharma', 2.00, 150, 30, '2025-12-31');

-- Drug Interactions (for testing)
INSERT INTO INTERACTS_WITH VALUES (1, 2, 'High', 'Both medicines increase bleeding risk - dangerous combination!');
INSERT INTO INTERACTS_WITH VALUES (3, 1, 'Medium', 'Amoxicillin may reduce effectiveness of Paracetamol');

-- Blood Donors
INSERT INTO BLOOD_DONOR VALUES (1, 'John Carter', 'O+', '01710001111', 'Mirpur, Dhaka', NULL, TRUE, 4);
INSERT INTO BLOOD_DONOR VALUES (2, 'Ahmed Hasan', 'A+', '01710003333', 'Mirpur, Dhaka', NULL, TRUE, 4);

-- Appointments (Sample)
INSERT INTO APPOINTMENT VALUES (1, '2026-04-25', '10:00:00', 'Confirmed', FALSE, NULL, 4, 1, NULL);
INSERT INTO APPOINTMENT VALUES (2, '2026-04-26', '11:00:00', 'Pending', FALSE, NULL, 5, 2, NULL);

-- Prescriptions (Sample)
INSERT INTO PRESCRIPTION VALUES (1, 'Common Cold - Rest and fluids', 'Take medicines as prescribed', NOW(), 4, 1);
INSERT INTO PRESCRIPTION VALUES (2, 'Diabetes Check - Monitor sugar', 'Continue current medication', NOW(), 5, 2);

-- Contains (Prescription Medicines)
INSERT INTO CONTAINS VALUES (1, 1, '500mg', '3 times daily', '5 days');
INSERT INTO CONTAINS VALUES (1, 6, '10mg', 'once daily', '5 days');
INSERT INTO CONTAINS VALUES (2, 4, '500mg', 'twice daily', '30 days');
