<?php
// --- SETUP AND SECURITY ---
// Start a session to use session variables for messages.
session_start();
// Include only the database connection, as this script doesn't output any HTML.
include 'db.php';

// Ensure only admins can perform this action.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // If not an admin, set an error message and redirect.
    $_SESSION['message'] = "You are not authorized to perform this action.";
    $_SESSION['message_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

// --- GET STUDENT ID ---
// Get the ID of the student to be deleted from the URL.
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- DELETION LOGIC ---
// Proceed only if a valid student ID was provided.
if ($student_id > 0) {
    // Use a transaction to ensure all related data is deleted together.
    $conn->begin_transaction();
    try {
        // WORKAROUND: Temporarily disable foreign key checks.
        // This is a forceful method to bypass issues where 'ON DELETE CASCADE' might not be working
        // as expected in the database schema, ensuring the deletion can proceed.
        $conn->query("SET FOREIGN_KEY_CHECKS=0");

        // Step 1: Manually delete all attendance records for the student.
        // This is done as a safeguard, although the foreign key checks are off.
        $stmt_att = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
        $stmt_att->bind_param("i", $student_id);
        $stmt_att->execute();
        $stmt_att->close();

        // Step 2: Delete the student's login account from the 'users' table.
        $stmt_user = $conn->prepare("DELETE FROM users WHERE student_id = ?");
        $stmt_user->bind_param("i", $student_id);
        $stmt_user->execute();
        $stmt_user->close();

        // Step 3: Finally, delete the main record from the 'students' table.
        $stmt_student = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt_student->bind_param("i", $student_id);
        $stmt_student->execute();
        $stmt_student->close();

        // Re-enable foreign key checks for normal database operation.
        $conn->query("SET FOREIGN_KEY_CHECKS=1");

        // If all steps were successful, commit the transaction to make the changes permanent.
        $conn->commit();
        
        // Set a success message to be displayed on the students page.
        $_SESSION['message'] = "Student and all related records deleted successfully!";
        $_SESSION['message_type'] = "success";

    } catch (Exception $e) {
        // If any step failed, roll back the entire transaction to undo all changes.
        $conn->rollback();
        // CRITICAL: Ensure foreign key checks are re-enabled even if an error occurs.
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        // Set an error message.
        $_SESSION['message'] = "Error deleting student: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
} else {
    // If the student ID was invalid, set an error message.
    $_SESSION['message'] = "Invalid student ID.";
    $_SESSION['message_type'] = "danger";
}

// --- REDIRECT ---
// Redirect back to the students list page to show the result message.
header("Location: students.php");
exit();
?>
