<?php
// --- SETUP AND SECURITY ---
include 'header.php';
// Ensure only admins can access this page.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- GET STUDENT ID ---
// Get the student ID from the URL's query string (e.g., edit_student.php?id=12).
// Cast it to an integer for security.
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- HANDLE FORM SUBMISSION (UPDATE LOGIC) ---
// Check if the form was submitted to update the student's details.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_student'])) {
    // Get and sanitize the data from the form.
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $batch_id = (int)$_POST['batch_id'];

    // Validate that all fields are filled correctly.
    if (!empty($name) && !empty($email) && $batch_id > 0) {
        // Use a transaction to ensure both tables are updated successfully.
        $conn->begin_transaction();
        try {
            // Step 1: Update the student's details in the 'students' table.
            $stmt = $conn->prepare("UPDATE students SET name = ?, email = ?, batch_id = ? WHERE student_id = ?");
            $stmt->bind_param("ssii", $name, $email, $batch_id, $student_id);
            $stmt->execute();
            $stmt->close();

            // Step 2: Update the student's username in the 'users' table (since username is the email).
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE student_id = ?");
            $stmt->bind_param("si", $email, $student_id);
            $stmt->execute();
            $stmt->close();

            // If both updates succeed, commit the transaction.
            $conn->commit();
            $success_message = "Student details updated successfully!";
        } catch (Exception $e) {
            // If any step fails, roll back the transaction.
            $conn->rollback();
            // Check for a duplicate entry error (e.g., if the new email is already taken).
            if ($conn->errno == 1062) {
                $error_message = "Error: Another student or user already exists with this email.";
            } else {
                $error_message = "An error occurred during the update. " . $e->getMessage();
            }
        }
    } else {
        // If validation fails, set an error message.
        $error_message = "Please fill in all fields.";
    }
}

// --- DATA FETCHING FOR THE FORM ---
// Fetch the student's current details to pre-fill the form fields.
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_result = $stmt->get_result();
if ($student_result->num_rows == 1) {
    $student = $student_result->fetch_assoc();
} else {
    // If no student is found with the given ID, redirect back to the main student list.
    header("Location: students.php");
    exit();
}
$stmt->close();

// Fetch all batches to populate the batch selection dropdown.
$batches_result = $conn->query("SELECT * FROM batches ORDER BY batch_name");
?>

<!-- --- HTML CONTENT --- -->
<div class="container mt-4">
    <h1 class="mb-4">Edit Student</h1>

    <div class="card">
        <div class="card-header">
            <h3>Editing Details for <?php echo htmlspecialchars($student['name']); ?></h3>
        </div>
        <div class="card-body">
            <!-- Display success or error messages if they exist. -->
            <?php if(isset($success_message)) { echo "<div class='alert alert-success'>$success_message</div>"; } ?>
            <?php if(isset($error_message)) { echo "<div class='alert alert-danger'>$error_message</div>"; } ?>
            
            <!-- The form submits back to this same page to process the update. -->
            <form action="edit_student.php?id=<?php echo $student_id; ?>" method="post">
                <!-- Name Input -->
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                </div>
                <!-- Email Input -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address (Username)</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                </div>
                <!-- Batch Selection Dropdown -->
                <div class="mb-3">
                    <label for="batch_id" class="form-label">Batch</label>
                    <select class="form-select" id="batch_id" name="batch_id" required>
                        <option value="">Select a Batch</option>
                        <?php while($batch = $batches_result->fetch_assoc()): ?>
                            <!-- Pre-select the student's current batch. -->
                            <option value="<?php echo $batch['batch_id']; ?>" <?php if($student['batch_id'] == $batch['batch_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($batch['batch_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <!-- Form Buttons -->
                <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
                <a href="students.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
