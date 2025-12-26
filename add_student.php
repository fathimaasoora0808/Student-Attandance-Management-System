<?php
// --- SETUP AND SECURITY ---
include 'header.php';
// Ensure only admins can access this page.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- HANDLE FORM SUBMISSION ---
// Check if the form was submitted to add a new student.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    // Get and sanitize data from the form.
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $batch_id = $_POST['batch_id'];
    $password = 'student123'; // Set a default password for new students.
    // Hash the password for secure storage in the database.
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Validate that all required fields are filled.
    if (!empty($name) && !empty($email) && !empty($batch_id)) {
        // Use a transaction to ensure that both the student and their user account are created successfully.
        $conn->begin_transaction();
        try {
            // Step 1: Insert the new student's details into the 'students' table.
            $stmt = $conn->prepare("INSERT INTO students (name, email, batch_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $name, $email, $batch_id);
            $stmt->execute();
            $student_id = $stmt->insert_id; // Get the ID of the newly created student.
            $stmt->close();

            // Step 2: Create a corresponding user account for the student in the 'users' table.
            $role = 'student';
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, student_id) VALUES (?, ?, ?, ?)");
            // The student's email is used as their username.
            $stmt->bind_param("sssi", $email, $hashed_password, $role, $student_id);
            $stmt->execute();
            $stmt->close();

            // If both steps succeed, commit the transaction to save the changes.
            $conn->commit();
            $success_message = "Student added successfully! Their username is their email and default password is 'student123'.";
        } catch (Exception $e) {
            // If any step fails, roll back the transaction to undo all changes.
            $conn->rollback();
            // Check for a duplicate entry error (MySQL error number 1062).
            if ($conn->errno == 1062) {
                $error_message = "Error: A student with this email already exists.";
            } else {
                $error_message = "An error occurred. Please try again. " . $e->getMessage();
            }
        }
    } else {
        // If validation fails, set an error message.
        $error_message = "Please fill in all fields.";
    }
}

// --- DATA FETCHING FOR THE FORM ---
// Fetch all batches to populate the "Assign to Batch" dropdown.
$batches_result = $conn->query("SELECT * FROM batches ORDER BY batch_name");
?>

<!-- --- HTML CONTENT --- -->
<div class="container mt-4">
    <h1 class="mb-4">Add New Student</h1>

    <div class="card">
        <div class="card-header">
            <h3>Student Details</h3>
        </div>
        <div class="card-body">
            <!-- Display success or error messages if they exist. -->
            <?php if(isset($success_message)) { echo "<div class='alert alert-success'>$success_message</div>"; } ?>
            <?php if(isset($error_message)) { echo "<div class='alert alert-danger'>$error_message</div>"; } ?>
            
            <!-- The form submits data back to this same page using the POST method. -->
            <form action="add_student.php" method="post">
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address (will be used as username)</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="batch_id" class="form-label">Assign to Batch</label>
                    <select class="form-select" id="batch_id" name="batch_id" required>
                        <option value="">Select a Batch</option>
                        <?php while($batch = $batches_result->fetch_assoc()): ?>
                            <option value="<?php echo $batch['batch_id']; ?>"><?php echo htmlspecialchars($batch['batch_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <!-- Informational message about the default password. -->
                <div class="alert alert-info">
                    The default password for the new student will be <strong>student123</strong>. They can be advised to change it later.
                </div>
                <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
