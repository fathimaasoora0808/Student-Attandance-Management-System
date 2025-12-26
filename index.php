<?php
include 'header.php';
// Ensure only admins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get and sanitize filter values
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_attendance'])) {
    $attendance_data = isset($_POST['attendance_status']) ? $_POST['attendance_status'] : [];
    $post_course_id = (int)$_POST['course_id'];
    $post_date = $_POST['date'];

    if (!empty($attendance_data) && $post_course_id > 0 && !empty($post_date)) {
        $stmt = $conn->prepare("
            INSERT INTO attendance (student_id, course_id, date, status, time_marked) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = VALUES(status), time_marked = NOW()
        ");

        foreach ($attendance_data as $student_id => $status) {
            $stmt->bind_param("iiss", $student_id, $post_course_id, $post_date, $status);
            $stmt->execute();
        }
        $stmt->close();
        $success_message = "Attendance for " . htmlspecialchars($post_date) . " saved successfully!";
    } else {
        $error_message = "Failed to save attendance. Please ensure all fields are selected.";
    }
}

// Fetch all batches for the dropdown
$batches_result = $conn->query("SELECT * FROM batches ORDER BY batch_name");
?>

<div class="container mt-4">
    <h1 class="mb-4">Mark Attendance</h1>

    <?php if(isset($success_message)) { echo "<div class='alert alert-success'>$success_message</div>"; } ?>
    <?php if(isset($error_message)) { echo "<div class='alert alert-danger'>$error_message</div>"; } ?>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Select Batch, Course, and Date</h3>
        </div>
        <div class="card-body">
            <form action="index.php" method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="batch_id" class="form-label">Batch</label>
                    <select name="batch_id" id="batch_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">Select a Batch</option>
                        <?php while($batch = $batches_result->fetch_assoc()): ?>
                            <option value="<?php echo $batch['batch_id']; ?>" <?php if($batch_id == $batch['batch_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($batch['batch_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <?php if ($batch_id > 0): ?>
                <div class="col-md-4">
                    <label for="course_id" class="form-label">Course</label>
                    <select name="course_id" id="course_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">Select a Course</option>
                        <?php
                        $stmt = $conn->prepare("SELECT c.course_id, c.course_name FROM courses c JOIN batch_courses bc ON c.course_id = bc.course_id WHERE bc.batch_id = ? ORDER BY c.course_name");
                        $stmt->bind_param("i", $batch_id);
                        $stmt->execute();
                        $courses_result = $stmt->get_result();
                        while($course = $courses_result->fetch_assoc()): ?>
                            <option value="<?php echo $course['course_id']; ?>" <?php if($course_id == $course['course_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                        <?php endwhile; $stmt->close(); ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-2">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" name="date" id="date" class="form-control" value="<?php echo $date; ?>">
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance Table -->
    <?php if ($batch_id > 0 && $course_id > 0 && !empty($date)): ?>
    <div class="card">
        <div class="card-header">
            <h3>Student List</h3>
        </div>
        <div class="card-body">
            <form action="index.php?batch_id=<?php echo $batch_id; ?>&course_id=<?php echo $course_id; ?>&date=<?php echo $date; ?>" method="post">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <input type="hidden" name="date" value="<?php echo $date; ?>">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch students for the selected batch
                        $students_stmt = $conn->prepare("SELECT student_id, name FROM students WHERE batch_id = ? ORDER BY name");
                        $students_stmt->bind_param("i", $batch_id);
                        $students_stmt->execute();
                        $students_result = $students_stmt->get_result();
                        
                        $students = [];
                        while ($row = $students_result->fetch_assoc()) {
                            $students[] = $row;
                        }
                        $students_stmt->close();

                        // Fetch existing attendance for these students
                        $attendance_records = [];
                        if (!empty($students)) {
                            $student_ids = array_column($students, 'student_id');
                            $ids_placeholder = implode(',', array_fill(0, count($student_ids), '?'));
                            $types = str_repeat('i', count($student_ids));

                            $att_stmt = $conn->prepare("SELECT student_id, status FROM attendance WHERE student_id IN ($ids_placeholder) AND course_id = ? AND date = ?");
                            
                            // Bind parameters dynamically
                            $params = $student_ids;
                            $params[] = $course_id;
                            $params[] = $date;
                            $att_stmt->bind_param($types . 'is', ...$params);
                            
                            $att_stmt->execute();
                            $att_result = $att_stmt->get_result();
                            while($att_row = $att_result->fetch_assoc()){
                                $attendance_records[$att_row['student_id']] = $att_row['status'];
                            }
                            $att_stmt->close();
                        }
                        
                        if (!empty($students)):
                            foreach($students as $student):
                                $status = $attendance_records[$student['student_id']] ?? '';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td>
                                <input type="radio" name="attendance_status[<?php echo $student['student_id']; ?>]" value="Present" <?php if($status == 'Present') echo 'checked'; ?> required> Present
                                <input type="radio" name="attendance_status[<?php echo $student['student_id']; ?>]" value="Absent" <?php if($status == 'Absent') echo 'checked'; ?>> Absent
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        else: ?>
                            <tr><td colspan="2" class="text-center">No students found in this batch.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if (!empty($students)): ?>
                <button type="submit" name="save_attendance" class="btn btn-primary">Save Attendance</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
