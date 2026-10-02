<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

// =============================================
// LOAD ALL FACULTY NAMES (for the dropdown)
// =============================================
$allFacultyNames = [];
$facultyResult = $conn->query("SELECT faculty_name FROM faculty ORDER BY faculty_id");
while ($row = $facultyResult->fetch_assoc()) {
    $allFacultyNames[] = $row['faculty_name'];
}

// =============================================
// LOAD ALL COURSES grouped by faculty
// Each course has: name, duration, and nature (Semester/Year)
// =============================================
$coursesList = [];
$courseResult = $conn->query(
    "SELECT course.course_name, course.duration, course.type_nature, faculty.faculty_name
     FROM course
     JOIN faculty ON course.faculty_id = faculty.faculty_id
     ORDER BY faculty.faculty_id, course.course_id"
);
while ($row = $courseResult->fetch_assoc()) {
    $coursesList[$row['faculty_name']][] = [
        'name'     => $row['course_name'],
        'duration' => $row['duration'] ?? '',
        'nature'   => $row['type_nature'] ?? '',
    ];
}

// =============================================
// LOAD FACULTY + COURSES for the card grid view
// =============================================
$faculties = $conn->query("SELECT * FROM faculty ORDER BY faculty_id");
$coursesByFacultyId = [];
$allCoursesResult = $conn->query(
    "SELECT course.*, faculty.faculty_id AS fid FROM course
     JOIN faculty ON course.faculty_id = faculty.faculty_id
     ORDER BY course.faculty_id, course.course_id"
);
while ($c = $allCoursesResult->fetch_assoc()) {
    $coursesByFacultyId[$c['fid']][] = $c;
}

// =============================================
// ADD NEW COURSE
// =============================================
if (isset($_POST['add_course'])) {
    $faculty     = $_POST['faculty']            ?? '';
    $course_name = trim($_POST['new_course_name']     ?? '');
    $duration    = trim($_POST['new_course_duration'] ?? '');
    $nature      = trim($_POST['new_course_nature']   ?? '');

    if ($faculty != '' && $course_name != '') {
        // Get the faculty_id from the faculty name
        $facStmt = $conn->prepare("SELECT faculty_id FROM faculty WHERE faculty_name = ?");
        $facStmt->bind_param("s", $faculty);
        $facStmt->execute();
        $facRow = $facStmt->get_result()->fetch_assoc();

        if ($facRow) {
            $faculty_id = $facRow['faculty_id'];

            // Check if course name already exists
            $dupStmt = $conn->prepare("SELECT course_id FROM course WHERE course_name = ?");
            $dupStmt->bind_param("s", $course_name);
            $dupStmt->execute();

            if ($dupStmt->get_result()->num_rows == 0) {
                // Insert the new course with duration and nature
                $insertStmt = $conn->prepare("INSERT INTO course (course_name, faculty_id, duration, type_nature) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param("siss", $course_name, $faculty_id, $duration, $nature);
                $insertStmt->execute();
                header("Location: subjects.php?faculty=" . urlencode($faculty) . "&course=" . urlencode($course_name));
                exit();
            } else {
                $_SESSION['manage_error'] = "A course named \"$course_name\" already exists.";
            }
        }
    }
    header("Location: subjects.php?faculty=" . urlencode($faculty));
    exit();
}

// =============================================
// UPDATE COURSE NAME AND DURATION TOGETHER
// =============================================
if (isset($_POST['update_course_row'])) {
    $old_name     = trim($_POST['old_course_name']        ?? '');
    $new_name     = trim($_POST['new_course_name_rename'] ?? '');
    $new_duration = trim($_POST['new_duration']           ?? '');
    $new_nature   = trim($_POST['new_nature']             ?? '');
    $faculty      = $_POST['faculty']                     ?? '';

    if ($old_name != '' && $new_name != '') {
        // If name changed, check for duplicate and update subjects table too
        if ($old_name !== $new_name) {
            $dupStmt = $conn->prepare("SELECT course_id FROM course WHERE course_name = ?");
            $dupStmt->bind_param("s", $new_name);
            $dupStmt->execute();

            if ($dupStmt->get_result()->num_rows > 0) {
                $_SESSION['manage_error'] = "A course named \"$new_name\" already exists.";
                header("Location: subjects.php?faculty=" . urlencode($faculty));
                exit();
            }

            // Update subjects table so they still link to the renamed course
            $subStmt = $conn->prepare("UPDATE subjects SET course=? WHERE course=?");
            $subStmt->bind_param("ss", $new_name, $old_name);
            $subStmt->execute();
        }

        // Update course name, duration, and nature together in one query
        $updateStmt = $conn->prepare("UPDATE course SET course_name=?, duration=?, type_nature=? WHERE course_name=?");
        $updateStmt->bind_param("ssss", $new_name, $new_duration, $new_nature, $old_name);
        $updateStmt->execute();
    }
    header("Location: subjects.php?faculty=" . urlencode($faculty));
    exit();
}

// =============================================
// DELETE COURSE
// =============================================
if (isset($_POST['delete_course'])) {
    $course_name = trim($_POST['course_to_delete'] ?? '');
    $faculty     = $_POST['faculty']               ?? '';

    if ($course_name != '') {
        $delStmt = $conn->prepare("DELETE FROM course WHERE course_name=?");
        $delStmt->bind_param("s", $course_name);
        $delStmt->execute();
    }
    header("Location: subjects.php?faculty=" . urlencode($faculty));
    exit();
}

// =============================================
// ADD SUBJECT
// =============================================
if (isset($_POST['add'])) {
    $course       = $_POST['course']       ?? '';
    $faculty      = $_POST['faculty']      ?? '';
    $subject_name = $_POST['subject_name'] ?? '';
    $subject_code = $_POST['subject_code'] ?? '';
    $teacher_name = $_POST['teacher_name'] ?? '';

    $conn->query("INSERT INTO subjects (course, subject_name, subject_code, teacher_name)
                  VALUES ('$course', '$subject_name', '$subject_code', '$teacher_name')");
    header("Location: subjects.php?faculty=" . urlencode($faculty) . "&course=" . urlencode($course));
    exit();
}

// =============================================
// UPDATE SUBJECT
// =============================================
if (isset($_POST['update'])) {
    $id           = intval($_POST['id']);
    $subject_name = $_POST['subject_name'];
    $subject_code = $_POST['subject_code'];
    $teacher_name = $_POST['teacher_name'];
    $course       = $_POST['course'];
    $faculty      = $_POST['faculty'];

    $conn->query("UPDATE subjects SET
        subject_name='$subject_name',
        subject_code='$subject_code',
        teacher_name='$teacher_name'
        WHERE id='$id'");
    header("Location: subjects.php?faculty=" . urlencode($faculty) . "&course=" . urlencode($course));
    exit();
}

// =============================================
// DELETE SUBJECT
// =============================================
if (isset($_GET['delete'])) {
    $id      = intval($_GET['delete']);
    $course  = $_GET['course']  ?? '';
    $faculty = $_GET['faculty'] ?? '';

    $conn->query("DELETE FROM subjects WHERE id='$id'");
    header("Location: subjects.php?faculty=" . urlencode($faculty) . "&course=" . urlencode($course));
    exit();
}

// =============================================
// GET SELECTED FACULTY AND COURSE FROM URL
// =============================================
$selectedFaculty = $_GET['faculty'] ?? '';
$selectedCourse  = $_GET['course']  ?? '';
$subjects        = null;
$currentNature   = '';

// Helper: shows "I Semester" or "I Year" label
function natureLabel($nature) {
    if ($nature === 'Semester') return 'I Semester';
    if ($nature === 'Year')     return 'I Year';
    return $nature;
}

// Load subjects and course type if a course is selected
if ($selectedFaculty != '' && $selectedCourse != '') {
    $subStmt = $conn->prepare("SELECT * FROM subjects WHERE course=? ORDER BY id ASC");
    $subStmt->bind_param("s", $selectedCourse);
    $subStmt->execute();
    $subjects = $subStmt->get_result();

    $natureResult = $conn->query("SELECT type_nature FROM course WHERE course_name='$selectedCourse'");
    if ($natureResult && $natureResult->num_rows > 0) {
        $currentNature = $natureResult->fetch_assoc()['type_nature'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Course</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .university-header { background: #031144; }

.subjects-box {
    max-width: 1100px;
    width: 98%;
    margin: 0 auto;
    padding: 100px 24px 20px;
    font-family: 'Georgia', serif;
}

        /* Row of dropdowns at the top */
        .dropdowns-row {
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
        .dropdowns-row select {
            width: auto;
            min-width: 200px;
            margin-bottom: 0;
        }

        /* Row of inputs side by side */
        .input-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .input-row input {
            flex: 1;
            min-width: 150px;
            margin-bottom: 0;
        }
       .input-row select {
    flex: 1;
    min-width: 150px;
    margin-bottom: 0;
    padding: 12px 10px;
}
        .input-row button {
            width: auto;
            padding: 12px 20px;
            margin-bottom: 0;
        }

        /* Course list table */
        .course-list-table {
            width: 100%;
            border-collapse: collapse;
            background: #fffdf8;
            border: 1px solid #d8c9a3;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 28px;
            box-shadow: 0 4px 14px rgba(3,17,68,0.08);
            font-family: 'Georgia', serif;
        }
        .course-list-table th {
            background: #031144;
            color: #D4AF37;
            padding: 12px 16px;
            text-align: left;
            font-size: 14px;
        }
        .course-list-table td {
            padding: 11px 16px;
            border-bottom: 1px solid #ece3cc;
            font-size: 15px;
            vertical-align: middle;
        }
        .course-list-table tbody tr:nth-child(even) { background: #fbf8ef; }
        .course-list-table input[type="text"] {
            padding: 8px 8px;
            font-size: 15px;
            border: 1px solid #d8c9a3;
            border-radius: 5px;
            background: #fff;
            font-family: 'Georgia', serif;
            margin-bottom: 0;
            width: 100%;
        }
        .course-list-table select {
    display: block;
    width: 200px;
    margin: 10px;
    padding: 8px 16px;
    font-size: 15px;
    border: 1px solid #d8c9a3;
    border-radius: 5px;
    background: #fff;
    font-family: 'Georgia', serif;
}

        /* Blue update button */
        .btn-save {
            background: #7494ec;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 13px;
            cursor: pointer;
            margin-bottom: 0;
            width: 100%;
            white-space: nowrap;
        }
        .btn-save:hover { background: #6884d3; }

        /* Red delete button */
        .btn-delete-course {
            background: #d64550;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 13px;
            cursor: pointer;
            margin-bottom: 0;
            width: 100%;
            white-space: nowrap;
        }
        .btn-delete-course:hover { background: #b83540; }

        /* Subjects table */
        .subjects-table {
            width: 100%;
            border-collapse: collapse;
            background: #fffdf8;
            border: 1px solid #d8c9a3;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 24px;
            box-shadow: 0 4px 14px rgba(3,17,68,0.08);
            font-family: 'Georgia', serif;
        }
        .subjects-table th {
            background: #031144;
            color: #D4AF37;
            padding: 14px 16px;
            text-align: left;
            font-size: 14px;
        }
        .subjects-table td {
            padding: 13px 16px;
            border-bottom: 1px solid #ece3cc;
            font-size: 15px;
            vertical-align: middle;
        }
        .subjects-table tbody tr:nth-child(even) { background: #fbf8ef; }
        .subjects-table input[type="text"] {
            width: 100%;
            padding: 8px 10px;
            font-size: 14px;
            border: 1px solid #d8c9a3;
            border-radius: 5px;
            background: #fff;
            font-family: 'Georgia', serif;
            margin-bottom: 0;
        }

        /* Error message */
        .notice {
            color: #a42834;
            font-weight: bold;
            margin-top: 16px;
            font-size: 15px;
        }
        .faculty-card-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 16px;
        }
        .faculty-card-grid > a {
            display: flex;
        }
        .faculty-view-card {
            background: #fffdf8;
            border: 1px solid #d8c9a3;
            border-radius: 8px;
            padding: 14px 16px;
            box-shadow: 0 2px 8px rgba(3,17,68,0.06);
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        .faculty-view-card:hover {
            transform: translateY(-3px);
            border-color: #D4AF37;
            box-shadow: 0 6px 18px rgba(3,17,68,0.15);
        }
        .faculty-active {
            border: 2px solid #031144;
            background: #eef2ff;
        }
        .faculty-view-card h4 {
            color: #031144;
            font-size: 16px;
            margin: 0 0 10px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #D4AF37;
        }
        .faculty-view-card ul {
            margin: 0;
            padding-left: 18px;
            list-style: disc;
        }
        .faculty-view-card li {
            font-size: 14px;
            color: #1c2233;
            padding: 3px 0;
        }
        .no-courses-note {
            color: #aaa;
            font-size: 13px;
            font-style: italic;
        }
        /* ── Shared page heading style (matches admin dashboard) ── */
        .faculty-heading {
            text-align: center;
            font-family: 'Georgia', serif;
            font-size: 26px;
            font-weight: bold;
            color: #031144;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin: 5px 0 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #D4AF37;
        }
        @media (max-width: 900px) {
            .faculty-card-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 560px) {
            .faculty-card-grid { grid-template-columns: 1fr; }
        }
        .change-link {
    font-size: 18px;
    font-weight: normal;
    color: #7494ec;
    margin-left: 8px;
    text-decoration: none;
    transition: color 0.15s ease;
}

.change-link:hover {
    color: #031144;
    text-decoration: underline;
}
    </style>
</head>
<body style="padding-top: 20px;">

<!-- ====== HEADER ====== -->
<div class="university-header">
    <img src="logo.png" alt="Logo">
    <h2>B&S University</h2>
    <div class="header-links">
        <a href="manage_faculty.php"><button class="header-btn">Back to Faculty</button></a>
    </div>
</div>

<!-- ====== MAIN CONTENT ====== -->
<div class="subjects-box"><h3 class="faculty-heading">Manage Course</h3>

    <!-- ====== COURSES BY FACULTY VIEW (shown only when no faculty selected yet) ====== -->
    <?php if ($selectedFaculty == ''): ?>
        <h3 class="faculty-heading" style="border-bottom: none;">Select a Faculty</h3>
        <?php $faculties->data_seek(0); if ($faculties && $faculties->num_rows > 0): ?>
        <div class="faculty-card-grid">
            <?php $faculties->data_seek(0); while ($f = $faculties->fetch_assoc()): ?>
                <?php $fCourses = $coursesByFacultyId[$f['faculty_id']] ?? []; ?>
                <a href="subjects.php?faculty=<?= urlencode($f['faculty_name']); ?>" style="text-decoration:none;">
                    <div class="faculty-view-card <?= $selectedFaculty == $f['faculty_name'] ? 'faculty-active' : ''; ?>">
                        <h4><?= htmlspecialchars($f['faculty_name']); ?></h4>
                        <?php if (empty($fCourses)): ?>
                            <p class="no-courses-note">No courses yet.</p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($fCourses as $c): ?>
                                    <li><?= htmlspecialchars($c['course_name']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
            <p style="color:#aaa;">No faculties added yet.</p>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Show error message if any -->
    <?php if (isset($_SESSION['manage_error'])): ?>
        <p class="notice"><?= htmlspecialchars($_SESSION['manage_error']); ?></p>
        <?php unset($_SESSION['manage_error']); ?>
    <?php endif; ?>

    <!-- ====== DROPDOWNS ROW ====== -->
    <div class="dropdowns-row">

        <?php if ($selectedFaculty != ''): ?>
        <!-- Show selected faculty as a label with a change link -->
        <span style="font-family:'Georgia',serif; font-size:20px; font-weight:bold; color:#031144;">
            <?= htmlspecialchars($selectedFaculty); ?>
            <a href="subjects.php" class="change-link">(Change)</a>
        </span>
        <?php endif; ?>

        <!-- Course dropdown (only shown after faculty is selected) -->
        <?php if ($selectedFaculty != ''): ?>
        <form action="subjects.php" method="GET">
            <input type="hidden" name="faculty" value="<?= htmlspecialchars($selectedFaculty); ?>">
            <select name="course" onchange="this.form.submit()">
                <option value="" disabled <?= $selectedCourse == '' ? 'selected' : ''; ?>>Select Course</option>
                <?php foreach ($coursesList[$selectedFaculty] ?? [] as $c): ?>
                    <option value="<?= htmlspecialchars($c['name']); ?>"
                        <?= $selectedCourse == $c['name'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($c['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>

    </div>

    <!-- ====== COURSE SECTION (shown after faculty is selected) ====== -->
    <?php if ($selectedFaculty != ''): ?>

        <!-- Add New Course form -->
        <div class="add-card" style="margin-bottom: 28px;">
            <h3>Add New Course to <?= htmlspecialchars($selectedFaculty); ?></h3>
            <form action="subjects.php" method="POST" autocomplete="off">
                <input type="hidden" name="faculty" value="<?= htmlspecialchars($selectedFaculty); ?>">
                <div class="input-row">
                    <input type="text" name="new_course_name"     placeholder="Course Name (e.g. BSc.IT)" required>
                    <input type="text" name="new_course_duration" placeholder="Duration (e.g. 4 Years)">
                    <select name="new_course_nature" required>
                        <option value="" disabled selected>Select Type</option>
                        <option value="Semester">Semester</option>
                        <option value="Year">Year</option>
                    </select>
                    <button type="submit" name="add_course">Add Course</button>
                </div>
            </form>
        </div>

        <!-- Course list table -->
        <?php if (!empty($coursesList[$selectedFaculty])): ?>
        <table class="course-list-table">
            <thead>
                <tr>
                    <th style="width:40px; text-align:center;">S.N</th>
                    <th>Course Name</th>
                    <th>Course Duration</th>
                    <th style="width:100px; text-align:center;">Course Type</th>
                    <th style="width:120px; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($coursesList[$selectedFaculty] as $c): ?>
                <tr>
                    <!-- Serial number -->
                    <td style="text-align:center;"><?= $sn++; ?></td>

                    <!-- Editable course name -->
                    <td>
                        <input type="text"
                            id="cname-<?= md5($c['name']); ?>"
                            value="<?= htmlspecialchars($c['name']); ?>">
                    </td>

                    <!-- Editable duration -->
                    <td>
                        <input type="text"
                            id="cdur-<?= md5($c['name']); ?>"
                            value="<?= htmlspecialchars($c['duration']); ?>"
                            placeholder="e.g. 4 Years">
                    </td>

                    <!-- Editable nature (dropdown only) -->
                    <td>
                        <select id="ctype-<?= md5($c['name']); ?>">
                            <option value="" disabled <?= $c['nature'] == '' ? 'selected' : ''; ?>>Select Type</option>
                            <option value="Semester" <?= $c['nature'] == 'Semester' ? 'selected' : ''; ?>>Semester</option>
                            <option value="Year"     <?= $c['nature'] == 'Year'     ? 'selected' : ''; ?>>Year</option>
                        </select>
                    </td>

                    <!-- Action buttons stacked vertically -->
                    <td style="text-align:center;">
                        <div style="display:flex; flex-direction:column; gap:6px;">

                            <!-- Update button -->
                            <button type="button" class="btn-save"
                                onclick="updateCourse(
                                    '<?= md5($c['name']); ?>',
                                    '<?= htmlspecialchars(addslashes($c['name'])); ?>'
                                )">
                                Update
                            </button>

                            <!-- Delete button -->
                            <button type="button" class="btn-delete-course"
                                onclick="if(confirm('Delete course \'<?= htmlspecialchars(addslashes($c['name'])); ?>\'?')) {
                                    document.getElementById('del-<?= md5($c['name']); ?>').submit();
                                }">
                                Delete
                            </button>

                        </div>

                        <!-- Hidden delete form (invisible, submitted by JS) -->
                        <form id="del-<?= md5($c['name']); ?>" action="subjects.php" method="POST" style="display:none;">
                            <input type="hidden" name="faculty"          value="<?= htmlspecialchars($selectedFaculty); ?>">
                            <input type="hidden" name="course_to_delete" value="<?= htmlspecialchars($c['name']); ?>">
                            <input type="hidden" name="delete_course"    value="1">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php else: ?>
            <p style="color:#aaa; margin-bottom:20px;">No courses added yet for <?= htmlspecialchars($selectedFaculty); ?>.</p>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ====== SUBJECT SECTION (shown only after course AND type are selected) ====== -->
    <?php if ($selectedFaculty != '' && $selectedCourse != '' && $currentNature != ''): ?>

        <!-- Add Subject form -->
        <div class="add-card">
            <h3>Add Subject to <?= htmlspecialchars($selectedCourse); ?> (<?= htmlspecialchars(natureLabel($currentNature)); ?>)</h3>
            <form action="subjects.php" method="POST" autocomplete="off">
                <input type="hidden" name="course"  value="<?= htmlspecialchars($selectedCourse); ?>">
                <input type="hidden" name="faculty" value="<?= htmlspecialchars($selectedFaculty); ?>">
                <div class="input-row">
                    <input type="text" name="subject_code" placeholder="Subject Code (e.g. CSIT101)" required>
                    <input type="text" name="subject_name" placeholder="Subject Name" required>
                    <input type="text" name="teacher_name" placeholder="Assigned Teacher" required>
                    <button type="submit" name="add">Add Subject</button>
                </div>
            </form>
        </div>

        <!-- Subjects list table -->
        <?php if ($subjects && $subjects->num_rows > 0): ?>
        <table class="subjects-table">
            <thead>
                <tr>
                    <th style="width:50px; text-align:center;">S.N</th>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Assigned Teacher</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; while ($sub = $subjects->fetch_assoc()): ?>
                <tr>
                    <td style="text-align:center;"><?= $sn++; ?></td>
                    <td><input type="text" id="code-<?=    $sub['id']; ?>" value="<?= htmlspecialchars($sub['subject_code']); ?>" autocomplete="off"></td>
                    <td><input type="text" id="name-<?=    $sub['id']; ?>" value="<?= htmlspecialchars($sub['subject_name']); ?>" autocomplete="off"></td>
                    <td><input type="text" id="teacher-<?= $sub['id']; ?>" value="<?= htmlspecialchars($sub['teacher_name']); ?>" autocomplete="off"></td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="btn-update"
                                style="width:auto; padding:7px 14px; font-size:13px; margin-bottom:0;"
                                onclick="updateSubject(<?= $sub['id']; ?>)">
                                Update
                            </button>
                            <a href="subjects.php?delete=<?= $sub['id']; ?>&course=<?= urlencode($selectedCourse); ?>&faculty=<?= urlencode($selectedFaculty); ?>"
                               onclick="return confirm('Delete this subject?')"
                               style="text-decoration:none;">
                                <button type="button" class="btn-delete"
                                    style="width:auto; padding:7px 14px; font-size:13px; margin-bottom:0;">
                                    Delete
                                </button>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Hidden form used by updateSubject() -->
        <form id="subject-update-form" action="subjects.php" method="POST" autocomplete="off">
            <input type="hidden" id="sub-id"      name="id">
            <input type="hidden" id="sub-code"    name="subject_code">
            <input type="hidden" id="sub-name"    name="subject_name">
            <input type="hidden" id="sub-teacher" name="teacher_name">
            <input type="hidden" name="course"    value="<?= htmlspecialchars($selectedCourse); ?>">
            <input type="hidden" name="faculty"   value="<?= htmlspecialchars($selectedFaculty); ?>">
            <input type="hidden" name="update"    value="1">
        </form>

        <?php else: ?>
            <p style="color:#aaa; margin-top:20px;">No subjects added yet for <?= htmlspecialchars($selectedCourse); ?>.</p>
        <?php endif; ?>

    <?php elseif ($selectedFaculty != '' && $selectedCourse != '' && $currentNature == ''): ?>
        <p class="notice">Please select the Course Type (Semester or Year) above before adding subjects.</p>
    <?php endif; ?>

    <!-- Hidden form used by updateCourse() — placed here so it always exists in the page -->
    <?php if ($selectedFaculty != ''): ?>
    <form id="course-update-form" action="subjects.php" method="POST">
        <input type="hidden" id="update-old-name"     name="old_course_name">
        <input type="hidden" id="update-new-name"     name="new_course_name_rename">
        <input type="hidden" id="update-new-duration" name="new_duration">
        <input type="hidden" id="update-new-nature"   name="new_nature">
        <input type="hidden" name="faculty"            value="<?= htmlspecialchars($selectedFaculty); ?>">
        <input type="hidden" name="update_course_row"  value="1">
    </form>
    <?php endif; ?>

</div>

<!-- ====== JAVASCRIPT ====== -->
<script>

// UPDATE COURSE:
// Step 1 — read the edited name and duration from the input boxes
// Step 2 — put them into the hidden form fields
// Step 3 — submit the hidden form to PHP
function updateCourse(hash, oldName) {
    document.getElementById('update-old-name').value     = oldName;
    document.getElementById('update-new-name').value     = document.getElementById('cname-' + hash).value;
    document.getElementById('update-new-duration').value = document.getElementById('cdur-'  + hash).value;
    document.getElementById('update-new-nature').value   = document.getElementById('ctype-' + hash).value;
    document.getElementById('course-update-form').submit();
}

// UPDATE SUBJECT:
// Same idea — read inputs, fill hidden form, submit
function updateSubject(id) {
    document.getElementById('sub-id').value      = id;
    document.getElementById('sub-code').value    = document.getElementById('code-'    + id).value;
    document.getElementById('sub-name').value    = document.getElementById('name-'    + id).value;
    document.getElementById('sub-teacher').value = document.getElementById('teacher-' + id).value;
    document.getElementById('subject-update-form').submit();
}

</script>

</body>
</html>