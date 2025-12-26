<?php
// --- SESSION MANAGEMENT ---
// Check if a session is not already active.
// Sessions are used to store user information (like login status) across multiple pages.
if (session_status() == PHP_SESSION_NONE) {
    // If no session is active, start a new one.
    session_start();
}

// --- DATABASE CONNECTION ---
// Include the database connection file. 
// 'include_once' ensures it's only included one time, even if multiple files try to include it.
include_once 'db.php';
?>
<!DOCTYPE html> <!-- Defines the document type as HTML5 -->
<html lang="en"> <!-- The root element of an HTML page, with the language set to English -->
<head>
    <!-- --- METADATA --- -->
    <meta charset="UTF-8"> <!-- Specifies the character encoding for the document, UTF-8 is standard -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Ensures the page is responsive and looks good on all devices -->
    <title>Student Attendance System</title> <!-- The title that appears in the browser tab -->

    <!-- --- STYLESHEETS --- -->
    <!-- Link to Bootstrap CSS for pre-built styles and components -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Link to Bootstrap Icons for using icons like the ones in the navigation bar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- --- CUSTOM STYLES --- -->
    <style>
        /* Apply styles to the whole page */
        body {
            background-color: #f8f9fa; /* A light grey background color */
        }
        /* Style for the navigation bar */
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1); /* Adds a subtle shadow for a "lifted" effect */
        }
        /* Style for card elements used throughout the site */
        .card {
            border: none; /* Removes the default border */
            box-shadow: 0 4px 8px rgba(0,0,0,.1); /* Adds a shadow to make cards stand out */
        }
    </style>
</head>
<body> <!-- The main container for all visible content on the page -->

<!-- --- NAVIGATION BAR --- -->
<!-- The 'nav' element represents a section with navigation links. -->
<!-- Bootstrap classes are used for styling: 'navbar-expand-lg' makes it responsive, 'navbar-dark bg-dark' gives it a dark theme. -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid"> <!-- A full-width container for the navbar contents -->
        
        <!-- Brand/Logo Link: The main link on the left of the navbar. -->
        <!-- The link destination ('href') changes depending on the user's role (admin or student). -->
        <a class="navbar-brand" href="<?php echo isset($_SESSION['role']) && $_SESSION['role'] == 'admin' ? 'dashboard.php' : 'student_dashboard.php'; ?>">
            <i class="bi bi-journal-check"></i> <!-- An icon from Bootstrap Icons -->
            University Student Attendance System
        </a>

        <!-- Mobile Menu Button: This button appears on small screens to toggle the navigation links. -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span> <!-- The "hamburger" icon for the button -->
        </button>

        <!-- Collapsible Menu: This div contains the navigation links and is hidden on small screens until the button is clicked. -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- 'ms-auto' aligns the navigation links to the right. -->
            <ul class="navbar-nav ms-auto">
                
                <!-- --- DYNAMIC NAVIGATION LINKS --- -->
                <!-- PHP logic to show different links based on whether a user is logged in. -->
                <?php if (isset($_SESSION['user_id'])): // This checks if a user ID is stored in the session. ?>
                    
                    <!-- If the user is an ADMIN, show admin-specific links. -->
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="mark_attendance.php"><i class="bi bi-pencil-square"></i> Mark Attendance</a></li>
                        
                        <!-- Dropdown Menu for Management Pages -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="manageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-gear-fill"></i> Manage
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="manageDropdown">
                                <li><a class="dropdown-item" href="students.php"><i class="bi bi-people"></i> Students</a></li>
                                <li><a class="dropdown-item" href="manage_batches.php"><i class="bi bi-collection"></i> Batches</a></li>
                                <li><a class="dropdown-item" href="manage_courses.php"><i class="bi bi-book"></i> Courses</a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
                    
                    <!-- If the user is a STUDENT, show student-specific links. -->
                    <?php elseif ($_SESSION['role'] == 'student'): ?>
                        <li class="nav-item"><a class="nav-link" href="student_dashboard.php"><i class="bi bi-person-circle"></i> My Attendance</a></li>
                    <?php endif; ?>
                    
                    <!-- The Logout link is shown to ALL logged-in users (both admin and student). -->
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                
                <?php else: // If the user is NOT logged in (no user_id in session). ?>
                    <!-- Show the Login link. -->
                    <li class="nav-item"><a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- This container holds the main content of each page. It is opened here and closed in 'footer.php'. -->
<div class="container">
