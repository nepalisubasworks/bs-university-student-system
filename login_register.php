<?php
session_start();
require_once 'config.php';

// =====================
// REGISTRATION
// =====================
if (isset($_POST['register'])) {

    // Get all form values
    $name         = $_POST['name'];
    $email        = $_POST['email'];
    $password     = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone        = $_POST['phone'];
    $parent_name  = $_POST['parent_name'];
    $parent_phone = $_POST['parent_phone'];
    $faculty      = $_POST['faculty'];
    $course       = $_POST['course'];
    $dob          = $_POST['dob'];
    $gender       = $_POST['gender'];
    $address      = $_POST['address'];

    // Save form values in session so form stays filled if there is an error
    $_SESSION['old_input']  = $_POST;
    $_SESSION['active_form'] = 'register';

    // Helper function: set error and go back to registration form
    function registerError($msg) {
        $_SESSION['register_error'] = $msg;
        header("Location: index.php");
        exit();
    }

    // Validate name (letters only)
    if (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
        registerError("Name should contain only letters!");
    }

    // Validate parent name (letters only)
    if (!preg_match("/^[a-zA-Z\s]+$/", $parent_name)) {
        registerError("Parent's name should contain only letters!");
    }

    // Validate phone (numbers only, must be 10 digits)
    if (!preg_match("/^[0-9]+$/", $phone) || strlen($phone) != 10) {
        registerError("Phone number must be exactly 10 digits!");
    }

    // Validate parent phone
    if (!preg_match("/^[0-9]+$/", $parent_phone) || strlen($parent_phone) != 10) {
        registerError("Parent's phone number must be exactly 10 digits!");
    }

    // Validate date format first (must be YYYY-MM-DD)
    $parsedDate = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $dob) {
        registerError("Invalid date of birth! Please use the format YYYY-MM-DD (e.g. 2000-05-20).");
    }

    // Validate age (must be between 18 and 50)
    $birthDate = new DateTime($dob);
    $today     = new DateTime();
    $age       = $today->diff($birthDate)->y;

    if ($age < 18) {
        registerError("You must be at least 18 years old to register!");
    }
    if ($age > 50) {
        registerError("Age cannot be more than 50 years!");
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM students WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        registerError("This email is already registered!");
    }

    // All validations passed — insert student into database
    $stmt = $conn->prepare("INSERT INTO students 
        (name, email, password, role, phone, parent_name, parent_phone, faculty, course, address, DOB, gender, registration_date)
        VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
    $stmt->bind_param("sssssssssss", $name, $email, $password, $phone, $parent_name, $parent_phone, $faculty, $course, $address, $dob, $gender);
    $stmt->execute();
    $newStudentId = $conn->insert_id;

    // Find the course_id for the selected course
    $courseStmt = $conn->prepare("SELECT course_id FROM course WHERE course_name = ? LIMIT 1");
    $courseStmt->bind_param("s", $course);
    $courseStmt->execute();
    $courseResult = $courseStmt->get_result();
    if ($courseResult->num_rows > 0) {
        $courseId = $courseResult->fetch_assoc()['course_id'];
        $enroll = $conn->prepare("INSERT INTO enrollment (student_id, course_id, enrollment_date) VALUES (?, ?, CURDATE())");
        $enroll->bind_param("ii", $newStudentId, $courseId);
        $enroll->execute();
    }

    // Registration done — clear saved form values and go back to login
    unset($_SESSION['old_input']);
    unset($_SESSION['active_form']);
    header("Location: index.php");
    exit();
}

// =====================
// LOGIN
// =====================
if (isset($_POST['login'])) {

    $email    = $_POST['email'];
    $password = $_POST['password'];

    // Find user by email
    $stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();

        // Check if password matches
        if (password_verify($password, $student['password'])) {

                        // Start a fresh session ID after login (blocks session fixation)
            session_regenerate_id(true);

            // Save email in session (used to identify the logged-in user)
            $_SESSION['email'] = $student['email'];

            // Send to correct page based on role
            if ($student['role'] == 'admin') {
                header("Location: admin_page.php");
            } else {
                header("Location: student_page.php");
            }
            exit();
        }
    }

    // Login failed
    $_SESSION['login_error']  = "Incorrect email or password!";
    $_SESSION['active_form']  = 'login';
    header("Location: index.php");
    exit();
}
?>