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

// --- Reusable Function to Log Actions ---
function log_action($dbh, $user_id, $user_role, $action) {
    try {
        $sql = "INSERT INTO system_logs (user_id, user_role, action) VALUES (:user_id, :user_role, :action)";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':user_role', $user_role);
        $stmt->bindParam(':action', $action);
        $stmt->execute();
    } catch (PDOException $e) {
        // Optionally log the error to a separate file
    }
}

$message = '';

// --- Handle Form Submission (Add/Edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    // Ensure time limit is an integer and positive
    $time_limit = (int)$_POST['time_limit']; 
    $exam_id = (int)$_POST['exam_id']; // 0 for Add, > 0 for Edit

    if (empty($subject) || empty($description) || $time_limit <= 0) {
        $message = '<div class="alert alert-danger">All fields are required and time limit must be greater than zero.</div>';
    } else {
        try {
            if ($exam_id > 0) {
                // EDIT Operation
                $sql = "UPDATE exams SET subject = :subject, description = :description, time_limit = :time_limit WHERE id = :id";
                $stmt = $dbh->prepare($sql);
                $stmt->bindParam(':id', $exam_id);
                $action_type = "Updated";
            } else {
                // ADD Operation
                $sql = "INSERT INTO exams (subject, description, time_limit) VALUES (:subject, :description, :time_limit)";
                $stmt = $dbh->prepare($sql);
                $action_type = "Created New";
            }
            
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':time_limit', $time_limit);

            if ($stmt->execute()) {
                $final_id = ($exam_id > 0) ? $exam_id : $dbh->lastInsertId();
                $message = '<div class="alert alert-success">Exam **' . htmlspecialchars($subject) . '** ' . strtolower($action_type) . ' successfully!</div>';
                
                // LOGGING ACTION
                log_action($dbh, $user_id, $user_role, $action_type . " Exam: ID #{$final_id} ({$subject})");

            } else {
                $message = '<div class="alert alert-danger">Failed to process exam data.</div>';
            }
        } catch (PDOException $e) {
             $message = '<div class="alert alert-danger">Database Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- Handle Delete Operation ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $exam_id = (int)$_GET['id'];
    $delete_message = '';
    
    // 1. Check if there are any questions tied to this exam (Foreign Key constraint)
    $sql_check_questions = "SELECT COUNT(id) FROM questions WHERE exam_id = :id";
    $stmt_check = $dbh->prepare($sql_check_questions);
    $stmt_check->bindParam(':id', $exam_id);
    $stmt_check->execute();
    
    if ($stmt_check->fetchColumn() > 0) {
        $delete_message = 'Cannot delete Exam ID #' . $exam_id . '. Please delete all associated questions first.';
        $is_error = true;
    } else {
        try {
            // 2. Fetch exam name for logging
            $sql_name = "SELECT subject FROM exams WHERE id = :id";
            $stmt_name = $dbh->prepare($sql_name);
            $stmt_name->bindParam(':id', $exam_id);
            $stmt_name->execute();
            $exam_name = $stmt_name->fetchColumn();

            // 3. Delete the exam
            $sql_delete = "DELETE FROM exams WHERE id = :id";
            $stmt_delete = $dbh->prepare($sql_delete);
            $stmt_delete->bindParam(':id', $exam_id);

            if ($stmt_delete->execute()) {
                $delete_message = 'Exam **' . htmlspecialchars($exam_name) . '** deleted successfully!';
                
                // LOGGING ACTION
                log_action($dbh, $user_id, $user_role, "Deleted Exam: ID #{$exam_id} ({$exam_name})");
                $is_error = false;
            } else {
                $delete_message = 'Failed to delete exam.';
                $is_error = true;
            }
        } catch (PDOException $e) {
            $delete_message = 'Database Error during deletion.';
            $is_error = true;
        }
    }
    
    // Redirect to clear GET parameters and display result message
    $alert_class = $is_error ? 'alert-danger' : 'alert-success';
    $final_message = '<div class="' . $alert_class . '">' . htmlspecialchars($delete_message) . '</div>';
    
    header("Location: manage_exam.php?msg=" . urlencode($final_message));
    exit;
}

// Check for redirected message (from deletion)
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
}


// --- Fetch All Exams for Display ---
$sql_fetch = "
    SELECT 
        e.id, 
        e.subject, 
        e.description, 
        e.time_limit,
        e.date_created,
        COUNT(q.id) AS total_questions
    FROM 
        exams e
    LEFT JOIN
        questions q ON e.id = q.exam_id
    GROUP BY
        e.id
    ORDER BY
        e.date_created DESC
";
$query_fetch = $dbh->query($sql_fetch);
$exams = $query_fetch->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Exams | Teacher</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* Reusing the established Blue/Boxed UI styles from dashboard.php */
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
.sidebar ul li a { display: flex; align-items: center; padding: 12px 18px; color: #e2e8f0; text-decoration: none; border-radius: 8px; transition: 0.3s ease; font-size: 15px; border-left: 0px solid transparent; }
.sidebar ul li a i { margin-right: 15px; font-size: 17px; }
.sidebar ul li a:hover { background-color: var(--link-hover-bg); color: #fff; }
.sidebar ul li a.active { background-color: var(--primary-color); color: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.2); font-weight: 600; }
.main-content { margin-left: 270px; padding: 30px; }
.header { background: white; padding: 20px 30px; margin: -30px -30px 30px -30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
.header h3 { font-weight: 700; color: var(--blue-sidebar); margin: 0; font-size: 24px; }
.header p { color: #64748b; margin: 0; font-size: 14px; }
.table thead { background-color: var(--blue-sidebar); color: white; }

/* Custom styles for Manage Exam */
.action-buttons a { margin-right: 5px; }
.modal-header { background-color: var(--primary-color); color: white; border-bottom: none; }
.modal-header .btn-close { filter: invert(1); }
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
        <li><a href="manage_exam.php" class="active"><i class="fa fa-file-alt"></i>Manage Exam</a></li>
        <li><a href="manage_question.php"><i class="fa fa-question-circle"></i>Manage Question</a></li>
        <li><a href="manage_account.php"><i class="fa fa-users"></i>Manage Account</a></li>
        <li><a href="system_log.php"><i class="fa fa-clipboard-list"></i>System Log</a></li>
        <li><a href="manage_status.php"><i class="fa fa-cog"></i>Manage Status</a></li>
        <li><a href="advance_search.php"><i class="fa fa-search"></i>Advance Search</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h3>Manage Exams 📝</h3>
           
        </div>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>

    <?php echo $message; // Display success/error messages ?>

    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#examModal" onclick="clearModal()">
            <i class="fa fa-plus-circle"></i> Add New Exam
        </button>
    </div>

    <div class="card p-4 shadow-sm">
        <h5 class="fw-bold mb-4">Current Exams (Total: <?php echo count($exams); ?>)</h5>
        
        <?php if (count($exams) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 20%;">Subject/Exam</th>
                            <th style="width: 30%;">Description</th>
                            <th style="width: 10%;">Duration (Mins)</th>
                            <th style="width: 10%;">Questions</th>
                            <th style="width: 25%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($exam['id']); ?></td>
                                <td><?php echo htmlspecialchars($exam['subject']); ?></td>
                                <td><?php echo htmlspecialchars(substr($exam['description'], 0, 50)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars($exam['time_limit']); ?></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo htmlspecialchars($exam['total_questions']); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn btn-sm btn-info text-white" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#examModal"
                                            onclick="editExam(<?php echo htmlspecialchars(json_encode($exam)); ?>)">
                                        <i class="fa fa-edit"></i> Edit
                                    </button>
                                    <a href="manage_question.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-warning text-dark">
                                        <i class="fa fa-plus"></i> Q&A
                                    </a>
                                    <a href="manage_exam.php?action=delete&id=<?php echo $exam['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('WARNING: Are you sure you want to delete this exam? This cannot be undone if there are no questions attached.')">
                                        <i class="fa fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center">No exams have been created yet. Click 'Add New Exam' to begin.</div>
        <?php endif; ?>

    </div>
</div>

<div class="modal fade" id="examModal" tabindex="-1" aria-labelledby="examModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_exam.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="examModalLabel">Add New Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="exam_id" id="exam_id" value="0">
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject / Exam Name</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="time_limit" class="form-label">Time Limit (in Minutes)</label>
                        <input type="number" class="form-control" id="time_limit" name="time_limit" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Exam Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="submit_exam" class="btn btn-primary">Save Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Function to set modal for ADD operation
    function clearModal() {
        document.getElementById('examModalLabel').innerText = 'Add New Exam';
        document.getElementById('exam_id').value = '0';
        document.getElementById('subject').value = '';
        document.getElementById('time_limit').value = '';
        document.getElementById('description').value = '';
    }

    // Function to set modal for EDIT operation
    function editExam(exam) {
        document.getElementById('examModalLabel').innerText = 'Edit Exam: ' + exam.subject;
        document.getElementById('exam_id').value = exam.id;
        document.getElementById('subject').value = exam.subject;
        document.getElementById('time_limit').value = exam.time_limit;
        document.getElementById('description').value = exam.description;
    }
</script>
</body>
</html>