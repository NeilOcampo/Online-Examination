<?php
session_start();
// Security check: Only Teachers can access this page
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher') {
    header("Location: ../login.php");
    exit;
}
include(__DIR__ . '/../includes/config.php');

$user_id = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];
$teacher = $_SESSION['user']['fullname'] ?? 'Teacher';

// --- Handle Delete Operation ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $msg_id = (int)$_GET['id'];
    try {
        $sql_delete = "DELETE FROM contact_messages WHERE id = :id";
        $stmt_delete = $dbh->prepare($sql_delete);
        $stmt_delete->bindParam(':id', $msg_id);
        if ($stmt_delete->execute()) {
            $final_message = '<div class="alert alert-success">Message deleted successfully!</div>';
        } else {
            $final_message = '<div class="alert alert-danger">Failed to delete message.</div>';
        }
    } catch (PDOException $e) {
        $final_message = '<div class="alert alert-danger">Database Error.</div>';
    }
    header("Location: manage_messages.php?msg=" . urlencode($final_message));
    exit;
}

$message = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';

// --- Fetch All Messages ---
$sql_fetch = "SELECT * FROM contact_messages ORDER BY date_sent DESC";
$query_fetch = $dbh->query($sql_fetch);
$contact_messages = $query_fetch->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Messages | Teacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* GINAYA ANG UI MULA SA MANAGE_EXAM.PHP */
        :root {
            --primary-color: #3b82f6; 
            --secondary-color: #14b8a6; 
            --blue-sidebar: #2563eb; 
            --link-hover-bg: rgba(255, 255, 255, 0.15); 
        }
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; margin: 0; }
        
        /* SIDEBAR STYLING */
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
        
        /* MAIN CONTENT STYLING */
        .main-content { margin-left: 270px; padding: 30px; }
        .header { background: white; padding: 20px 30px; margin: -30px -30px 30px -30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
        .header h3 { font-weight: 700; color: var(--blue-sidebar); margin: 0; font-size: 24px; }
        
        .card { border: none; border-radius: 12px; }
        .table thead { background-color: var(--blue-sidebar); color: white; }
        
        .badge-unread { background-color: #ef4444; color: white; }
        .badge-read { background-color: #10b981; color: white; }
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
        <li><a href="manage_messages.php" class="active"><i class="fa fa-envelope"></i>Manage Messages</a></li>
        <li><a href="system_log.php"><i class="fa fa-clipboard-list"></i>System Log</a></li>
        <li><a href="manage_status.php"><i class="fa fa-cog"></i>Manage Status</a></li>
        <li><a href="advance_search.php"><i class="fa fa-search"></i>Advance Search</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h3>Inquiries & Messages ✉️</h3>
        </div>
        <a href="../logout.php" class="btn btn-danger btn-sm px-4 rounded-pill">Logout</a>
    </div>

    <?php echo $message; ?>

    <div class="card p-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">Student Inquiries (Total: <?php echo count($contact_messages); ?>)</h5>
        </div>
        
        <?php if (count($contact_messages) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Date Sent</th>
                            <th style="width: 20%;">Sender Name</th>
                            <th style="width: 20%;">Email</th>
                            <th style="width: 30%;">Message</th>
                            <th style="width: 15%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contact_messages as $msg): ?>
                            <tr>
                                <td class="small text-muted">
                                    <?php echo date("M d, Y h:i A", strtotime($msg['date_sent'])); ?>
                                </td>
                                <td class="fw-bold"><?php echo htmlspecialchars($msg['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($msg['email']); ?></td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;">
                                        <?php echo htmlspecialchars($msg['message']); ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info text-white" 
                                            onclick='viewMessage(<?php echo json_encode($msg); ?>)'
                                            data-bs-toggle="modal" data-bs-target="#viewModal">
                                        <i class="fa fa-eye"></i> View
                                    </button>
                                    <a href="manage_messages.php?action=delete&id=<?php echo $msg['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Delete this message?')">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fa fa-envelope-open text-muted mb-3" style="font-size: 3rem;"></i>
                <p class="text-muted">No messages found in the database.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--primary-color); color: white;">
                <h5 class="modal-title">Message Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>From:</strong> <span id="m_name"></span></p>
                <p><strong>Email:</strong> <span id="m_email"></span></p>
                <hr>
                <p><strong>Message:</strong></p>
                <div class="p-3 bg-light rounded" id="m_text" style="white-space: pre-wrap;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function viewMessage(data) {
        document.getElementById('m_name').innerText = data.full_name;
        document.getElementById('m_email').innerText = data.email;
        document.getElementById('m_text').innerText = data.message;
    }
</script>
</body>
</html>