<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

// Helper: safely output text in HTML
function h($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

$email = $_SESSION['email'];

// =============================================
// GET STUDENT DATA
// =============================================
$stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If no student found, destroy session and go back to login
if (!$student) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$course = $student['course'];

// =============================================
// GET COURSE TYPE AND DURATION
// =============================================
$courseLabel    = '';
$courseDuration = '';

$stmt2 = $conn->prepare("SELECT type_nature, duration FROM course WHERE course_name = ?");
$stmt2->bind_param("s", $course);
$stmt2->execute();
$courseRow = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

if ($courseRow) {
    $courseLabel    = $courseRow['type_nature'] == 'Semester' ? 'I Semester' : 'I Year';
    $courseDuration = $courseRow['duration'] ?? '';
}

// =============================================
// GET SUBJECTS FOR THIS COURSE
// =============================================
$subjectRows = [];

$stmt3 = $conn->prepare("SELECT * FROM subjects WHERE course = ?");
$stmt3->bind_param("s", $course);
$stmt3->execute();
$subs = $stmt3->get_result();
$stmt3->close();

while ($sub = $subs->fetch_assoc()) {
    $subjectRows[] = $sub;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Page</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html, body {
            overflow: hidden;
            height: 100%;
        }

        .university-header { background: #031144; }

        .student-box {
            max-width: 100%;
            width: 98%;
            margin: 0 auto;
            padding: 2px 10px 0;
            font-family: 'Georgia', serif;
        }

        .student-box h1 {
            font-size: 32px;
            font-weight: normal;
            text-align: center;
            margin-top: -18px;
            margin-bottom: 30px;
        }

        .student-box h1 span {
            color: #031144;
            font-weight: bold;
        }

        .section-title {
            text-align: center;
            font-size: 19px;
            font-weight: bold;
            color: #031144;
            margin: 18px 0 10px;
            letter-spacing: 0.5px;
        }

        .tables-row {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 28px;
        }

        .tables-row .col {
            flex: 1;
            min-width: 0;
        }

        .col .section-title {
            margin-top: 0;
        }

        /* Both table wrappers same height with scroll */
        .table-wrap {
            background: #fffdf8;
            border-radius: 4px;
            box-shadow: 0 6px 24px rgba(3,17,68,0.12);
            border: 1px solid #d8c9a3;
            width: 100%;
            height: 420px;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Georgia', serif;
        }

        .info-table th {
            background: #031144;
            color: #D4AF37;
            height: 46px;
            padding: 10px 16px;
            text-align: left;
            font-size: 16px;
            font-weight: bold;
            border-bottom: 2px solid #D4AF37;
            border-right: 1px solid #1a2a6e;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .info-table td {
            padding: 11px 16px;
            border-bottom: 1px solid #d8c9a3;
            border-right: 1px solid #e8dfc4;
            font-size: 15px;
            color: #1c2233;
            vertical-align: middle;
        }

        .info-table tbody tr:nth-child(even) { background: #fbf8ef; }
        .info-table tbody tr:hover           { background: #f3ecd6; }
        .info-table tbody tr:last-child td   { border-bottom: none; }

        /* Bold label column in My Details table */
        .info-table td.label {
            font-weight: bold;
            color: #031144;
            width: 160px;
            white-space: nowrap;
        }

        /* Grey italic text for empty fields */
        .not-assigned {
            color: #aaa;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .tables-row { flex-direction: column; }
        }
    </style>
</head>
<body style="padding-top: 120px;">

<!-- ====== HEADER ====== -->
<div class="university-header">
    <img src="logo.png" alt="Logo">
    <h2>B&S University</h2>
    <div class="header-links">
        <a href="logout.php"><button class="header-btn">Logout</button></a>
    </div>
</div>

<div class="student-box">

    <h1>Welcome, <span><?= h($student['name']); ?></span></h1>

    <div class="tables-row">

        <!-- ====== LEFT: MY DETAILS ====== -->
        <div class="col">
            <p class="section-title">My Details</p>
            <div class="table-wrap">
                <table class="info-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="label">Email</td>
                            <td><?= h($student['email']); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Phone</td>
                            <td><?= h($student['phone']); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Date of Birth</td>
                            <td><?= h($student['DOB']); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Gender</td>
                            <td><?= !empty($student['gender']) ? h($student['gender']) : '<span class="not-assigned">Not provided</span>'; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Faculty</td>
                            <td><?= h($student['faculty']); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Course</td>
                            <td><?= h($student['course']); ?></td>
                        </tr>
                        <!-- Course Duration row — fetched from course table -->
                        <tr>
                            <td class="label">Course Duration</td>
                            <td><?= $courseDuration != '' ? h($courseDuration) : '<span class="not-assigned">Not set yet</span>'; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Address</td>
                            <td><?= h($student['address']); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Parent's Name</td>
                            <td><?= h($student['parent_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Parent's Phone</td>
                            <td><?= h($student['parent_phone']); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Roll Number</td>
                            <td><?= !empty($student['roll_number']) ? h($student['roll_number']) : '<span class="not-assigned">Not assigned yet</span>'; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Room Number</td>
                            <td><?= !empty($student['room_number']) ? h($student['room_number']) : '<span class="not-assigned">Not assigned yet</span>'; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Registered On</td>
                            <td><?= !empty($student['registration_date']) ? h($student['registration_date']) : '<span class="not-assigned">—</span>'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ====== RIGHT: COURSE SUBJECTS ====== -->
        <div class="col">
            <p class="section-title">
                Subject Information — <?= h($course); ?>
                <?= $courseLabel != '' ? ' — ' . h($courseLabel) : ''; ?>
            </p>
            <div class="table-wrap">
                <?php if (!empty($subjectRows)): ?>
                <table class="info-table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Teacher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjectRows as $sub): ?>
                        <tr>
                            <td><?= h($sub['subject_code']); ?></td>
                            <td><?= h($sub['subject_name']); ?></td>
                            <td><?= h($sub['teacher_name']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="padding:16px; color:#aaa; font-family:'Georgia',serif;">
                        No subjects assigned yet for your course.
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>