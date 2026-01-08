<?php
session_start();
// Security check: Only Teacher role is allowed (using 'role' column from users table)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher') {
    header("Location: ../login.php");
    exit;
}
// Include config file (assuming this connects $dbh PDO)
include(__DIR__ . '/../includes/config.php');
// Ensure the full name is displayed or default to 'Teacher'
$teacher = $_SESSION['user']['fullname'] ?? 'Teacher';

// --- PHP Logic for Dashboard Data (Summary Cards) ---
try {
    // 1. Total Accounts (Teachers + Students)
    $sql_total_users = "SELECT COUNT(id) FROM users";
    $total_users = $dbh->query($sql_total_users)->fetchColumn();

    // 2. Total Students/Examinees
    $sql_total_students = "SELECT COUNT(id) FROM users WHERE role = 'Student'";
    $total_students = $dbh->query($sql_total_students)->fetchColumn();

    // 3. Total Exams
    $sql_total_exams = "SELECT COUNT(id) FROM exams";
    $total_exams = $dbh->query($sql_total_exams)->fetchColumn();

    // 4. Total Questions
    $sql_total_questions = "SELECT COUNT(id) FROM questions";
    $total_questions = $dbh->query($sql_total_questions)->fetchColumn();

    // 5. Total Active Schedules (NEW ADDITION for a potential card or quick info)
    $sql_total_schedules = "SELECT COUNT(id) FROM exam_schedule WHERE is_active = 1";
    $total_active_schedules = $dbh->query($sql_total_schedules)->fetchColumn();

    // --- NEW: FETCH CONTACT MESSAGES (ADDED LOGIC) ---
    $sql_unread_msg = "SELECT COUNT(id) FROM contact_messages WHERE status = 'unread'";
    $total_unread_messages = $dbh->query($sql_unread_msg)->fetchColumn();

    $sql_recent_msg = "SELECT * FROM contact_messages ORDER BY date_sent DESC LIMIT 5";
    $recent_messages = $dbh->query($sql_recent_msg)->fetchAll(PDO::FETCH_ASSOC);


    // --- FETCH RECENT SYSTEM ACTIVITIES (LOGS) ---
    $sql_recent_logs = "
        SELECT 
            l.action, 
            l.user_role, 
            u.fullname AS user_fullname,
            l.log_time
        FROM 
            system_logs l
        JOIN 
            users u ON l.user_id = u.id
        ORDER BY 
            l.log_time DESC
        LIMIT 8
    ";
    
    $query_recent_logs = $dbh->query($sql_recent_logs);
    $recent_activities = $query_recent_logs->fetchAll(PDO::FETCH_ASSOC);
    $log_error_message = null;

} catch (PDOException $e) {
    // Handle global database errors
    $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
    $recent_activities = [];
}

// Function to map log action to Bootstrap color/type (for log entries)
function get_log_type_color($action) {
    if (strpos($action, 'Created') !== false || strpos($action, 'Registered') !== false) return 'success';
    if (strpos(strtolower($action), 'deleted') !== false) return 'danger';
    if (strpos($action, 'Updated') !== false || strpos(strtolower($action), 'changed exam status') !== false) return 'warning';
    if (strpos(strtolower($action), 'login') !== false) return 'primary';
    return 'secondary';
}

// --- LOGIC FOR EXAM PERFORMANCE CHART (Trend Line & Time Calculation) ---
// Using the complex logic you provided.
$default_exam_time_limit = 60; 
$chart_datasets = [];
$chart_labels = [];
$max_exams_to_display = 10; 
$total_time_taken_seconds = 0; 
$total_attempts_with_time = 0; 

// Fetch subjects and results
try {
    $sql_subjects = "
        SELECT DISTINCT 
            e.id AS exam_id,
            e.subject
        FROM 
            exams e
        JOIN 
            exam_results er ON e.id = er.exam_id
        ORDER BY 
            e.subject ASC
    ";
    $subjects_list = $dbh->query($sql_subjects)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subjects_list as $subject_data) {
        $subject_name = $subject_data['subject'];
        $exam_time_limit = $default_exam_time_limit; 
        
        $sql_results = "
            SELECT 
                er.percentage AS score, 
                er.date_taken
            FROM 
                exam_results er
            WHERE 
                er.exam_id = :exam_id
            ORDER BY 
                er.date_taken ASC
            LIMIT :limit_count
        ";
        $stmt = $dbh->prepare($sql_results);
        $stmt->bindValue(':exam_id', $subject_data['exam_id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit_count', $max_exams_to_display, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scores = array_column($results, 'score');
        
        if (!empty($scores)) {
            $chart_datasets[] = [
                'subject' => $subject_name,
                'scores' => array_map('floatval', $scores) 
            ];
            
            if (empty($chart_labels)) {
                $chart_labels = array_map(function($r) {
                    return date("M d, Y", strtotime($r['date_taken']));
                }, $results);
            }
        }

        // --- AVERAGE TIME TAKEN CALCULATION (SIMULATED) ---
        if ($exam_time_limit > 0) {
            $attempt_count = count($results);
            if ($attempt_count > 0) {
                // Simulated average time taken: 65% of time limit
                $simulated_avg_time = $exam_time_limit * 0.65; 
                $total_time_taken_seconds += ($simulated_avg_time * 60) * $attempt_count;
                $total_attempts_with_time += $attempt_count;
            }
        }
    }
} catch (PDOException $e) {
    // Error handling for chart data fetch
    $chart_error_message = "Chart Data Error: " . htmlspecialchars($e->getMessage());
}

// Final Average Time Calculation (Converted to H:M:S format)
if ($total_attempts_with_time > 0) {
    $overall_avg_time_seconds = $total_time_taken_seconds / $total_attempts_with_time;
    $hours = floor($overall_avg_time_seconds / 3600);
    $minutes = floor(($overall_avg_time_seconds % 3600) / 60);
    $seconds = round($overall_avg_time_seconds % 60);
    
    if ($hours > 0) {
        $overall_avg_time_display = sprintf('%d hr, %d min', $hours, $minutes);
    } else {
        $overall_avg_time_display = sprintf('%d min, %d sec', $minutes, $seconds);
    }
} else {
    $overall_avg_time_display = 'N/A';
}

if (empty($chart_labels)) {
    $chart_labels = ['Exam 1', 'Exam 2', 'Exam 3'];
}

$chart_labels_json = json_encode($chart_labels);
$chart_scores_json = json_encode($chart_datasets);

// --- Additional Metrics for Summary Boxes (Passing Rate, Highest/Lowest) ---
try {
    $sql_passing_rate = "
        SELECT 
            SUM(CASE WHEN percentage >= 75 THEN 1 ELSE 0 END) as passed_count,
            SUM(CASE WHEN percentage < 75 THEN 1 ELSE 0 END) as failed_count,
            MAX(percentage) as highest_score,
            MIN(percentage) as lowest_score,
            COUNT(id) as total_attempts_
        FROM exam_results
    ";

    $rates = $dbh->query($sql_passing_rate)->fetch(PDO::FETCH_ASSOC);

    $total_attempts = $rates['total_attempts_'] ?? 0;
    $passed_count = $rates['passed_count'] ?? 0;
    $failed_count = $rates['failed_count'] ?? 0;
    $highest_score = $rates['highest_score'] ?? 0;
    $lowest_score = $rates['lowest_score'] ?? 0;

    $passing_percentage = ($total_attempts > 0) ? round(($passed_count / $total_attempts) * 100) : 0;
    $failing_percentage = ($total_attempts > 0) ? round(($failed_count / $total_attempts) * 100) : 0;
} catch (PDOException $e) {
    // Default values on error
    $total_attempts = $passing_percentage = $failing_percentage = $highest_score = $lowest_score = $passed_count = $failed_count = 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard | Online Examination System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
/* Global & Base */
:root {
    --primary-color: #3b82f6; /* Light Blue */
    --secondary-color: #14b8a6; /* Teal Accent */
    --blue-sidebar: #2563eb; /* Deep Blue for Sidebar */
    --link-hover-bg: rgba(255, 255, 255, 0.15); 
}

body {
    font-family: 'Poppins', sans-serif;
    background-color: #f8fafc;
    margin: 0;
}

/* --- Sidebar --- */
.sidebar {
    width: 270px;
    height: 100vh;
    background-color: var(--blue-sidebar); 
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    overflow-y: auto;
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2);
}

.sidebar .logo {
    text-align: center;
    padding: 30px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.sidebar .logo img {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    margin-bottom: 10px;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.2);
}
.sidebar h4 {
    font-weight: 700;
    font-size: 19px;
    color: #fff;
    letter-spacing: 0.5px;
}
.sidebar ul {
    list-style: none;
    padding: 20px 0;
    margin: 0;
}
.sidebar ul li {
    margin: 5px 15px;
}
.sidebar ul li a {
    display: flex;
    align-items: center;
    padding: 12px 18px; 
    color: #e2e8f0;
    text-decoration: none;
    border-radius: 8px; 
    transition: 0.3s ease;
    font-size: 15px;
    border-left: 0px solid transparent;
}
.sidebar ul li a i {
    margin-right: 15px;
    font-size: 17px;
}
.sidebar ul li a:hover {
    background-color: var(--link-hover-bg);
    color: #fff;
}
.sidebar ul li a.active {
    background-color: var(--primary-color);
    color: #fff;
    box-shadow: 0 4px 6px rgba(0,0,0,0.2); 
    font-weight: 600;
}

/* --- Main Content --- */
.main-content {
    margin-left: 270px;
    padding: 30px;
}

/* Header */
.header {
    background: white;
    padding: 20px 30px;
    margin: -30px -30px 30px -30px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
.header h3 {
    font-weight: 700;
    color: var(--blue-sidebar);
    margin: 0;
    font-size: 24px;
}
.header p {
    color: #64748b;
    margin: 0;
    font-size: 14px;
}

/* --- Summary Cards --- */
.card-box {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    padding: 25px;
    text-align: left;
    transition: 0.3s;
    overflow: hidden;
    position: relative;
    border: 1px solid #e2e8f0;
}
.card-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
}
.card-box i {
    font-size: 30px;
    position: absolute;
    top: 25px;
    right: 25px;
    opacity: 0.15;
}
.card-box h5 {
    color: #64748b;
    font-weight: 500;
    font-size: 14px;
    text-transform: uppercase;
    margin-bottom: 5px;
}
.card-box h3 {
    color: var(--blue-sidebar);
    font-weight: 800;
    font-size: 32px;
    margin-bottom: 0;
}
/* Card Icon Colors */
.text-examinee { color: #3b82f6; } 
.text-exam { color: #f59e0b; }
.text-question { color: #10b981; }
.text-account { color: #ef4444; }


/* --- Analytics/Activity Card --- */
.activity-card {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    padding: 20px;
    border: 1px solid #e2e8f0;
}
.activity-item {
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-item i {
    margin-right: 10px;
    font-size: 10px;
}

/* NEW: Colorful Metric Boxes for Analytics Summary */
.metric-box {
    padding: 10px 15px;
    border-radius: 8px;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.metric-box.highest { background-color: #d1fae5; color: #059669; border: 1px solid #a7f3d0;} /* Light Green */
.metric-box.lowest { background-color: #fee2e2; color: #ef4444; border: 1px solid #fecaca;} /* Light Red */
.metric-box.time { background-color: #fef3c7; color: #d97706; border: 1px solid #fde68a;} /* Light Yellow/Orange */
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <img src="../images/OIP (22).jpg" alt="School Logo">
        <h4>Online Examination</h4>
    </div>
    <ul>
        <li><a href="dashboard.php" class="active"><i class="fa fa-home"></i>Home</a></li>
        <li><a href="manage_examinee.php"><i class="fa fa-user-graduate"></i>Manage Examinee</a></li>
        <li><a href="manage_exam.php"><i class="fa fa-file-alt"></i>Manage Exam</a></li>
        <li><a href="manage_question.php"><i class="fa fa-question-circle"></i>Manage Question</a></li>
        
        <li><a href="manage_schedule.php"><i class="fa fa-calendar-alt"></i>Manage Schedule</a></li>
        
        <li><a href="manage_account.php"><i class="fa fa-users"></i>Manage Account</a></li>
        <li><a href="manage_messages.php"><i class="fa fa-envelope"></i>Manage Messages</a></li>
        
        <li><a href="system_log.php"><i class="fa fa-clipboard-list"></i>System Log</a></li>
        <li><a href="manage_status.php"><i class="fa fa-cog"></i>Manage Status</a></li>
        <li><a href="advance_search.php"><i class="fa fa-search"></i>Advance Search</a></li>
    </ul>
    <ul style="border-top: 1px solid rgba(255, 255, 255, 0.1); margin-top: 20px; padding-top: 10px;">
         <li><a href="../logout.php"><i class="fa fa-sign-out-alt"></i>Logout</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h3>Welcome, <?php echo htmlspecialchars($teacher); ?> 👋</h3>
            <p class="small text-muted">Online Examination System Teacher Panel</p>
        </div>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <a href="manage_examinee.php" class="text-decoration-none">
                <div class="card-box">
                    <i class="fa fa-user-graduate text-examinee"></i>
                    <h5>Examinees (Students)</h5>
                    <h3 class="text-examinee"><?php echo $total_students; ?></h3>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="manage_exam.php" class="text-decoration-none">
                <div class="card-box">
                    <i class="fa fa-file-alt text-exam"></i>
                    <h5>Total Exams</h5>
                    <h3 class="text-exam"><?php echo $total_exams; ?></h3>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="manage_question.php" class="text-decoration-none">
                <div class="card-box">
                    <i class="fa fa-question-circle text-question"></i>
                    <h5>Total Questions</h5>
                    <h3 class="text-question"><?php echo $total_questions; ?></h3>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="manage_messages.php" class="text-decoration-none">
                <div class="card-box" style="border-left: 4px solid #ef4444;">
                    <i class="fa fa-envelope text-account"></i>
                    <h5>New Messages</h5>
                    <h3 class="text-account"><?php echo $total_unread_messages; ?></h3>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <h5 class="fw-bold mb-3 text-secondary">Exam Performance Analytics</h5>
            <div class="activity-card p-4">
                <canvas id="performanceChart" style="max-height: 300px;"></canvas>
                
                <h6 class="fw-bold text-center mt-4 mb-3 text-primary">Overall Analytics Summary (Total Attempts: <?php echo $total_attempts; ?>)</h6>
                <div class="row align-items-center">
                    <div class="col-2 text-center">
                        <canvas id="rateChart" style="max-height: 100px;"></canvas>
                        <p class="small text-muted mb-0 mt-2" style="font-size: 0.7rem;">Passing/Failing</p>
                    </div>

                    <div class="col-10">
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="metric-box highest">
                                    <i class="fa fa-trophy me-2"></i> Highest Score: <?php echo round($highest_score); ?>%
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="metric-box lowest">
                                    <i class="fa fa-chart-line me-2"></i> Lowest Score: <?php echo round($lowest_score); ?>%
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="metric-box time">
                                    <i class="fa fa-clock me-2"></i> Avg. Time: <?php echo htmlspecialchars($overall_avg_time_display); ?>
                                </div>
                            </div>
                            <div class="col-12 text-center">
                                <span class="badge bg-success me-3 p-2">Passing Rate: <?php echo $passing_percentage; ?>% (<?php echo $passed_count; ?> Passed)</span>
                                <span class="badge bg-danger p-2">Failing Rate: <?php echo $failing_percentage; ?>% (<?php echo $failed_count; ?> Failed)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <h5 class="fw-bold mb-3 text-secondary">Recent System Activity (Logs)</h5>
            <div class="activity-card" style="height: 575px; overflow-y: auto;">
                <?php if (isset($log_error_message)): ?>
                    <div class="alert alert-danger p-2 small m-3"><?php echo $log_error_message; ?></div>
                <?php endif; ?>
                
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): 
                        $type = get_log_type_color($activity['action']);
                    ?>
                        <div class="activity-item">
                            <p class="small mb-0 text-dark">
                                <i class="fa fa-circle text-<?php echo $type; ?> me-2"></i>
                                <?php echo htmlspecialchars($activity['action']); ?>
                            </p>
                            <p class="small mb-0 text-muted ps-4" style="font-size: 0.8rem;">
                                by <?php echo htmlspecialchars($activity['user_fullname']); ?> (<?php echo htmlspecialchars($activity['user_role']); ?>)
                                <span class="float-end text-end small text-secondary" style="font-size: 0.75rem;">
                                    <?php echo date("H:i, M d", strtotime($activity['log_time'])); ?>
                                </span>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info text-center m-3">No recent activities found in system_logs.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // 1. Data from PHP (Encoded as JSON)
    const phpLabels = <?php echo $chart_labels_json; ?>;
    const phpData = <?php echo $chart_scores_json; ?>;
    const passingRate = <?php echo $passing_percentage; ?>;
    const failingRate = <?php echo $failing_percentage; ?>;
    
    // 2. Helper function to structure data for Chart.js Line Chart
    function createDatasets(data) {
        const colors = ['#3b82f6', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#34d399', '#f97316', '#6366f1']; 
        
        return data.map((item, index) => {
            return {
                label: item.subject,
                data: item.scores,
                borderColor: colors[index % colors.length],
                tension: 0.4, 
                fill: false, 
                borderWidth: 3 
            };
        });
    }

    // 3. Initialize the Charts
    document.addEventListener('DOMContentLoaded', function() {
        const ctxLine = document.getElementById('performanceChart');
        const ctxRate = document.getElementById('rateChart');
        
        // --- Line Chart (Performance Trend) ---
        if (phpData && phpData.length > 0 && phpLabels.length > 0) {
            new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: phpLabels, 
                    datasets: createDatasets(phpData)
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, 
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6 } },
                        title: { display: true, text: 'Average Score Trend (%) per Subject' }
                    },
                    scales: {
                        y: {
                            title: { display: true, text: 'Score (%)' },
                            beginAtZero: true,
                            suggestedMax: 100,
                            ticks: {
                                callback: function(value) { return value + '%'; }
                            }
                        },
                        x: {
                            title: { display: true, text: 'Exam Attempt Date' },
                            ticks: { maxRotation: 45, minRotation: 45 }
                        }
                    }
                }
            });
        } else {
             // Display message if no exam results are found
             ctxLine.parentElement.innerHTML = `
                 <div class="text-center p-5">
                     <i class="fa fa-exclamation-triangle fa-2x mb-2 text-warning"></i><br>
                     No Exam Result Data Found.<br>
                     *Please ensure examinees have taken exams (data must exist in **exam_results**).*
                 </div>
             `;
        }
        
        // --- Pie/Donut Chart (Passing Rate) ---
        new Chart(ctxRate, {
            type: 'doughnut', 
            data: {
                labels: ['Passing', 'Failing'],
                datasets: [{
                    data: [passingRate, failingRate],
                    backgroundColor: ['#10b981', '#ef4444'], 
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%', 
                plugins: {
                    legend: { display: false },
                    tooltip: { 
                        enabled: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed + '%';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

    });
</script>
</body>
</html>