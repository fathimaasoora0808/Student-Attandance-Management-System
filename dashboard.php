<?php
include 'header.php';
// Ensure only admins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch stats for the dashboard
$students_count = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$batches_count = $conn->query("SELECT COUNT(*) as count FROM batches")->fetch_assoc()['count'];
$courses_count = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
?>

<div class="container mt-4">
    <h1 class="mb-4">Faculty Dashboard</h1>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">Total Students</div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo $students_count; ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Total Batches</div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo $batches_count; ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info mb-3">
                <div class="card-header">Total Courses</div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo $courses_count; ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3>Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="list-group">
                <a href="mark_attendance.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-check-circle-fill"></i> Mark Attendance
                </a>
                <a href="add_student.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-person-plus-fill"></i> Add New Student
                </a>
                <a href="students.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-people-fill"></i> View All Students
                </a>
                <a href="manage_batches.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-collection-fill"></i> Manage Batches
                </a>
                <a href="manage_courses.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-book-fill"></i> Manage Courses
                </a>
                 <a href="reports.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-file-earmark-bar-graph-fill"></i> View Reports
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
