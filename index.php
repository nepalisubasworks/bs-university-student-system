<?php
session_start();
require_once 'config.php';

$errors = [
    'login' => $_SESSION['login_error'] ?? null,
    'register' => $_SESSION['register_error'] ?? null
];
$activeForm = $_SESSION['active_form'] ?? 'login';
$old = $_SESSION['old_input'] ?? [];

unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['active_form']);

function showError($error) {
    return !empty($error) ? "<p class='error-message'>" . htmlspecialchars($error) . "</p>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}

// Load faculties from the database for the registration dropdown.
$facultyListIndex = [];
$facultyResultIndex = $conn->query("SELECT faculty_name FROM faculty ORDER BY faculty_id");
while ($f = $facultyResultIndex->fetch_assoc()) {
    $facultyListIndex[] = $f['faculty_name'];
}

// Load courses grouped by faculty, to hand to script.js for the
// "select faculty -> show matching courses" dropdown behavior.
$coursesForJS = [];
$courseResultIndex = $conn->query(
    "SELECT course.course_name, faculty.faculty_name
     FROM course
     JOIN faculty ON course.faculty_id = faculty.faculty_id
     ORDER BY faculty.faculty_id, course.course_name"
);
while ($c = $courseResultIndex->fetch_assoc()) {
    $coursesForJS[$c['faculty_name']][] = $c['course_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management Record System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        p a { color: #FFD700; }
        h2 {
            background-color: rgba(3, 17, 68, 0.85);
            padding: 1px;
            padding-bottom: 5px;
            border-radius: 5px;
            color: #FFD700;
            font-family: 'Georgia', serif;
        }
        .font {
            font-family: "Georgia", serif;
            font-optical-sizing: auto;
            font-weight: 200;
            font-style: normal;
        }
    </style>
</head>

<body class="login-page">
<div class="university-header">
    <img src="logo.png" alt="Logo">
    <h2>B&S University</h2>
</div>

<div class="container" style="margin-top: 40px;">

    <!-- LOGIN FORM -->
    <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
        <form action="login_register.php" method="POST" autocomplete="off">
            <h2 class="font">Login Form</h2>
            <?= showError($errors['login']); ?>
            <input type="email" name="email" placeholder="Email" autocomplete="off" required>
            <input type="password" name="password" placeholder="Password"  autocomplete="new-password" required>
            <button type="submit" name="login">Login</button>
            <p>Don't have an account? <a href="#" onclick="showForm('register-form')">Register</a></p>
        </form>
    </div>

    <!-- REGISTRATION FORM -->
    <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
        <form action="login_register.php" method="POST" autocomplete="off">
            <h2 class="font">Registration Form</h2>
            <style>
                .university-header { background: #031144; }
            </style>
            <?= showError($errors['register']); ?>
            <input type="text" name="name" placeholder="Full Name" autocomplete="off" value="<?= $old['name'] ?? ''; ?>" required>
            <input type="email" name="email" placeholder="Email" autocomplete="off" value="<?= $old['email'] ?? ''; ?>" required>
            <input type="password" name="password" placeholder="Password"  autocomplete="new-password" required>
            <input type="text" name="phone" placeholder="Personal Phone Number" autocomplete="off" value="<?= $old['phone'] ?? ''; ?>" minlength="10" maxlength="10" required>
            <input type="text" name="parent_name" placeholder="Parent's Name" autocomplete="off" value="<?= $old['parent_name'] ?? ''; ?>" required>
            <input type="text" name="parent_phone" placeholder="Parent's Phone Number" autocomplete="off" value="<?= $old['parent_phone'] ?? ''; ?>" minlength="10" maxlength="10" required>

            <select name="faculty" id="faculty" onchange="showCourses()" required>
                <option value="" disabled <?= empty($old['faculty']) ? 'selected' : ''; ?>>Select Faculty</option>
                <?php foreach ($facultyListIndex as $fac): ?>
                    <option value="<?= htmlspecialchars($fac); ?>" <?= ($old['faculty'] ?? '') == $fac ? 'selected' : ''; ?>><?= htmlspecialchars($fac); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="course" id="course" data-old-course="<?= $old['course'] ?? ''; ?>" required>
                <option value="" disabled selected>Select Course</option>
            </select>

            <input type="text" name="dob" placeholder="Date of Birth (YYYY-MM-DD)" autocomplete="off" value="<?= $old['dob'] ?? ''; ?>" required>

            <!-- GENDER FIELD (new) -->
            <select name="gender" required>
                <option value="" disabled <?= empty($old['gender']) ? 'selected' : ''; ?>>Select Gender</option>
                <option value="Male" <?= ($old['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?= ($old['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?= ($old['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>

            <input type="text" name="address" placeholder="Address" autocomplete="off" value="<?= $old['address'] ?? ''; ?>" required>
            <input type="hidden" name="role" value="student">
            <button type="submit" name="register">Register</button>
            <p>Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
        </form>
    </div>

</div>
<script>
    // Built from the database (faculty/course tables) - see top of index.php.
    // script.js reads this instead of having courses hardcoded.
    var coursesData = <?= json_encode($coursesForJS); ?>;
</script>
<script src="script.js"></script>
</body>
</html>s