<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

$adminEmail  = $_SESSION['email'];
$adminStmt = $conn->prepare("SELECT name, role FROM students WHERE email = ?");
$adminStmt->bind_param("s", $adminEmail);
$adminStmt->execute();
$adminResult = $adminStmt->get_result();
$adminRow    = $adminResult->fetch_assoc();

// Only admins may use this page
if (!$adminRow || $adminRow['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$adminName = $adminRow['name'];
// =====================
// DELETE STUDENT
// =====================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
$enrStmt = $conn->prepare("DELETE FROM enrollment WHERE student_id = ? AND student_id IN (SELECT id FROM students WHERE role = 'student')");
$enrStmt->bind_param("i", $id);
$enrStmt->execute();

$delStmt = $conn->prepare("DELETE FROM students WHERE id = ? AND role = 'student'");
$delStmt->bind_param("i", $id);
$delStmt->execute();
    header("Location: admin_page.php");
    exit();
}

// =====================
// EDIT STUDENT DETAILS
// =====================
if (isset($_POST['edit_submit'])) {
    $id           = intval($_POST['id']);
    $name         = $_POST['name'];
    $email        = trim($_POST['email']);
    $phone        = $_POST['phone'];
    $gender       = $_POST['gender'];
    $parent_name  = $_POST['parent_name'];
    $parent_phone = $_POST['parent_phone'];
    $address      = $_POST['address'];
    $dob          = $_POST['dob'];
    $new_faculty  = $_POST['new_faculty'];
    $new_course   = $_POST['new_course'];
    $duration     = trim($_POST['duration'] ?? '');

    $redirectFaculty = $_POST['redirect_faculty'];
    $redirectCourse  = $_POST['redirect_course'];

    function editError($msg, $faculty, $course) {
        $_SESSION['update_error'] = $msg;
        header("Location: admin_page.php?faculty=" . urlencode($faculty) . "&course=" . urlencode($course));
        exit();
    }

    if (!preg_match("/^[a-zA-Z\s]+$/", $name))        editError("Name should contain only letters!", $redirectFaculty, $redirectCourse);
    if (!preg_match("/^[a-zA-Z\s]+$/", $parent_name)) editError("Parent's name should contain only letters!", $redirectFaculty, $redirectCourse);
    if (!preg_match("/^[0-9+\-]+$/", $phone))         editError("Phone must contain only numbers!", $redirectFaculty, $redirectCourse);
    if (!preg_match("/^[0-9+\-]+$/", $parent_phone))  editError("Parent phone must contain only numbers!", $redirectFaculty, $redirectCourse);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    editError("Please enter a valid email!", $redirectFaculty, $redirectCourse);

    $emailCheck = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
    $emailCheck->bind_param("si", $email, $id);
    $emailCheck->execute();
    if ($emailCheck->get_result()->num_rows > 0) editError("That email is already used by another student!", $redirectFaculty, $redirectCourse);

    $stmt = $conn->prepare("UPDATE students SET name=?, email=?, phone=?, gender=?, parent_name=?, parent_phone=?, address=?, DOB=?, faculty=?, course=? WHERE id=?");
    $stmt->bind_param("ssssssssssi", $name, $email, $phone, $gender, $parent_name, $parent_phone, $address, $dob, $new_faculty, $new_course, $id);
    $stmt->execute();

    // Save duration to course table if provided
    if ($duration !== '') {
        $dStmt = $conn->prepare("UPDATE course SET duration = ? WHERE course_name = ?");
        $dStmt->bind_param("ss", $duration, $new_course);
        $dStmt->execute();
    }

    header("Location: admin_page.php?faculty=" . urlencode($new_faculty) . "&course=" . urlencode($new_course));
    exit();
}

// =====================
// UPDATE ROLL & ROOM
// =====================
if (isset($_POST['update'])) {
    $id      = intval($_POST['id']);
    $roll    = $_POST['roll_number'];
    $room    = $_POST['room_number'];
    $faculty = $_POST['faculty'];
    $course  = $_POST['course'];

    if ($roll !== '') {
        try {
            $rollStmt = $conn->prepare("UPDATE students SET roll_number=? WHERE id=?");
            $rollStmt->bind_param("si", $roll, $id);
            $rollStmt->execute();
        } catch (mysqli_sql_exception $e) {
            $_SESSION['update_error'] = $e->getCode() == 1062
                ? "Roll number '$roll' is already used by another student."
                : "Something went wrong while updating the roll number.";
            header("Location: admin_page.php?faculty=" . urlencode($faculty) . "&course=" . urlencode($course));
            exit();
        }
    }

    if ($room !== '') {
       $roomStmt = $conn->prepare("UPDATE students SET room_number = ? WHERE course = ?");
$roomStmt->bind_param("ss", $room, $course);
$roomStmt->execute();
    } else {
        $existStmt = $conn->prepare("SELECT room_number FROM students WHERE course = ? AND room_number IS NOT NULL AND room_number != '' LIMIT 1");
$existStmt->bind_param("s", $course);
$existStmt->execute();
$existing = $existStmt->get_result();
        if ($existing && $existing->num_rows > 0) {
            $row = $existing->fetch_assoc();
            $roomStmt2 = $conn->prepare("UPDATE students SET room_number = ? WHERE id = ?");
$roomStmt2->bind_param("si", $row['room_number'], $id);
$roomStmt2->execute();
        }
    }

    header("Location: admin_page.php?faculty=" . urlencode($faculty) . "&course=" . urlencode($course));
    exit();
}

// =====================
// LOAD DATA
// =====================
$facultyList   = [];
$facultyResult = $conn->query("SELECT faculty_name FROM faculty ORDER BY faculty_id");
while ($f = $facultyResult->fetch_assoc()) $facultyList[] = $f['faculty_name'];

$coursesList  = [];
$courseResult = $conn->query(
    "SELECT course.course_name, faculty.faculty_name FROM course
     JOIN faculty ON course.faculty_id = faculty.faculty_id
     ORDER BY faculty.faculty_id, course.course_name"
);
while ($c = $courseResult->fetch_assoc()) $coursesList[$c['faculty_name']][] = $c['course_name'];

$selectedFaculty = $_GET['faculty']       ?? '';
$selectedCourse  = $_GET['course']        ?? '';
$search          = $_GET['search']        ?? '';
$courseSearch    = $_GET['course_search'] ?? '';
$fromSearch      = $_GET['from_search']   ?? '';
$viewAll         = isset($_GET['view_all']);

// Students for selected course
$students = null;
if ($selectedFaculty != '' && $selectedCourse != '') {
    if ($courseSearch != '') {
        $like = "%$courseSearch%";
        $sql  = "SELECT * FROM students WHERE role='student' AND faculty=? AND course=? AND (name LIKE ? OR email LIKE ?)
                 ORDER BY CASE WHEN roll_number IS NULL OR roll_number='' THEN 1 ELSE 0 END, CAST(roll_number AS UNSIGNED), id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $selectedFaculty, $selectedCourse, $like, $like);
    } else {
        $sql  = "SELECT * FROM students WHERE role='student' AND faculty=? AND course=?
                 ORDER BY CASE WHEN roll_number IS NULL OR roll_number='' THEN 1 ELSE 0 END, CAST(roll_number AS UNSIGNED), id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $selectedFaculty, $selectedCourse);
    }
    $stmt->execute();
    $students = $stmt->get_result();

    // Fetch duration for this course to prefill edit modal
    $durStmt = $conn->prepare("SELECT duration FROM course WHERE course_name = ?");
    $durStmt->bind_param("s", $selectedCourse);
    $durStmt->execute();
    $durRow = $durStmt->get_result()->fetch_assoc();
    $selectedCourseDuration = $durRow['duration'] ?? '';
}

// All students ledger
$allStudents = null;
if ($viewAll) {
    if ($search != '') {
        $like    = "%$search%";
        $stmtAll = $conn->prepare("SELECT id, name, email, faculty, course, roll_number FROM students WHERE role='student' AND (name LIKE ? OR email LIKE ?) ORDER BY faculty, course, id");
        $stmtAll->bind_param("ss", $like, $like);
        $stmtAll->execute();
        $allStudents = $stmtAll->get_result();
    } else {
        $allStudents = $conn->query("SELECT id, name, email, faculty, course, roll_number FROM students WHERE role='student' ORDER BY faculty, course, id");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Page</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html, body {
            overflow: hidden;
            height: 100%;
        }

        .university-header { background: #031144; }

        /* Scrollable content area below fixed header */
        .admin-box {
            height: calc(100vh - 100px);
            overflow-y: auto;
            overflow-x: hidden;
            width: 98%;
            max-width: 100%;
            margin: 0 auto;
            padding: 0 10px 40px;
            font-family: 'Georgia', serif;
            box-sizing: border-box;
        }

        .admin-box h1 {
            font-size: 32px;
            font-weight: normal;
            text-align: center;
            margin: 10px 0 6px;
        }

        .admin-box h1 span { color: #031144; font-weight: bold; }

        /* ── Error toast ── */
        .admin-box .error-message {
            position: fixed !important;
            top: 160px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 500;
            max-width: 90%;
            width: auto;
            white-space: nowrap;
            margin: 0 !important;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            animation: fadeOutError 6s forwards;
        }

        @keyframes fadeOutError {
            0%   { opacity: 1; }
            80%  { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }

        /* ── Page subtitle & nav ── */
        .page-subtitle  { margin: 6px 0 4px !important; }
        .back-links-row { margin-bottom: 6px; }
        .search-bar     { margin: 4px 0 6px !important; }

        /* ── Tables ── */
        .table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            max-height: 380px;
            background: #fffdf8;
            border-radius: 4px;
            box-shadow: 0 6px 24px rgba(3,17,68,0.12);
            border: 1px solid #d8c9a3;
        }

        .student-table { width: 100%; min-width: 100%; }

        .student-table th:first-child { min-width: 55px; color: #D4AF37; }

        .student-table td:last-child,
        .student-table th:last-child  { padding-right: 20px; min-width: 120px; }

        .student-table th:nth-child(9),
        .student-table th:nth-child(10) { width: 70px; min-width: 70px; }

        /* ── "Select a Faculty" heading ── */
        .faculty-heading {
            text-align: center;
            font-family: 'Georgia', serif;
            font-size: 26px;
            font-weight: bold;
            color: #031144;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin: 18px 0 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #D4AF37;
        }

        /* ── View All button centered ── */
        .view-all-wrap {
            text-align: center;
            margin-bottom: 18px;
        }

        .view-all-wrap button {
            width: auto;
            padding: 12px 30px;
            font-family: 'Georgia', serif;
            font-size: 16px;
            letter-spacing: 1px;
        }
.manage-faculty-card:hover {
      border-top: 4px solid #D4AF37;
    box-shadow: 0 6px 18px rgba(3, 17, 68, 0.18);
    background: #031144;
    color: #D4AF37;
}

    </style>
</head>
<body class="admin-locked" style="padding-top: 100px;">

<!-- ====== HEADER ====== -->
<div class="university-header">
    <img src="logo.png" alt="Logo">
    <?php if ($fromSearch): ?>
        <div style="position:absolute; left:24px; top:50%; transform:translateY(-50%);">
            <a href="admin_page.php"><button class="header-btn">&#8592; Back</button></a>
        </div>
    <?php endif; ?>
    <h2>B&S University</h2>
    <div class="header-links">
        <a href="logout.php"><button class="header-btn">Logout</button></a>
    </div>
</div>

<div class="admin-box">

    <h1>Welcome, <span><?= htmlspecialchars($adminName); ?></span></h1>

    <?php if (isset($_SESSION['update_error'])): ?>
        <p class="error-message"><?= htmlspecialchars($_SESSION['update_error']); ?></p>
        <?php unset($_SESSION['update_error']); ?>
    <?php endif; ?>

    <!-- ====================================================== -->
    <?php if ($selectedFaculty == ''): ?>
    <!-- ====================================================== -->

        <?php if ($viewAll): ?>
            <!-- STEP 1b: All Registered Students ledger -->

            <p class="page-subtitle">
                <?= $search != '' ? 'Search Results for "' . htmlspecialchars($search) . '"' : 'All Registered Students'; ?>
            </p>

            <div class="back-links-row">
                <a href="admin_page.php" class="back-link">&larr; Back to Faculties</a>
            </div>

            <form action="admin_page.php" method="GET" autocomplete="off">
                <input type="hidden" name="view_all" value="1">
                <div class="search-bar">
                    <input type="text" name="search" placeholder="Search student by name or email" value="<?= htmlspecialchars($search); ?>" autocomplete="off">
                    <button type="submit">Search</button>
                    <?php if ($search != ''): ?>
                        <a href="admin_page.php?view_all=1"><button type="button">Clear</button></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-wrap">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>S.N</th><th>Name</th><th>Email</th><th>Roll No</th><th>Faculty</th><th>Course</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($allStudents && $allStudents->num_rows > 0): ?>
                            <?php $sn = 1; while ($s = $allStudents->fetch_assoc()): ?>
                            <tr>
                                <td><?= $sn++; ?></td>
                                <td><?= htmlspecialchars($s['name']); ?></td>
                                <td><?= htmlspecialchars($s['email']); ?></td>
                                <td><?= htmlspecialchars($s['roll_number'] ?? '—'); ?></td>
                                <td><?= htmlspecialchars($s['faculty']); ?></td>
                                <td><?= htmlspecialchars($s['course']); ?></td>
                                <td>
                                    <a href="admin_page.php?faculty=<?= urlencode($s['faculty']); ?>&course=<?= urlencode($s['course']); ?>&from_search=1">
                                        <button type="button" class="btn-update">View Details</button>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;">No students found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- STEP 1a: Faculty selection -->

            <div class="view-all-wrap">
                <a href="admin_page.php?view_all=1">
                    <button type="button">View All Registered Students</button>
                </a>
            </div>

            <h3 class="faculty-heading">&nbsp; Select a Faculty &nbsp;</h3>

            <div class="card-grid">
                <!-- Manage Faculty card pinned first -->
            <a href="manage_faculty.php" class="faculty-card manage-faculty-card">
    &#9998;&nbsp; Manage Faculty
</a>

                <?php foreach ($facultyList as $fac): ?>
                    <a href="admin_page.php?faculty=<?= urlencode($fac); ?>" class="faculty-card">
                        <?= htmlspecialchars($fac); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- ====================================================== -->
    <?php elseif ($selectedCourse == ''): ?>
    <!-- STEP 2: Course cards for selected faculty -->
    <!-- ====================================================== -->

        <p class="page-subtitle"><?= htmlspecialchars($selectedFaculty); ?> — Select a Course</p>
        <div class="back-links-row">
            <a href="admin_page.php" class="back-link">&larr; Back to Faculties</a>
        </div>

        <div class="card-grid">
            <?php if (!empty($coursesList[$selectedFaculty])): ?>
                <?php foreach ($coursesList[$selectedFaculty] as $course): ?>
                    <a href="admin_page.php?faculty=<?= urlencode($selectedFaculty); ?>&course=<?= urlencode($course); ?>" class="course-card">
                        <?= htmlspecialchars($course); ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#aaa;">No courses added yet for this faculty.</p>
            <?php endif; ?>
        </div>

    <!-- ====================================================== -->
    <?php else: ?>
    <!-- STEP 3: Students table for selected course -->
    <!-- ====================================================== -->

        <p class="page-subtitle"><?= htmlspecialchars($selectedFaculty); ?> — <?= htmlspecialchars($selectedCourse); ?></p>

        <div class="back-links-row">
            <a href="admin_page.php?faculty=<?= urlencode($selectedFaculty); ?>" class="back-link">&larr; Back to Courses</a>
            <a href="admin_page.php" class="back-link">&larr; Back to Faculties</a>
        </div>

        <form action="admin_page.php" method="GET" autocomplete="off">
            <input type="hidden" name="faculty" value="<?= htmlspecialchars($selectedFaculty); ?>">
            <input type="hidden" name="course"  value="<?= htmlspecialchars($selectedCourse); ?>">
            <div class="search-bar">
                <input type="text" name="course_search" placeholder="Search student by name or email" value="<?= htmlspecialchars($courseSearch); ?>" autocomplete="off">
                <button type="submit">Search</button>
                <?php if ($courseSearch != ''): ?>
                    <a href="admin_page.php?faculty=<?= urlencode($selectedFaculty); ?>&course=<?= urlencode($selectedCourse); ?>">
                        <button type="button">Clear</button>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>S.N</th><th>Name</th><th>Email</th><th>Phone</th>
                        <th>Parent Name</th><th>Parent Phone</th><th>Address</th>
                        <th>Date of Birth</th><th>Roll No</th><th>Room No</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students && $students->num_rows > 0): ?>
                        <?php $sn = 1; while ($student = $students->fetch_assoc()): ?>
                        <tr>
                            <td><?= $sn++; ?></td>
                            <td><?= htmlspecialchars($student['name']); ?></td>
                            <td><?= htmlspecialchars($student['email']); ?></td>
                            <td><?= htmlspecialchars($student['phone']); ?></td>
                            <td><?= htmlspecialchars($student['parent_name']); ?></td>
                            <td><?= htmlspecialchars($student['parent_phone']); ?></td>
                            <td><?= htmlspecialchars($student['address']); ?></td>
                            <td><?= htmlspecialchars($student['DOB']); ?></td>
                            <td>
                                <input type="text" id="roll-<?= $student['id']; ?>" value="<?= htmlspecialchars($student['roll_number'] ?? ''); ?>" placeholder="Roll No">
                            </td>
                            <td>
                                <input type="text" id="room-<?= $student['id']; ?>" value="<?= htmlspecialchars($student['room_number'] ?? ''); ?>" placeholder="Room No">
                            </td>
                            <td>
                                <div class="action-cell">
                                    <button type="button" class="btn-update" onclick="updateStudent(<?= $student['id']; ?>)">Update</button>
                                    <button type="button" class="btn-menu" onclick="toggleMenu(this)">&#8942;</button>
                                    <div class="action-dropdown">
                                        <button type="button" class="edit-option" onclick="openEdit(
                                            <?= $student['id']; ?>,
                                            '<?= htmlspecialchars(addslashes($student['name'])); ?>',
                                            '<?= htmlspecialchars(addslashes($student['email'])); ?>',
                                            '<?= htmlspecialchars(addslashes($student['phone'])); ?>',
                                            '<?= htmlspecialchars(addslashes($student['gender'] ?? '')); ?>',
                                            '<?= htmlspecialchars(addslashes($student['parent_name'])); ?>',
                                            '<?= htmlspecialchars(addslashes($student['parent_phone'])); ?>',
                                            '<?= htmlspecialchars(addslashes($student['address'])); ?>',
                                            '<?= htmlspecialchars(addslashes($student['DOB'])); ?>',
                                            '<?= htmlspecialchars(addslashes($student['faculty'])); ?>',
                                            '<?= htmlspecialchars(addslashes($student['course'])); ?>',
                                            '<?= htmlspecialchars(addslashes($selectedCourseDuration)); ?>'
                                        )">Edit</button>
                                        <a href="admin_page.php?delete=<?= $student['id']; ?>&faculty=<?= urlencode($selectedFaculty); ?>&course=<?= urlencode($selectedCourse); ?>"
                                           onclick="return confirm('Delete this student?')" style="text-decoration:none;">
                                            <button type="button" class="delete-option">Delete</button>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="11" style="text-align:center;">No students found in this course.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Hidden update form -->
        <form id="update-form" action="admin_page.php" method="POST">
            <input type="hidden" id="hidden-id"   name="id">
            <input type="hidden" id="hidden-roll"  name="roll_number">
            <input type="hidden" id="hidden-room"  name="room_number">
            <input type="hidden" name="faculty"    value="<?= htmlspecialchars($selectedFaculty); ?>">
            <input type="hidden" name="course"     value="<?= htmlspecialchars($selectedCourse); ?>">
            <input type="hidden" name="update"     value="1">
        </form>

        <!-- Edit Student Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <h3>Edit Student Details</h3>
                <form action="admin_page.php" method="POST" autocomplete="off">
                    <input type="hidden" id="edit-id" name="id">
                    <input type="hidden" name="redirect_faculty" value="<?= htmlspecialchars($selectedFaculty); ?>">
                    <input type="hidden" name="redirect_course"  value="<?= htmlspecialchars($selectedCourse); ?>">

                    <label>Name</label>
                    <input type="text"  id="edit-name"         name="name"         autocomplete="off" required>

                    <label>Email</label>
                    <input type="email" id="edit-email"        name="email"        autocomplete="off" required>

                    <label>Phone</label>
                    <input type="text"  id="edit-phone"        name="phone"        minlength="10" maxlength="10" autocomplete="off" required>

                    <label>Gender</label>
                    <select id="edit-gender" name="gender" required>
                        <option value="" disabled>Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>

                    <label>Parent's Name</label>
                    <input type="text"  id="edit-parent-name"  name="parent_name"  autocomplete="off" required>

                    <label>Parent's Phone</label>
                    <input type="text"  id="edit-parent-phone" name="parent_phone" minlength="10" maxlength="10" autocomplete="off" required>

                    <label>Address</label>
                    <input type="text"  id="edit-address"      name="address"      autocomplete="off" required>

                    <label>Date of Birth</label>
                    <input type="text"  id="edit-dob"          name="dob"          placeholder="YYYY-MM-DD" autocomplete="off" required>

                    <label>Faculty</label>
                    <select id="edit-new-faculty" name="new_faculty" onchange="updateEditCourseOptions()" required>
                        <option value="" disabled>Select Faculty</option>
                        <?php foreach ($facultyList as $fac): ?>
                            <option value="<?= htmlspecialchars($fac); ?>"><?= htmlspecialchars($fac); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Course</label>
                    <select id="edit-new-course" name="new_course" required>
                        <option value="" disabled>Select Course</option>
                    </select>

                    <label>Course Duration</label>
                    <input type="text" id="edit-duration" name="duration" placeholder="e.g. 4 Years" autocomplete="off">

                    <button type="submit" name="edit_submit">Save Changes</button>
                    <button type="button" class="btn-close" onclick="closeEdit()">Cancel</button>
                </form>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="script.js"></script>
<script>
function updateStudent(id) {
    document.getElementById('hidden-id').value   = id;
    document.getElementById('hidden-roll').value = document.getElementById('roll-' + id).value;
    document.getElementById('hidden-room').value = document.getElementById('room-' + id).value;
    document.getElementById('update-form').submit();
}

var editCoursesData = <?= json_encode($coursesList); ?>;

function updateEditCourseOptions(preselectCourse) {
    var faculty   = document.getElementById('edit-new-faculty').value;
    var courseBox = document.getElementById('edit-new-course');
    courseBox.innerHTML = '<option value="" disabled>Select Course</option>';
    if (editCoursesData[faculty]) {
        editCoursesData[faculty].forEach(function(course) {
            var opt   = document.createElement('option');
            opt.value = course;
            opt.text  = course;
            if (preselectCourse && course === preselectCourse) opt.selected = true;
            courseBox.appendChild(opt);
        });
    }
}

function openEdit(id, name, email, phone, gender, parentName, parentPhone, address, dob, faculty, course, duration) {
    document.getElementById('edit-id').value           = id;
    document.getElementById('edit-name').value         = name;
    document.getElementById('edit-email').value        = email;
    document.getElementById('edit-phone').value        = phone;
    document.getElementById('edit-gender').value       = gender;
    document.getElementById('edit-parent-name').value  = parentName;
    document.getElementById('edit-parent-phone').value = parentPhone;
    document.getElementById('edit-address').value      = address;
    document.getElementById('edit-dob').value          = dob;
    document.getElementById('edit-new-faculty').value  = faculty;
    document.getElementById('edit-duration').value     = duration || '';
    updateEditCourseOptions(course);
    document.getElementById('editModal').style.display = 'flex';
}

function closeEdit() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

</body>
</html>