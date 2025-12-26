<?php
// --- SETUP ---
// This script serves as a simple API endpoint. It doesn't display any HTML.
// Include the database connection file.
include 'db.php';

// --- SET HTTP HEADER ---
// Set the content type header to 'application/json'.
// This tells the browser (or any client) that the response from this script is in JSON format.
header('Content-Type: application/json');

// --- GET BATCH ID ---
// Get the 'batch_id' from the URL's query string (e.g., api_get_courses.php?batch_id=2).
// This is sent by the JavaScript 'fetch' request.
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

// --- FETCH AND RETURN COURSES ---
// Proceed only if a valid, positive batch ID was provided.
if ($batch_id > 0) {
    // Prepare a SQL statement to select all courses associated with the given batch ID.
    // This joins the 'courses' and 'batch_courses' tables.
    $stmt = $conn->prepare("SELECT c.course_id, c.course_name FROM courses c JOIN batch_courses bc ON c.course_id = bc.course_id WHERE bc.batch_id = ? ORDER BY c.course_name");
    // Bind the batch ID to the prepared statement to prevent SQL injection.
    $stmt->bind_param("i", $batch_id);
    // Execute the query.
    $stmt->execute();
    // Get the result set from the executed statement.
    $result = $stmt->get_result();
    // Fetch all rows from the result set into an associative array.
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    // Close the statement to free up resources.
    $stmt->close();
    
    // --- ENCODE AND OUTPUT ---
    // Encode the array of courses into a JSON string and output it.
    // This JSON string is what the JavaScript 'fetch' request receives.
    echo json_encode($courses);
} else {
    // If no valid batch ID was provided, return an empty JSON array.
    echo json_encode([]);
}
?>
