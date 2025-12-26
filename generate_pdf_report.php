<?php
session_start();
require('fpdf/fpdf.php'); // Adjust the path to the FPDF library
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

if (!$batch_id || !$course_id || !$month) {
    die("Invalid parameters for report generation.");
}

// Fetch batch and course names for the report header
$batch_info = $conn->query("SELECT batch_name FROM batches WHERE batch_id = $batch_id")->fetch_assoc();
$course_info = $conn->query("SELECT course_name FROM courses WHERE course_id = $course_id")->fetch_assoc();
$report_title = "Attendance Report for " . htmlspecialchars($course_info['course_name']);
$report_subtitle = "Batch: " . htmlspecialchars($batch_info['batch_name']) . " | Month: " . date('F Y', strtotime($month));

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        global $report_title, $report_subtitle;
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, $report_title, 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, $report_subtitle, 0, 1, 'C');
        $this->Ln(10);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Colored table
    function FancyTable($header, $data)
    {
        // Colors, line width and bold font
        $this->SetFillColor(25, 48, 71);
        $this->SetTextColor(255);
        $this->SetDrawColor(128, 0, 0);
        $this->SetLineWidth(.3);
        $this->SetFont('', 'B');
        // Header
        $w = array(70, 30, 30, 30, 30);
        for ($i = 0; $i < count($header); $i++)
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        $this->Ln();
        // Color and font restoration
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('');
        // Data
        $fill = false;
        foreach ($data as $row) {
            $this->Cell($w[0], 6, $row[0], 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 6, $row[1], 'LR', 0, 'C', $fill);
            $this->Cell($w[2], 6, $row[2], 'LR', 0, 'C', $fill);
            $this->Cell($w[3], 6, $row[3], 'LR', 0, 'C', $fill);
            $this->Cell($w[4], 6, $row[4] . '%', 'LR', 0, 'C', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

$header = array('Student Name', 'Total Classes', 'Present', 'Absent', 'Percentage');

$report_month = date('m', strtotime($month));
$report_year = date('Y', strtotime($month));

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

$stmt = $conn->prepare($query);
$stmt->bind_param("iisi", $course_id, $report_month, $report_year, $batch_id);
$stmt->execute();
$report_result = $stmt->get_result();

$data = [];
while ($row = $report_result->fetch_assoc()) {
    $total = (int)$row['total_classes'];
    $present = (int)$row['present_classes'];
    $absent = (int)$row['absent_classes'];
    $percentage = ($total > 0) ? round(($present / $total) * 100, 2) : 0;
    $data[] = [
        htmlspecialchars_decode($row['name']),
        $total,
        $present,
        $absent,
        $percentage
    ];
}
$stmt->close();

$pdf->FancyTable($header, $data);
$pdf->Output('D', 'Attendance_Report_' . date('Y-m-d') . '.pdf');
?>
