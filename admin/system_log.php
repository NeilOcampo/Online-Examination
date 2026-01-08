<?php
session_start();
// Security check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher') {
    header("Location: ../login.php");
    exit;
}
include(__DIR__ . '/../includes/config.php');
$teacher = $_SESSION['user']['fullname'] ?? 'Teacher';

// --- Pagination Setup ---
$limit = 10; // Logs per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// 1. Count Total Records
$sql_count = "SELECT COUNT(l.id) FROM system_logs l";
$query_count = $dbh->query($sql_count);
$total_logs = $query_count->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// 2. Fetch Logs with Pagination
// Join with 'users' to get the full name of the user who performed the action
$sql_logs = "
    SELECT 
        l.id, l.user_role, l.action, l.log_time, u.fullname AS user_fullname 
    FROM 
        system_logs l
    JOIN 
        users u ON l.user_id = u.id
    ORDER BY 
        l.log_time DESC
    LIMIT :start, :limit
";
$query_logs = $dbh->prepare($sql_logs);
$query_logs->bindParam(':start', $start, PDO::PARAM_INT);
$query_logs->bindParam(':limit', $limit, PDO::PARAM_INT);
$query_logs->execute();
$logs = $query_logs->fetchAll(PDO::FETCH_ASSOC);

// Function to determine badge color based on role
function get_role_badge_color($role) {
    switch ($role) {
        case 'Teacher':
            return 'primary';
        case 'Student':
            return 'info';
        default:
            return 'secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Logs | Teacher</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* Reusing the established Blue/Boxed UI styles */
:root {
    --primary-color: #3b82f6; /* Light Blue */
    --secondary-color: #14b8a6; /* Teal Accent */
    --blue-sidebar: #2563eb; /* Deep Blue for Sidebar */
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
.pagination .page-item.active .page-link { background-color: var(--primary-color); border-color: var(--primary-color); }
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
        <li><a href="system_log.php" class="active"><i class="fa fa-clipboard-list"></i>System Log</a></li>
        <li><a href="manage_status.php"><i class="fa fa-cog"></i>Manage Status</a></li>
        <li><a href="advance_search.php"><i class="fa fa-search"></i>Advance Search</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h3>System Log 📋</h3>
            <p>Tracking all user activities and system changes.</p>
        </div>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>

    <div class="card p-4 shadow-sm">
        <h5 class="fw-bold mb-4">Activity Timeline (Latest first)</h5>
        
        <?php if ($total_logs > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 15%;">Time</th>
                            <th style="width: 20%;">User</th>
                            <th style="width: 10%;">Role</th>
                            <th style="width: 50%;">Action/Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['id']); ?></td>
                                <td><?php echo date("Y-m-d H:i:s", strtotime($log['log_time'])); ?></td>
                                <td><?php echo htmlspecialchars($log['user_fullname']); ?></td>
                                <td><span class="badge bg-<?php echo get_role_badge_color($log['user_role']); ?>"><?php echo htmlspecialchars($log['user_role']); ?></span></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-3">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-info text-center">No system logs found.</div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>