<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

// Only admins may use this page
$guardStmt = $conn->prepare("SELECT role FROM students WHERE email = ?");
$guardStmt->bind_param("s", $_SESSION['email']);
$guardStmt->execute();
$guardRow = $guardStmt->get_result()->fetch_assoc();
if (!$guardRow || $guardRow['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Security token: one secret per login session.
// Delete forms must send it back, which proves the request
// came from our own page and not from a hidden link elsewhere.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function h($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

// Add new faculty
if (isset($_POST['add_faculty'])) {
    $faculty_name = trim($_POST['faculty_name'] ?? '');

    if ($faculty_name !== '') {
        // Prevent duplicate faculty names (case-insensitive)
        $check = $conn->prepare("SELECT faculty_id FROM faculty WHERE faculty_name = ?");
        $check->bind_param("s", $faculty_name);
        $check->execute();
        $existing = $check->get_result();

        if ($existing->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO faculty (faculty_name) VALUES (?)");
            $stmt->bind_param("s", $faculty_name);
            $stmt->execute();
        } else {
            $_SESSION['manage_error'] = "A faculty named \"$faculty_name\" already exists.";
        }
    }

    header("Location: manage_faculty.php");
    exit();
}

// Rename faculty
if (isset($_POST['update_faculty'])) {
    $faculty_id = intval($_POST['faculty_id'] ?? 0);
    $faculty_name = trim($_POST['faculty_name'] ?? '');

    if ($faculty_id > 0 && $faculty_name !== '') {
        // Prevent renaming to a name that already belongs to a different faculty
        $check = $conn->prepare("SELECT faculty_id FROM faculty WHERE faculty_name = ? AND faculty_id != ?");
        $check->bind_param("si", $faculty_name, $faculty_id);
        $check->execute();
        $existing = $check->get_result();

        if ($existing->num_rows == 0) {
            $stmt = $conn->prepare("UPDATE faculty SET faculty_name = ? WHERE faculty_id = ?");
            $stmt->bind_param("si", $faculty_name, $faculty_id);
            $stmt->execute();
        } else {
            $_SESSION['manage_error'] = "A faculty named \"$faculty_name\" already exists.";
        }
    }

    header("Location: manage_faculty.php");
    exit();
}

// Delete faculty (also deletes its courses)
// Only accepts POST requests that carry the correct token
if (isset($_POST['delete_faculty'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit("Invalid request.");
    }

    $faculty_id = intval($_POST['delete_faculty']);

    $stmt1 = $conn->prepare("DELETE FROM course WHERE faculty_id = ?");
    $stmt1->bind_param("i", $faculty_id);
    $stmt1->execute();

    $stmt2 = $conn->prepare("DELETE FROM faculty WHERE faculty_id = ?");
    $stmt2->bind_param("i", $faculty_id);
    $stmt2->execute();

    header("Location: manage_faculty.php");
    exit();
}


// Rename course
if (isset($_POST['update_course'])) {
    $course_id = intval($_POST['course_id'] ?? 0);
    $new_course_name = trim($_POST['course_name'] ?? '');

    if ($course_id > 0 && $new_course_name !== '') {
        // Get the course's CURRENT name before renaming, so we can
        // cascade the rename into the subjects table (which stores
        // course by name, not by course_id).
        $current = $conn->prepare("SELECT course_name FROM course WHERE course_id = ?");
        $current->bind_param("i", $course_id);
        $current->execute();
        $currentRow = $current->get_result()->fetch_assoc();
        $old_course_name = $currentRow['course_name'] ?? '';

        // Prevent renaming to a name that already belongs to a different course
        $check = $conn->prepare("SELECT course_id FROM course WHERE course_name = ? AND course_id != ?");
        $check->bind_param("si", $new_course_name, $course_id);
        $check->execute();
        $existing = $check->get_result();

        if ($existing->num_rows == 0) {
            $stmt = $conn->prepare("UPDATE course SET course_name = ? WHERE course_id = ?");
            $stmt->bind_param("si", $new_course_name, $course_id);
            $stmt->execute();

            // Cascade the rename so existing subjects stay linked to this course
            if ($old_course_name !== '' && $old_course_name !== $new_course_name) {
                $cascade = $conn->prepare("UPDATE subjects SET course = ? WHERE course = ?");
                $cascade->bind_param("ss", $new_course_name, $old_course_name);
                $cascade->execute();
            }
        } else {
            $_SESSION['manage_error'] = "A course named \"$new_course_name\" already exists.";
        }
    }

    header("Location: manage_faculty.php");
    exit();
}

// Delete course
// Only accepts POST requests that carry the correct token
if (isset($_POST['delete_course'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit("Invalid request.");
    }

    $course_id = intval($_POST['delete_course']);
    $stmt = $conn->prepare("DELETE FROM course WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    header("Location: manage_faculty.php");
    exit();
}

// Fetch all faculties
$faculties = $conn->query("SELECT * FROM faculty ORDER BY faculty_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Faculty & Courses</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .university-header { background: #031144; }
       .faculty-card-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-top: 16px;
    align-items: stretch;
}
.faculty-view-card {
    background: #fffdf8;
    border: 1px solid #d8c9a3;
    border-radius: 8px;
    padding: 14px 16px;
    box-shadow: 0 2px 8px rgba(3,17,68,0.06);
    display: flex;
    flex-direction: column;
}
.faculty-view-card h4 {
    color: #031144;
    font-size: 16px;
    margin: 0 0 10px 0;
    padding-bottom: 8px;
    border-bottom: 2px solid #D4AF37;
    min-height: 2.6em;
    display: flex;
    align-items: flex-end;
}
.section-card {
    background: #f4f7ff;
    border: 1.5px solid #b8a06a;
    border-radius: 10px;
    padding: 14px 20px;
    margin: 10px 0;
    box-shadow: 0 4px 14px rgba(3,17,68,0.08);
}
        .section-card h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #7494ec;
        }
        .add-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .add-row input, .add-row select {
            flex: 1;
            min-width: 180px;
            margin-bottom: 0;
        }
        .add-row button {
            width: auto;
            padding: 12px 20px;
            margin-bottom: 0;
        }
        .list-table {
            width: 100%;
            border-collapse: collapse;
            background: #fffdf8;
            border: 1px solid #d8c9a3;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 16px;
            font-family: 'Georgia', serif;
        }
        .list-table th {
            background: #031144;
            color: #D4AF37;
            padding: 12px 16px;
            text-align: left;
            font-size: 14px;
        }
        .list-table td {
            padding: 11px 16px;
            border-bottom: 1px solid #ece3cc;
            font-size: 15px;
            vertical-align: middle;
        }
        .list-table tbody tr:nth-child(even) { background: #fbf8ef; }
        .faculty-card-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 16px;
        }
        .faculty-view-card {
            background: #fffdf8;
            border: 1px solid #d8c9a3;
            border-radius: 8px;
            padding: 14px 16px;
            box-shadow: 0 2px 8px rgba(3,17,68,0.06);
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
            margin: 0;
        }
        @media (max-width: 900px) {
            .faculty-card-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 560px) {
            .faculty-card-grid { grid-template-columns: 1fr; }
        }
        .notice {
            color: #a42834;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 0;
            font-size: 14px;
        }
.manage-box {
    max-width: 70%;
    width: 92%;
    margin: 0 auto;
    padding: 110px 24px 20px;
    font-family: 'Georgia', serif;
}
.list-table td input[type="text"] {
    padding: 8px 10px;
    margin-bottom: 0;
    font-size: 15px;
    border: 1px solid #d8c9a3;
    border-radius: 5px;
    background: #fff;
    font-family: 'Georgia', serif;
}
.list-table td {
    vertical-align: middle;
}
.manage-course-card {
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    min-height: 180px;
}

.manage-course-card:hover {
    transform: translateY(-3px);
    border-top: 4px solid #D4AF37;
    box-shadow: 0 6px 18px rgba(3, 17, 68, 0.18);
    background: #031144;
    color: #D4AF37;
}

.manage-course-card:hover h4 {
    color: #D4AF37;
    border-bottom-color: #D4AF37;
}

.manage-course-card:hover p {
    color: #a0b4e0;
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
    margin: 18px 0 14px;
    padding-bottom: 10px;
    border-bottom: 2px solid #D4AF37;
}
    </style>
</head>
<body style="padding-top: 0px;">

<div class="university-header">
    <img src="logo.png" alt="Logo">
    <h2>B&S University</h2>
    <div class="header-links">
<a href="subjects.php"><button class="header-btn" style="margin-left: 15px; padding: 5px 2px;">Manage Course</button></a>
        <a href="admin_page.php"><button class="header-btn" style="padding: 5px 5px;">Back to Dashboard</button></a>
    </div>
</div>

<div class="manage-box">
    <h3 class="faculty-heading">Manage Faculty</h3>

    <?php if (isset($_SESSION['manage_error'])): ?>
        <p class="notice"><?= h($_SESSION['manage_error']); ?></p>
        <?php unset($_SESSION['manage_error']); ?>
    <?php endif; ?>

    <!-- Add Faculty -->
    <div class="section-card">
        <h3>Add New Faculty</h3>
        <form action="manage_faculty.php" method="POST" autocomplete="off">
            <div class="add-row">
                <input type="text" name="faculty_name" placeholder="Faculty Name (e.g. Engineering)" required>
                <button type="submit" name="add_faculty">Add Faculty</button>
            </div>
        </form>

        <!-- Faculty List -->
        <?php if ($faculties->num_rows > 0): ?>
        <table class="list-table">
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Faculty Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; $faculties->data_seek(0); while ($f = $faculties->fetch_assoc()): ?>
                <tr>
                    <td><?= $sn++; ?></td>
                    <td><input type="text" id="faculty-name-<?= $f['faculty_id']; ?>" value="<?= h($f['faculty_name']); ?>" autocomplete="off"></td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="btn-update"
                                style="width:auto; padding:6px 14px; font-size:13px; margin-bottom:0;"
                                onclick="updateFaculty(<?= $f['faculty_id']; ?>)">Rename</button>
                            <form method="POST" action="manage_faculty.php" style="margin:0;"
      onsubmit="return confirm('Delete this faculty and all its courses?')">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']); ?>">
    <input type="hidden" name="delete_faculty" value="<?= $f['faculty_id']; ?>">
    <button type="submit" class="btn-delete"
        style="width:auto; padding:6px 14px; font-size:13px; margin-bottom:0;">Delete</button>
</form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Hidden form used by updateFaculty() to submit the rename -->
        <form id="faculty-update-form" action="manage_faculty.php" method="POST">
            <input type="hidden" id="faculty-update-id" name="faculty_id">
            <input type="hidden" id="faculty-update-name" name="faculty_name">
            <input type="hidden" name="update_faculty" value="1">
        </form>

        <?php else: ?>
            <p style="color:#aaa; margin-top:12px;">No faculties added yet.</p>
        <?php endif; ?>
    </div>

</div>

<script>
function updateFaculty(id) {
    var newName = document.getElementById('faculty-name-' + id).value.trim();
    if (newName === '') {
        alert('Faculty name cannot be empty.');
        return;
    }
    document.getElementById('faculty-update-id').value = id;
    document.getElementById('faculty-update-name').value = newName;
    document.getElementById('faculty-update-form').submit();
}
</script>

</body>
</html>