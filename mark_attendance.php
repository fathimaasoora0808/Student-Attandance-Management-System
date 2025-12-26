<?php
// --- SETUP AND SECURITY ---
// Start a session to manage user login state.
session_start();
// Check if the user is logged in and is an admin. If not, redirect to the login page.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
// Include the database connection and the standard page header.
include 'db.php';
include 'header.php';

// --- DATA FETCHING FOR FILTERS ---
// Get all batches from the database to populate the batch selection dropdown.
$batches = $conn->query("SELECT * FROM batches ORDER BY batch_name");

// --- HANDLE GET PARAMETERS (FILTER VALUES) ---
// Get the selected batch, course, and date from the URL (if they exist).
// These are used to filter the student list and re-select the options after the page reloads.
$selected_batch = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$selected_course = isset($_GET['course_id']) ? $_GET['course_id'] : '';
$attendance_date = isset($_GET['attendance_date']) ? $_GET['attendance_date'] : date('Y-m-d'); // Default to today's date.

// --- FETCH STUDENTS BASED ON FILTERS ---
// If a batch and course have been selected, fetch the list of students.
$students = [];
if ($selected_batch && $selected_course) {
    // Use a prepared statement to prevent SQL injection.
    $stmt = $conn->prepare("SELECT s.student_id, s.name FROM students s WHERE s.batch_id = ?");
    $stmt->bind_param("i", $selected_batch);
    $stmt->execute();
    $result = $stmt->get_result();
    // Loop through the results and add each student to the $students array.
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}

// --- HANDLE POST REQUEST (SAVING ATTENDANCE) ---
// Check if the form was submitted using the POST method.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the submitted data.
    $batch_id = $_POST['batch_id'];
    $course_id = $_POST['course_id'];
    $date = $_POST['attendance_date'];
    $attendance_data = $_POST['attendance']; // This is an array of [student_id => status]

    // Prepare a single SQL statement to insert or update attendance records.
    // "ON DUPLICATE KEY UPDATE" is very efficient: if a record for that student, course, and date
    // already exists, it updates the status; otherwise, it inserts a new record.
    $stmt = $conn->prepare("INSERT INTO attendance (student_id, course_id, date, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");

    // Loop through each student's attendance status from the form.
    foreach ($attendance_data as $student_id => $status) {
        // Bind the parameters and execute the query for each student.
        $stmt->bind_param("iiss", $student_id, $course_id, $date, $status);
        $stmt->execute();
    }
    $stmt->close();
    // Show a success message.
    echo "<div class='alert alert-success'>Attendance saved successfully!</div>";
    
    // After saving, keep the filters selected to show the user what was just saved.
    $selected_batch = $batch_id;
    $selected_course = $course_id;
    $attendance_date = $date;
}
?>

<!-- --- HTML CONTENT --- -->
<div class="container mt-4">
    <h2>Mark Attendance</h2>
    <hr>
    <!-- Filter Form: Submits via GET to fetch the student list. -->
    <form method="GET" action="mark_attendance.php" class="form-inline mb-4">
        <!-- Batch Selection Dropdown -->
        <div class="form-group mr-2">
            <label for="batch_id" class="mr-2">Batch:</label>
            <select name="batch_id" id="batch_id" class="form-control" required>
                <option value="">Select Batch</option>
                <?php while ($batch = $batches->fetch_assoc()): ?>
                    <option value="<?php echo $batch['batch_id']; ?>" <?php echo ($selected_batch == $batch['batch_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($batch['batch_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <!-- Course Selection Dropdown (Dynamically populated by JavaScript) -->
        <div class="form-group mr-2">
            <label for="course_id" class="mr-2">Course:</label>
            <select name="course_id" id="course_id" class="form-control" required>
                <option value="">Select Batch First</option>
                <?php
                // If the page is loaded with a batch already selected (e.g., after fetching students),
                // this PHP block pre-populates the course dropdown with the correct courses.
                if ($selected_batch) {
                    $stmt = $conn->prepare("SELECT c.course_id, c.course_name FROM courses c JOIN batch_courses bc ON c.course_id = bc.course_id WHERE bc.batch_id = ? ORDER BY c.course_name");
                    $stmt->bind_param("i", $selected_batch);
                    $stmt->execute();
                    $courses_result = $stmt->get_result();
                    while ($course = $courses_result->fetch_assoc()) {
                        $is_selected = ($selected_course == $course['course_id']) ? 'selected' : '';
                        echo "<option value='{$course['course_id']}' {$is_selected}>" . htmlspecialchars($course['course_name']) . "</option>";
                    }
                    $stmt->close();
                }
                ?>
            </select>
        </div>
        <!-- Date Selection -->
        <div class="form-group mr-2">
            <label for="attendance_date" class="mr-2">Date:</label>
            <input type="date" name="attendance_date" id="attendance_date" class="form-control" value="<?php echo $attendance_date; ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Fetch Students</button>
    </form>

    <!-- Attendance Form: Only shown if students were fetched. -->
    <?php if (!empty($students)): ?>
    <form method="POST" action="mark_attendance.php">
        <!-- Hidden fields to pass the filter values when saving attendance. -->
        <input type="hidden" name="batch_id" value="<?php echo $selected_batch; ?>">
        <input type="hidden" name="course_id" value="<?php echo $selected_course; ?>">
        <input type="hidden" name="attendance_date" value="<?php echo $attendance_date; ?>">
        
        <!-- "Mark All" buttons for convenience -->
        <div class="mb-3">
            <button type="button" class="btn btn-outline-success btn-sm" onclick="markAll('Present')">Mark All Present</button>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="markAll('Absent')">Mark All Absent</button>
        </div>

        <!-- Student Attendance Table -->
        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>Student Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                    <td>
                        <!-- Radio buttons for Present/Absent status -->
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="attendance[<?php echo $student['student_id']; ?>]" id="present_<?php echo $student['student_id']; ?>" value="Present" required>
                            <label class="form-check-label" for="present_<?php echo $student['student_id']; ?>">Present</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="attendance[<?php echo $student['student_id']; ?>]" id="absent_<?php echo $student['student_id']; ?>" value="Absent" required>
                            <label class="form-check-label" for="absent_<?php echo $student['student_id']; ?>">Absent</label>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="btn btn-success">Save Attendance</button>
    </form>
    <?php elseif (isset($_GET['batch_id'])): ?>
        <!-- Message shown if filters are selected but no students are found. -->
        <div class="alert alert-info">No students found for the selected batch.</div>
    <?php endif; ?>
</div>

<!-- --- JAVASCRIPT --- -->
<script>
// This function runs after the HTML document has been fully loaded.
document.addEventListener('DOMContentLoaded', function() {
    const batchSelect = document.getElementById('batch_id');
    const courseSelect = document.getElementById('course_id');

    // Add an event listener that triggers when the batch selection changes.
    batchSelect.addEventListener('change', function() {
        const batchId = this.value;
        
        // When the batch changes, hide the student list until "Fetch Students" is clicked again.
        const studentTableForm = document.querySelector('form[method="POST"]');
        if (studentTableForm) {
            studentTableForm.style.display = 'none';
        }
        
        // Show a "Loading..." message in the course dropdown.
        courseSelect.innerHTML = '<option value="">Loading...</option>';

        // If a valid batch is selected...
        if (batchId) {
            // Fetch the courses for the selected batch from our API endpoint.
            fetch('api_get_courses.php?batch_id=' + batchId)
                .then(response => response.json()) // Parse the JSON response.
                .then(data => {
                    // Clear the dropdown and add the default option.
                    courseSelect.innerHTML = '<option value="">Select Course</option>';
                    // Loop through the fetched courses and add each one as an option.
                    data.forEach(course => {
                        const option = new Option(course.course_name, course.course_id);
                        courseSelect.add(option);
                    });
                })
                .catch(error => {
                    // If the fetch fails, show an error message.
                    console.error('Error fetching courses:', error);
                    courseSelect.innerHTML = '<option value="">Error loading courses</option>';
                });
        } else {
            // If no batch is selected, reset the course dropdown.
            courseSelect.innerHTML = '<option value="">Select Batch First</option>';
        }
    });
});

// This function is called by the "Mark All" buttons.
function markAll(status) {
    // Get all radio buttons in the form.
    const radios = document.querySelectorAll('input[type="radio"]');
    radios.forEach(radio => {
        // If the radio button's value matches the desired status ('Present' or 'Absent')...
        if (radio.value === status) {
            // ...check it.
            radio.checked = true;
        }
    });
}
</script>

<?php include 'footer.php'; ?>
