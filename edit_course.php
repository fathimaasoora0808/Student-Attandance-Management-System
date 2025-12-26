<?php
// --- SETUP AND SECURITY ---
include 'header.php';
// Ensure only admins can access this page.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- GET COURSE ID ---
// Get the course ID from the URL's query string (e.g., edit_course.php?id=3).
// Cast it to an integer for security.
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Redirect to the main management page if no valid ID is provided.
if ($course_id === 0) {
    header("Location: manage_courses.php");
    exit();
}

// --- HANDLE FORM SUBMISSION (UPDATE LOGIC) ---
// Check if the form was submitted to update the course.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_course'])) {
    // Get and sanitize the new course name from the form.
    $course_name = trim($_POST['course_name']);
    // Validate that the name is not empty.
    if (!empty($course_name)) {
        // Prepare an UPDATE statement to prevent SQL injection.
        $stmt = $conn->prepare("UPDATE courses SET course_name = ? WHERE course_id = ?");
        $stmt->bind_param("si", $course_name, $course_id);
        // Execute the statement and set a success or error message in the session.
        if ($stmt->execute()) {
            $_SESSION['message'] = "Course updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            // This error usually happens if the new name already exists (due to the UNIQUE constraint).
            $_SESSION['message'] = "Error updating course. The name may already exist.";
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
        // Redirect back to the main management page to show the message.
        header("Location: manage_courses.php");
        exit();
    } else {
        // If the name was empty, set an error message to be displayed on this page.
        $error_message = "Course name cannot be empty.";
    }
}

// --- DATA FETCHING FOR THE FORM ---
// Fetch the current details of the course being edited to pre-fill the form field.
$stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
// Check if a course with the given ID was found.
if ($result->num_rows == 1) {
    $course = $result->fetch_assoc();
} else {
    // If no course is found, redirect back to the main page with an error message.
    $_SESSION['message'] = "Course not found.";
    $_SESSION['message_type'] = "danger";
    header("Location: manage_courses.php");
    exit();
}
$stmt->close();
?>

<!-- --- HTML CONTENT --- -->
<div class="container mt-4">
    <h1 class="mb-4">Edit Course</h1>

    <!-- Display an error message if one was set during form processing. -->
    <?php if(isset($error_message)) { echo "<div class='alert alert-danger'>$error_message</div>"; } ?>

    <div class="card">
        <div class="card-header">
            <h3>Update Course Name</h3>
        </div>
        <div class="card-body">
            <!-- The form submits back to this same page to process the update. -->
            <form action="edit_course.php?id=<?php echo $course_id; ?>" method="post">
                <div class="mb-3">
                    <label for="course_name" class="form-label">Course Name</label>
                    <!-- The input field is pre-filled with the current course name. -->
                    <!-- 'htmlspecialchars' is used to prevent XSS attacks. -->
                    <input type="text" class="form-control" id="course_name" name="course_name" value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                </div>
                <!-- Form Buttons -->
                <button type="submit" name="update_course" class="btn btn-primary">Update Course</button>
                <a href="manage_courses.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
