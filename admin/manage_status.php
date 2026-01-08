<?php
session_start();
// Security check: Only Teachers can access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher') {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../includes/config.php');
include(__DIR__ . '/../includes/log_helper.php'); // For logging all management actions

$current_user_id = $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role'];
$message = '';

// --- Logic for Toggling Exam Status ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    
    $exam_id = (int)$_POST['exam_id'];
    $current_status = (int)$_POST['current_status'];
    
    // Determine the new status (1=Active, 0=Inactive)
    $new_status = $current_status == 1 ? 0 : 1;
    $status_text = $new_status == 1 ? 'Active' : 'Inactive';

    try {
        // Fetch exam subject for better logging
        $sql_subject = "SELECT subject FROM exams WHERE id = :id";
        $stmt_subject = $dbh->prepare($sql_subject);
        $stmt_subject->execute([':id' => $exam_id]);
        $exam_subject = $stmt_subject->fetchColumn();

        $sql_update = "UPDATE exams SET is_active = :status WHERE id = :id";
        $stmt = $dbh->prepare($sql_update);
        $stmt->execute([':status' => $new_status, ':id' => $exam_id]);

        log_action($dbh, $current_user_id, $current_user_role, "Changed Exam Status: Exam '{$exam_subject}' (ID: {$exam_id}) set to {$status_text}.");
        $message = '<div class="alert alert-success">Exam **' . htmlspecialchars($exam_subject) . '** status successfully changed to **' . $status_text . '**!</div>';
        
    } catch (PDOException $e) {
        // Check for missing column error first
        if (strpos($e->getMessage(), 'Unknown column \'is_active\'') !== false) {
             $message = '<div class="alert alert-danger">Database Error: **Missing `is_active` column** in the `exams` table. Please run the SQL command provided above.</div>';
        } else {
             $message = '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// --- Fetch All Exams with Status (Read) ---
$sql_exams = "SELECT id, subject, time_limit, is_active FROM exams ORDER BY id DESC";
$query_exams = $dbh->query($sql_exams);
$exams = $query_exams->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Status | Teacher</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* Reusing the established Blue/Boxed UI styles */
:root {
    --primary-color: #3b82f6; 
    --secondary-color: #14b8a6; 
    --blue-sidebar: #2563eb; 
    --link-hover-bg: rgba(255, 255, 255, 0.15); 
}
body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; margin: 0; }
.sidebar { width: 270px; height: 100vh; background-color: var(--blue-sidebar); color: white; position: fixed; top: 0; left: 0; box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2); }
.sidebar .logo { text-align: center; padding: 30px 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
.sidebar .logo img { width: 90px; height: 90px; border-radius: 50%; margin-bottom: 10px; object-fit: cover; border: 3px solid rgba(255,255,255,0.2); }
.sidebar h4 { font-weight: 700; font-size: 19px; color: #fff; letter-spacing: 0.5px; }
.sidebar ul { list-style: none; padding: 20px 0; margin: 0; }
.sidebar ul li { margin: 5px 15px; }
.sidebar ul li a { display: flex; align-items: center; padding: 12px 18px; color: #e2e8f0; text-decoration: none; border-radius: 8px; transition: 0.3s ease; font-size: 15px; }
.sidebar ul li a i { margin-right: 15px; font-size: 17px; }
.sidebar ul li a:hover { background-color: var(--link-hover-bg); color: #fff; }
.sidebar ul li a.active { background-color: var(--primary-color); color: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.2); font-weight: 600; }
.main-content { margin-left: 270px; padding: 30px; }
.header { background: white; padding: 20px 30px; margin: -30px -30px 30px -30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
.header h3 { font-weight: 700; color: var(--blue-sidebar); margin: 0; font-size: 24px; }
.header p { color: #64748b; margin: 0; font-size: 14px; }
.table thead { background-color: var(--blue-sidebar); color: white; }
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <img src="../images/OIP (22).jpg" alt="School Logo">
        <h4>Online Examination</h4>
    </div>
    <ul>
        <li><a href="dashboard.php"><i class="fa fa-home"></i>Home</a></li>
        <li><a href="manage_examinee.php"><i class="fa fa-user-graduate"></i>Manage Examinee</a></li>
        <li><a href="manage_exam.php"><i class="fa fa-file-alt"></i>Manage Exam</a></li>
        <li><a href="manage_question.php"><i class="fa fa-question-circle"></i>Manage Question</a></li>
        <li><a href="manage_account.php"><i class="fa fa-users"></i>Manage Account</a></li>
        <li><a href="system_log.php"><i class="fa fa-clipboard-list"></i>System Log</a></li>
        <li><a href="manage_status.php" class="active"><i class="fa fa-cog"></i>Manage Status</a></li>
        <li><a href="advance_search.php"><i class="fa fa-search"></i>Advance Search</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h3>Manage Exam Status 🚦</h3>
            <p>Control the availability (Active/Inactive) of all exams.</p>
        </div>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>

    <?php echo $message; ?>

    <div class="card p-4 shadow-sm">
        <h5 class="fw-bold mb-4">Exam Status Overview</h5>
        
        <?php if (!empty($exams)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 40%;">Subject</th>
                            <th style="width: 15%;">Time Limit (mins)</th>
                            <th style="width: 20%;">Current Status</th>
                            <th style="width: 20%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam): 
                            $is_active = $exam['is_active'];
                            $status_text = $is_active ? 'Active' : 'Inactive';
                            $badge_color = $is_active ? 'success' : 'danger';
                            $button_text = $is_active ? 'Set Inactive' : 'Set Active';
                            $button_color = $is_active ? 'danger' : 'success';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($exam['id']); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($exam['subject']); ?></td>
                                <td><?php echo htmlspecialchars($exam['time_limit']); ?></td>
                                <td><span class="badge bg-<?php echo $badge_color; ?> fs-6"><?php echo $status_text; ?></span></td>
                                <td>
                                    <form method="POST" action="manage_status.php" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam['id']); ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $is_active; ?>">
                                        <button type="submit" class="btn btn-sm btn-<?php echo $button_color; ?>" 
                                            onclick="return confirm('Are you sure you want to change the status of <?php echo htmlspecialchars($exam['subject']); ?> to <?php echo $is_active ? 'INACTIVE' : 'ACTIVE'; ?>?');">
                                            <i class="fa fa-toggle-<?php echo $is_active ? 'off' : 'on'; ?>"></i> <?php echo $button_text; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-3">No exams found. Please create exams first in the Manage Exam page.</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>