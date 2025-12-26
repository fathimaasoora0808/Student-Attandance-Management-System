<?php
include 'header.php';
// Ensure only students can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Fetch student's name and batch
$student_info_stmt = $conn->prepare("SELECT s.name, b.batch_name FROM students s JOIN batches b ON s.batch_id = b.batch_id WHERE s.student_id = ?");
$student_info_stmt->bind_param("i", $student_id);
$student_info_stmt->execute();
$student_info_result = $student_info_stmt->get_result();
$student_info = $student_info_result->fetch_assoc();
$student_name = $student_info['name'];
$batch_name = $student_info['batch_name'];
$student_info_stmt->close();

// Fetch the student's attendance statistics for each course in their batch
$query = "
    SELECT 
        c.course_name,
        COUNT(a.id) AS total_classes,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_classes
    FROM students s
    JOIN batch_courses bc ON s.batch_id = bc.batch_id
    JOIN courses c ON bc.course_id = c.course_id
    LEFT JOIN attendance a ON s.student_id = a.student_id AND c.course_id = a.course_id
    WHERE s.student_id = ?
    GROUP BY c.course_id, c.course_name
    ORDER BY c.course_name;
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_result = $stmt->get_result();
?>

<div class="container mt-4">
    <h1 class="mb-2">Welcome, <?php echo htmlspecialchars($student_name); ?>!</h1>
    <p class="lead mb-4">Batch: <?php echo htmlspecialchars($batch_name); ?></p>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Your Attendance Report</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container mb-4" style="position: relative; height:40vh; width:80vw">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Course Name</th>
                                <th>Total Classes Marked</th>
                                <th>Classes Attended</th>
                                <th>Attendance Percentage</th>
                                <th>Exam Eligibility</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $chart_labels = [];
                            $chart_data = [];
                            $chart_colors = [];
                            if ($attendance_result->num_rows > 0):
                                $attendance_result->data_seek(0); // Reset cursor
                                while($row = $attendance_result->fetch_assoc()):
                                    $total_classes = (int)$row['total_classes'];
                                    $present_classes = (int)$row['present_classes'];
                                    $percentage = ($total_classes > 0) ? round(($present_classes / $total_classes) * 100, 2) : 0;
                                    $is_eligible = $percentage >= 80;

                                    $chart_labels[] = $row['course_name'];
                                    $chart_data[] = $percentage;
                                    $chart_colors[] = $is_eligible ? 'rgba(40, 167, 69, 0.7)' : 'rgba(220, 53, 69, 0.7)';
                            ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                        <td><?php echo $total_classes; ?></td>
                                        <td><?php echo $present_classes; ?></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar <?php echo $is_eligible ? 'bg-success' : 'bg-danger'; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $percentage; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($is_eligible): ?>
                                                <span class="badge bg-success">Eligible</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Not Eligible to sit for exam</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No attendance data found for your courses yet.</td>
                                </tr>
                            <?php endif; $stmt->close(); ?>
                        </tbody>
                    </table>
                    <div class="alert alert-info mt-3">
                        <strong>Note:</strong> You must have at least 80% attendance in a course to be eligible for the final exam.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const attendanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Attendance Percentage',
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: <?php echo json_encode($chart_colors); ?>,
                borderColor: <?php echo json_encode(array_map(function($color) { return str_replace('0.7', '1', $color); }, $chart_colors)); ?>,
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%'
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Attendance: ' + context.raw + '%';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include 'footer.php'; ?>
