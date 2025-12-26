<?php
// --- SESSION AND DATABASE SETUP ---
// Start a session to store user login information.
session_start();
// Include the database connection file.
include 'db.php';

// --- REDIRECT IF ALREADY LOGGED IN ---
// Check if the user is already logged in by looking for a 'user_id' in the session.
if (isset($_SESSION['user_id'])) {
    // If logged in, redirect them to the appropriate dashboard based on their role.
    if ($_SESSION['role'] === 'admin') {
        header("Location: dashboard.php");
    } else {
        header("Location: student_dashboard.php");
    }
    // Stop the script to ensure the redirect happens.
    exit();
}

// --- HANDLE LOGIN FORM SUBMISSION ---
// Check if the page was loaded via a POST request, which means the login form was submitted.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the username and password from the submitted form data.
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate that both fields were filled out.
    if (!empty($username) && !empty($password)) {
        // --- DATABASE QUERY ---
        // Prepare a SQL statement to prevent SQL injection attacks.
        $stmt = $conn->prepare("SELECT user_id, username, password, role, student_id FROM users WHERE username = ?");
        // Bind the user-provided username to the '?' in the prepared statement. 's' means the data is a string.
        $stmt->bind_param("s", $username);
        // Execute the query.
        $stmt->execute();
        // Get the result of the query.
        $result = $stmt->get_result();

        // Check if exactly one user was found with that username.
        if ($result->num_rows == 1) {
            // Fetch the user's data from the result.
            $user = $result->fetch_assoc();

            // --- PASSWORD VERIFICATION ---
            // Verify that the submitted password matches the hashed password stored in the database.
            if (password_verify($password, $user['password'])) {
                // --- SUCCESSFUL LOGIN ---
                // If the password is correct, store user details in the session.
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Redirect the user to the correct dashboard based on their role.
                if ($user['role'] === 'admin') {
                    header("Location: dashboard.php");
                } else {
                    // If the user is a student, also store their student_id.
                    $_SESSION['student_id'] = $user['student_id'];
                    header("Location: student_dashboard.php");
                }
                // Stop the script after redirecting.
                exit();
            } else {
                // If the password does not match, set an error message.
                $error = "Invalid username or password.";
            }
        } else {
            // If no user was found with that username, set the same generic error message.
            $error = "Invalid username or password.";
        }
        // Close the prepared statement to free up resources.
        $stmt->close();
    } else {
        // If either field was empty, set an error message.
        $error = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom styles for the login page -->
    <style>
        body {
            /* Flexbox properties to center the login card vertically and horizontally. */
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh; /* Full viewport height. */
            background-color: #f8f9fa; /* Light grey background. */
        }
        .login-card {
            width: 100%;
            max-width: 400px; /* Set a maximum width for the login form. */
            padding: 2rem; /* Add some padding inside the card. */
        }
    </style>
</head>
<body>
<!-- The main login form container -->
<div class="card login-card">
    <div class="card-body">
        <h1 class="card-title text-center mb-4">
            <i class="bi bi-journal-check"></i> Login
        </h1>
        
        <!-- Display an error message here if one was set in the PHP code -->
        <?php if(isset($error)) { echo "<div class='alert alert-danger'>$error</div>"; } ?>
        
        <!-- The login form. It submits the data back to this same page (login.php) using the POST method. -->
        <form action="login.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required> <!-- 'required' makes the field mandatory -->
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="d-grid"> <!-- 'd-grid' makes the button take up the full width -->
                <button type="submit" name="login" class="btn btn-primary">Login</button>
            </div>
        </form>
        
        <!-- An informational box with login hints for testing -->
        <div class="alert alert-info mt-3">
            <p class="mb-0"><strong>Admin:</strong> admin / admin123</p>
            <p class="mb-0">Students must be registered by an admin.</p>
        </div>
    </div>
</div>
<!-- Include Bootstrap's JavaScript bundle for any dynamic components (like dropdowns, though not used here). -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
