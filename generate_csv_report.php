<?php
// --- SETUP AND SECURITY ---
// Start a session to check for login credentials.
session_start();
// Include the database connection.
include 'db.php';

// Ensure only logged-in admins can access this script.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- GET AND VALIDATE PARAMETERS ---
// Get the report filters (batch, course, month) from the URL.
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// If any of the required parameters are missing, stop the script.
if (!$batch_id || !$course_id || !$month) {
    die("Invalid parameters for report generation.");
}

// --- PREPARE DATABASE QUERY ---
// Extract the month and year from the 'month' parameter for the SQL query.
$report_month = date('m', strtotime($month));
$report_year = date('Y', strtotime($month));

// This is the same SQL query used on the reports.php page to generate the summary.
$query = "
    SELECT 
        s.name,
        COUNT(a.id) AS total_classes,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_classes,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_classes
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id 
        AND a.course_id = ? 
        AND MONTH(a.date) = ? 
        AND YEAR(a.date) = ?
    WHERE s.batch_id = ?
    GROUP BY s.student_id, s.name
    ORDER BY s.name;
";

// Prepare and execute the query with the filter parameters.
$stmt = $conn->prepare($query);
$stmt->bind_param("iisi", $course_id, $report_month, $report_year, $batch_id);
$stmt->execute();
$report_result = $stmt->get_result();

// --- GENERATE AND OUTPUT CSV FILE ---
// Set the filename for the downloaded CSV file.
$filename = "Attendance_Report_" . date('Y-m-d') . ".csv";

// Set HTTP headers to tell the browser to download the file as a CSV.
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open a special PHP file stream that writes directly to the HTTP response body.
$output = fopen('php://output', 'w');

// Write the header row to the CSV file.
fputcsv($output, array('Student Name', 'Total Classes', 'Present', 'Absent', 'Percentage'));

// Loop through each row of the database result.
while ($row = $report_result->fetch_assoc()) {
    // Calculate the attendance percentage.
    $total = (int)$row['total_classes'];
    $present = (int)$row['present_classes'];
    $absent = (int)$row['absent_classes'];
    $percentage = ($total > 0) ? round(($present / $total) * 100, 2) : 0;
    
    // Create an array with the data for the current row.
    $csv_row = [
        htmlspecialchars_decode($row['name']), // Decode HTML entities for the CSV
        $total,
        $present,
        $absent,
        $percentage . '%' // Add a '%' sign to the percentage
    ];
    
    // Write the row to the CSV file.
    fputcsv($output, $csv_row);
}

// --- CLEANUP ---
// Close the database statement.
$stmt->close();
// Close the file stream.
fclose($output);
// Stop the script.
exit();
?>
