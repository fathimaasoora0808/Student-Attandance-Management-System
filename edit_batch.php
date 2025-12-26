<?php
// --- PRE-PROCESSING LOGIC ---
// This block handles all backend logic before any HTML is rendered.
// This approach prevents the "headers already sent" error by ensuring that all
// header() calls for redirects are executed before the HTML output from header.php begins.

// Start the session if it's not already active.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Include the database connection.
include_once 'db.php';

// --- SECURITY CHECK ---
// Ensure only admins can access this page.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- GET BATCH ID ---
// Get the batch ID from the URL and ensure it's a valid integer.
$batch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Redirect if no valid ID is provided. This check happens early.
if ($batch_id === 0) {
    header("Location: manage_batches.php");
    exit();
}

// --- HANDLE FORM SUBMISSION (UPDATE LOGIC) ---
// Check if the form was submitted to update the batch.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_batch'])) {
    // Get the updated batch name and course IDs from the form.
    $batch_name = trim($_POST['batch_name']);
    $course_ids = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];

    // Validate the submitted data.
    if (!empty($batch_name) && count($course_ids) > 0) {
        // Use a transaction to ensure all database operations succeed or none do.
        $conn->begin_transaction();
        try {
            // Step 1: Update the batch name.
            $stmt = $conn->prepare("UPDATE batches SET batch_name = ? WHERE batch_id = ?");
            $stmt->bind_param("si", $batch_name, $batch_id);
            $stmt->execute();
            $stmt->close();

            // Step 2: Delete all existing course associations for this batch.
            $stmt = $conn->prepare("DELETE FROM batch_courses WHERE batch_id = ?");
            $stmt->bind_param("i", $batch_id);
            $stmt->execute();
            $stmt->close();

            // Step 3: Insert the new set of course associations.
            $stmt = $conn->prepare("INSERT INTO batch_courses (batch_id, course_id) VALUES (?, ?)");
            foreach ($course_ids as $course_id) {
                $stmt->bind_param("ii", $batch_id, $course_id);
                $stmt->execute();
            }
            $stmt->close();

            // If all steps succeed, commit the changes.
            $conn->commit();
            $_SESSION['message'] = "Batch updated successfully!";
            $_SESSION['message_type'] = "success";
            
            // Redirect back to the main management page.
            header("Location: manage_batches.php");
            exit();
        } catch (Exception $e) {
            // If any step fails, roll back all changes.
            $conn->rollback();
            $_SESSION['message'] = "Error updating batch: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
            
            // Redirect back to the edit page to show the error.
            header("Location: edit_batch.php?id=" . $batch_id);
            exit();
        }
    } else {
        // If validation fails, set an error message and reload the page.
        $_SESSION['message'] = "Batch name cannot be empty and at least one course must be selected.";
        $_SESSION['message_type'] = "danger";
        header("Location: edit_batch.php?id=" . $batch_id);
        exit();
    }
}

// --- DATA FETCHING FOR THE FORM ---
// Fetch the current details of the batch to pre-fill the form.
$stmt = $conn->prepare("SELECT batch_name FROM batches WHERE batch_id = ?");
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 1) {
    $batch = $result->fetch_assoc();
} else {
    // If no batch is found with that ID, redirect with an error message.
    $_SESSION['message'] = "Batch not found.";
    $_SESSION['message_type'] = "danger";
    header("Location: manage_batches.php");
    exit();
}
$stmt->close();

// Fetch the IDs of all courses currently assigned to this batch.
$assigned_courses_stmt = $conn->prepare("SELECT course_id FROM batch_courses WHERE batch_id = ?");
$assigned_courses_stmt->bind_param("i", $batch_id);
$assigned_courses_stmt->execute();
$assigned_courses_result = $assigned_courses_stmt->get_result();
$assigned_course_ids = [];
while ($row = $assigned_courses_result->fetch_assoc()) {
    $assigned_course_ids[] = $row['course_id'];
}
$assigned_courses_stmt->close();

// Fetch all available courses to populate the selection box.
$courses_result = $conn->query("SELECT * FROM courses ORDER BY course_name");

// --- PAGE SETUP ---
// Now that all processing is done, we can safely include the header to start HTML output.
include 'header.php';
?>

<!-- --- HTML CONTENT --- -->
<div class="container mt-4">
    <h1 class="mb-4">Edit Batch</h1>

    <?php
    // Display any session messages (e.g., success or error from the update process).
    if (isset($_SESSION['message'])) {
        echo "<div class='alert alert-{$_SESSION['message_type']}'>{$_SESSION['message']}</div>";
        unset($_SESSION['message'], $_SESSION['message_type']);
    }
    ?>

    <div class="card">
        <div class="card-header">
            <h3>Update Batch Details</h3>
        </div>
        <div class="card-body">
            <form action="edit_batch.php?id=<?php echo $batch_id; ?>" method="post">
                <!-- Batch Name Input -->
                <div class="mb-3">
                    <label for="batch_name" class="form-label">Batch Name</label>
                    <input type="text" class="form-control" id="batch_name" name="batch_name" value="<?php echo htmlspecialchars($batch['batch_name']); ?>" required>
                </div>
                <!-- Course Assignment Selection Box -->
                <div class="mb-3">
                    <label for="course_ids" class="form-label">Assign Courses</label>
                    <select multiple class="form-select" id="course_ids" name="course_ids[]" size="10" required>
                        <?php while($course = $courses_result->fetch_assoc()): ?>
                            <option value="<?php echo $course['course_id']; ?>" <?php echo in_array($course['course_id'], $assigned_course_ids) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl (or Cmd on Mac) to select multiple courses.</small>
                </div>
                <!-- Form Buttons -->
                <button type="submit" name="update_batch" class="btn btn-primary">Update Batch</button>
                <a href="manage_batches.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
