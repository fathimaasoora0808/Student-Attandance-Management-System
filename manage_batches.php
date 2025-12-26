<?php
// --- SETUP AND SECURITY ---
include 'header.php';
// Ensure only admins can access this page.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- HANDLE BATCH DELETION ---
// Check if a 'delete_id' is present in the URL's query string.
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    // Prepare a DELETE statement to remove the batch.
    // Note: The database is set up with 'ON DELETE CASCADE', so deleting a batch
    // will automatically delete related students and their attendance records.
    $stmt = $conn->prepare("DELETE FROM batches WHERE batch_id = ?");
    $stmt->bind_param("i", $delete_id);
    // Execute the statement and set a success or error message.
    if ($stmt->execute()) {
        $success_message = "Batch deleted successfully.";
    } else {
        $error_message = "Error deleting batch: " . $conn->error;
    }
    $stmt->close();
}

// --- HANDLE ADDING A NEW BATCH ---
// Check if the form was submitted to add a new batch.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_batch'])) {
    // Get the batch name and the list of selected course IDs from the form.
    $batch_name = trim($_POST['batch_name']);
    $course_ids = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];

    // Validate that a name was provided and exactly 6 courses were selected.
    if (!empty($batch_name) && count($course_ids) == 6) {
        // Use a transaction to ensure all queries succeed or none do.
        $conn->begin_transaction();
        try {
            // Step 1: Insert the new batch into the 'batches' table.
            $stmt = $conn->prepare("INSERT INTO batches (batch_name) VALUES (?)");
            $stmt->bind_param("s", $batch_name);
            $stmt->execute();
            $batch_id = $stmt->insert_id; // Get the ID of the new batch.
            $stmt->close();

            // Step 2: Link the selected courses to the new batch in the 'batch_courses' table.
            $stmt = $conn->prepare("INSERT INTO batch_courses (batch_id, course_id) VALUES (?, ?)");
            foreach ($course_ids as $course_id) {
                $stmt->bind_param("ii", $batch_id, $course_id);
                $stmt->execute();
            }
            $stmt->close();

            // If both steps were successful, commit the transaction.
            $conn->commit();
            $success_message = "Batch and course assignments added successfully!";
        } catch (Exception $e) {
            // If any step failed, roll back the transaction and show an error.
            $conn->rollback();
            $error_message = "Error creating batch. Please try again. " . $e->getMessage();
        }
    } else {
        // If validation fails, show an error message.
        $error_message = "Please provide a batch name and select exactly 6 courses.";
    }
}

// --- DATA FETCHING FOR THE PAGE ---
// Fetch all courses to populate the multi-select dropdown in the "Add Batch" form.
$courses_result = $conn->query("SELECT * FROM courses ORDER BY course_name");

// Fetch all existing batches and their assigned courses to display in the list.
$batches_query = "
    SELECT 
        b.batch_id, 
        b.batch_name, 
        -- Concatenate all course names for a batch into a single string.
        GROUP_CONCAT(c.course_name SEPARATOR ', ') as courses
    FROM batches b
    -- Join to get the course IDs linked to each batch.
    LEFT JOIN batch_courses bc ON b.batch_id = bc.batch_id
    -- Join to get the course names from the course IDs.
    LEFT JOIN courses c ON bc.course_id = c.course_id
    -- Group the results by batch to get one row per batch.
    GROUP BY b.batch_id, b.batch_name
    ORDER BY b.batch_name";
$batches_result = $conn->query($batches_query);
?>

<!-- --- HTML CONTENT --- -->
<div class="container mt-4">
    <h1 class="mb-4">Manage Batches</h1>

    <!-- Add New Batch Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Add New Batch</h3>
        </div>
        <div class="card-body">
            <!-- Display success or error messages if they exist -->
            <?php if(isset($success_message)) { echo "<div class='alert alert-success'>$success_message</div>"; } ?>
            <?php if(isset($error_message)) { echo "<div class='alert alert-danger'>$error_message</div>"; } ?>
            
            <form action="manage_batches.php" method="post" id="addBatchForm">
                <div class="mb-3">
                    <label for="batch_name" class="form-label">Batch Name (e.g., 2023/2024)</label>
                    <input type="text" class="form-control" id="batch_name" name="batch_name" required>
                </div>
                <div class="mb-3">
                    <label for="course_ids" class="form-label">Assign Courses (Select exactly 6)</label>
                    <!-- 'multiple' allows selecting more than one option. 'name="course_ids[]"' sends the selections as an array. -->
                    <select multiple class="form-select" id="course_ids" name="course_ids[]" size="10" required>
                        <?php while($course = $courses_result->fetch_assoc()): ?>
                            <option value="<?php echo $course['course_id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <small id="courseHelp" class="form-text text-muted">Hold Ctrl (or Cmd on Mac) to select multiple courses.</small>
                </div>
                <button type="submit" name="add_batch" class="btn btn-primary">Add Batch</button>
            </form>
        </div>
    </div>

    <!-- List of Existing Batches -->
    <div class="card">
        <div class="card-header">
            <h3>Existing Batches</h3>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Batch Name</th>
                        <th>Assigned Courses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($batches_result->num_rows > 0): ?>
                        <?php while($batch = $batches_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars($batch['courses'] ? $batch['courses'] : 'No courses assigned'); ?></td>
                                <td>
                                    <!-- Edit button links to the edit_batch.php page with the batch ID. -->
                                    <a href="edit_batch.php?id=<?php echo $batch['batch_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <!-- Delete button links to this same page with a delete_id parameter. -->
                                    <a href="manage_batches.php?delete_id=<?php echo $batch['batch_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this batch? This will also delete all students in this batch.');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center">No batches found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- --- JAVASCRIPT --- -->
<script>
// Add a submit event listener to the form for client-side validation.
document.getElementById('addBatchForm').addEventListener('submit', function(event) {
    // Find all selected course options.
    const selectedCourses = document.querySelectorAll('#course_ids option:checked');
    // Check if the number of selected courses is not equal to 6.
    if (selectedCourses.length !== 6) {
        // If not, show an alert and prevent the form from submitting.
        alert('You must select exactly 6 courses.');
        event.preventDefault();
    }
});
</script>

<?php include 'footer.php'; ?>
