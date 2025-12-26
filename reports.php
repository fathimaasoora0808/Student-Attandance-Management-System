<?php
// --- SETUP AND SECURITY ---
// Include the standard header, which handles session start and database connection.
include 'header.php';
// Ensure only users with the 'admin' role can access this page.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- GET FILTER VALUES ---
// Get the filter values (batch, course, month) from the URL's query string.
// Use the null coalescing operator (??) or isset() for safety, providing default values.
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m'); // Default to the current month.

// --- FETCH DATA FOR FILTERS ---
// Fetch all batches to populate the batch selection dropdown.
$batches_result = $conn->query("SELECT * FROM batches ORDER BY batch_name");
?>

<!-- --- HTML CONTENT --- -->
<div class="container mt-4">
    <h1 class="mb-4">Attendance Reports</h1>

    <!-- Filter Form Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Generate Report</h3>
        </div>
        <div class="card-body">
            <!-- The form submits to itself using the GET method to update the URL with filter parameters. -->
            <form action="reports.php" method="get" class="row g-3 align-items-end">
                <!-- Batch Filter Dropdown -->
                <div class="col-md-4">
                    <label for="batch_id" class="form-label">Filter by Batch</label>
                    <select name="batch_id" id="batch_id" class="form-select">
                        <option value="">Select a Batch</option>
                        <?php
                        // Reset the internal pointer of the result set to loop through it again.
                        mysqli_data_seek($batches_result, 0);
                        // Loop through each batch and create an option in the dropdown.
                        while($batch = $batches_result->fetch_assoc()): ?>
                            <option value="<?php echo $batch['batch_id']; ?>" <?php if($batch_id == $batch['batch_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($batch['batch_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Course Filter Dropdown (Dynamically populated) -->
                <div class="col-md-4">
                    <label for="course_id" class="form-label">Filter by Course</label>
                    <select name="course_id" id="course_id" class="form-select">
                        <option value="">Select a Course</option>
                        <?php
                        // If a batch was selected when the page loaded, populate the courses for that batch.
                        if ($batch_id) {
                            $stmt = $conn->prepare("SELECT c.course_id, c.course_name FROM courses c JOIN batch_courses bc ON c.course_id = bc.course_id WHERE bc.batch_id = ? ORDER BY c.course_name");
                            $stmt->bind_param("i", $batch_id);
                            $stmt->execute();
                            $courses_result = $stmt->get_result();
                            while($course = $courses_result->fetch_assoc()): ?>
                                <option value="<?php echo $course['course_id']; ?>" <?php if($course_id == $course['course_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile;
                            $stmt->close();
                        }
                        ?>
                    </select>
                </div>

                <!-- Month Filter -->
                <div class="col-md-2">
                    <label for="month" class="form-label">Filter by Date</label>
                    <input type="month" name="month" id="month" class="form-control" value="<?php echo $month; ?>">
                </div>

                <!-- Submit Button -->
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Generate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Table: This section is only displayed if all three filters are selected. -->
    <?php if ($batch_id && $course_id && $month): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3>Report for <?php echo date('F Y', strtotime($month)); ?></h3>
            <!-- Export Buttons -->
            <div>
                <a href="generate_csv_report.php?batch_id=<?php echo $batch_id; ?>&course_id=<?php echo $course_id; ?>&month=<?php echo $month; ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-file-earmark-excel-fill"></i> Export to Excel
                </a>
                <a href="generate_pdf_report.php?batch_id=<?php echo $batch_id; ?>&course_id=<?php echo $course_id; ?>&month=<?php echo $month; ?>" class="btn btn-danger btn-sm" target="_blank">
                    <i class="bi bi-file-earmark-pdf-fill"></i> Export to PDF
                </a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Student Name</th>
                        <th>Total Classes</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // --- REPORT DATA CALCULATION ---
                    // Get the month and year from the selected month filter.
                    $report_month = date('m', strtotime($month));
                    $report_year = date('Y', strtotime($month));

                    // SQL query to generate the attendance summary for each student.
                    $query = "
                        SELECT 
                            s.name, -- Get the student's name
                            -- Count all attendance records for the student in the given month/course
                            COUNT(a.id) AS total_classes,
                            -- Count only the 'Present' records
                            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_classes,
                            -- Count only the 'Absent' records
                            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_classes
                        FROM students s
                        -- Join attendance records based on student_id and filter by course, month, and year
                        LEFT JOIN attendance a ON s.student_id = a.student_id 
                            AND a.course_id = ? 
                            AND MONTH(a.date) = ? 
                            AND YEAR(a.date) = ?
                        -- Filter students by the selected batch
                        WHERE s.batch_id = ?
                        -- Group the results by student to get one summary row per student
                        GROUP BY s.student_id, s.name
                        ORDER BY s.name;
                    ";
                    
                    // Prepare and execute the query with the filter parameters.
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iisi", $course_id, $report_month, $report_year, $batch_id);
                    $stmt->execute();
                    $report_result = $stmt->get_result();

                    // Check if any results were returned.
                    if ($report_result->num_rows > 0):
                        // Loop through each row of the result set.
                        while($row = $report_result->fetch_assoc()):
                            // Calculate the attendance percentage.
                            $total = (int)$row['total_classes'];
                            $present = (int)$row['present_classes'];
                            $absent = (int)$row['absent_classes'];
                            $percentage = ($total > 0) ? round(($present / $total) * 100, 2) : 0;
                    ?>
                    <!-- Display the data in a table row. -->
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo $total; ?></td>
                        <td><?php echo $present; ?></td>
                        <td><?php echo $absent; ?></td>
                        <td><?php echo $percentage; ?>%</td>
                    </tr>
                    <?php 
                        endwhile;
                    else: ?>
                        <!-- If no records are found, display a message in the table. -->
                        <tr><td colspan="5" class="text-center">No attendance records found for the selected criteria.</td></tr>
                    <?php endif; $stmt->close(); ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- --- JAVASCRIPT FOR DYNAMIC COURSE DROPDOWN --- -->
<script>
// Add an event listener to the batch dropdown that triggers when its value changes.
document.getElementById('batch_id').addEventListener('change', function() {
    const batchId = this.value;
    const courseSelect = document.getElementById('course_id');
    // Display a "Loading..." message while fetching data.
    courseSelect.innerHTML = '<option value="">Loading...</option>';

    // If a valid batch is selected...
    if (batchId) {
        // ...fetch the list of courses for that batch from the API endpoint.
        fetch('api_get_courses.php?batch_id=' + batchId)
            .then(response => response.json()) // Parse the response as JSON.
            .then(data => {
                // When data is received, clear the dropdown and add a default option.
                courseSelect.innerHTML = '<option value="">Select a Course</option>';
                // Loop through the received course data and add each one as an option.
                data.forEach(course => {
                    const option = new Option(course.course_name, course.course_id);
                    courseSelect.add(option);
                });
            })
            .catch(error => {
                // If there's an error, log it and show an error message in the dropdown.
                console.error('Error fetching courses:', error);
                courseSelect.innerHTML = '<option value="">Error loading courses</option>';
            });
    } else {
        // If no batch is selected, reset the dropdown.
        courseSelect.innerHTML = '<option value="">Select a Course</option>';
    }
});
</script>

<?php include 'footer.php'; ?>
