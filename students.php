<?php
// --- SETUP AND SECURITY ---
include 'header.php';
// Ensure only admins can access this page.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>

<!-- --- HTML CONTENT --- -->
<div class="container mt-4">
    <h1 class="mb-4">Manage Students</h1>

    <?php
    // --- DISPLAY SESSION MESSAGES ---
    // Check for any success or error messages stored in the session.
    if (isset($_SESSION['message'])) {
        // Display the message in a Bootstrap alert.
        echo "<div class='alert alert-{$_SESSION['message_type']}'>{$_SESSION['message']}</div>";
        // Clear the message from the session so it doesn't show again.
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3>All Students</h3>
            <!-- "Add New Student" button that links to the add_student.php page. -->
            <a href="add_student.php" class="btn btn-success">
                <i class="bi bi-person-plus-fill"></i> Add New Student
            </a>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Batch</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // --- FETCH AND DISPLAY STUDENTS ---
                    // SQL query to select all students and join with the 'batches' table to get the batch name.
                    $query = "
                        SELECT s.student_id, s.name, s.email, b.batch_name 
                        FROM students s 
                        LEFT JOIN batches b ON s.batch_id = b.batch_id
                        ORDER BY s.student_id DESC
                    ";
                    $result = $conn->query($query);
                    
                    // Check if any students were found.
                    if ($result->num_rows > 0):
                        // Loop through each student record.
                        while($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo $row['student_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['batch_name'] ? $row['batch_name'] : 'N/A'); ?></td>
                        <td>
                            <!-- Edit button links to the edit page with the student's ID. -->
                            <a href="edit_student.php?id=<?php echo $row['student_id']; ?>" class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil-fill"></i> Edit
                            </a>
                            <!-- Delete button links to the delete script with the student's ID. -->
                            <a href="delete_student.php?id=<?php echo $row['student_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this student? This will also delete their login account and attendance records.')">
                                <i class="bi bi-trash-fill"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    else: ?>
                        <!-- If no students are found, display a message spanning all columns. -->
                        <tr>
                            <td colspan="5" class="text-center">No students found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
