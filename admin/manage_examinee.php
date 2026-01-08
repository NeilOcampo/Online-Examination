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
$edit_examinee_data = null;

// --- Helper for Password Hashing ---
// Assuming all passwords in your database are stored using MD5, 
// we stick to MD5 for consistency. (e.g., '123456' is e10adc3949ba59abbe56e057f20f883e)
function hash_password($password) {
    // In a real application, use password_hash() (Bcrypt) instead of md5.
    return md5($password);
}


// --- CRUD Logic (POST Handling) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $examinee_id = $_POST['examinee_id'] ?? null;

    try {
        if ($action === 'add') {
            if (empty($fullname) || empty($email) || empty($password)) {
                throw new Exception("Please fill in the full name, email, and provide a default password.");
            }

            // Check if email already exists
            $sql_check = "SELECT id FROM users WHERE email = :email";
            $stmt_check = $dbh->prepare($sql_check);
            $stmt_check->execute([':email' => $email]);
            if ($stmt_check->rowCount() > 0) {
                throw new Exception("Email already registered. Use a different email address.");
            }

            $hashed_password = hash_password($password);
            $role = 'Student';

            $sql_insert = "INSERT INTO users (fullname, email, password, role) VALUES (:fullname, :email, :password, :role)";
            $stmt = $dbh->prepare($sql_insert);
            $stmt->execute([
                ':fullname' => $fullname,
                ':email' => $email,
                ':password' => $hashed_password,
                ':role' => $role
            ]);

            log_action($dbh, $current_user_id, $current_user_role, "Created New Student Account: {$fullname} (ID: " . $dbh->lastInsertId() . ")");
            $message = '<div class="alert alert-success">Examinee **' . htmlspecialchars($fullname) . '** successfully added!</div>';

        } elseif ($action === 'update' && $examinee_id) {
            if (empty($fullname) || empty($email)) {
                throw new Exception("Full Name and Email cannot be empty.");
            }

            $sql_update = "UPDATE users SET fullname = :fullname, email = :email";
            $params = [':fullname' => $fullname, ':email' => $email, ':id' => $examinee_id];
            $log_message = "Updated Student Account ID #{$examinee_id}: Name changed to '{$fullname}'.";

            // Handle optional password update
            if (!empty($password)) {
                $hashed_password = hash_password($password);
                $sql_update .= ", password = :password";
                $params[':password'] = $hashed_password;
                $log_message .= " Password was also reset.";
            }

            $sql_update .= " WHERE id = :id AND role = 'Student'";
            $stmt = $dbh->prepare($sql_update);
            $stmt->execute($params);

            log_action($dbh, $current_user_id, $current_user_role, $log_message);
            $message = '<div class="alert alert-success">Examinee details updated!</div>';

        } elseif ($action === 'delete' && $examinee_id) {
            // Fetch name before deleting for log
            $sql_fetch = "SELECT fullname FROM users WHERE id = :id AND role = 'Student'";
            $stmt_fetch = $dbh->prepare($sql_fetch);
            $stmt_fetch->execute([':id' => $examinee_id]);
            $old_name = $stmt_fetch->fetchColumn();

            $sql_delete = "DELETE FROM users WHERE id = :id AND role = 'Student'";
            $stmt = $dbh->prepare($sql_delete);
            $stmt->execute([':id' => $examinee_id]);

            log_action($dbh, $current_user_id, $current_user_role, "Deleted Student Account: {$old_name} (ID: #{$examinee_id})");
            $message = '<div class="alert alert-success">Examinee **' . htmlspecialchars($old_name) . '** successfully deleted!</div>';
        }
        
        // Redirect to clear POST data and prevent resubmission
        header("Location: manage_examinee.php?msg=" . urlencode(strip_tags($message)));
        exit;

    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// --- Fetch Examinee for Editing ---
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $sql_edit = "SELECT id, fullname, email FROM users WHERE id = :id AND role = 'Student'";
    $stmt_edit = $dbh->prepare($sql_edit);
    $stmt_edit->execute([':id' => $edit_id]);
    $edit_examinee_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);

    if (!$edit_examinee_data) {
        $message = '<div class="alert alert-danger">Examinee not found or not a Student.</div>';
    }
}

// --- Display Messages from Redirects ---
if (isset($_GET['msg'])) {
    $message = '<div class="alert alert-success">' . htmlspecialchars($_GET['msg']) . '</div>';
}

// --- Fetch All Examinees (Read) ---
$sql_examinees = "SELECT id, fullname, email, created_at FROM users WHERE role = 'Student' ORDER BY id DESC";
$query_examinees = $dbh->query($sql_examinees);
$examinees = $query_examinees->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Examinee | Teacher</title>
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
        <li><a href="manage_examinee.php" class="active"><i class="fa fa-user-graduate"></i>Manage Examinee</a></li>
        <li><a href="manage_exam.php"><i class="fa fa-file-alt"></i>Manage Exam</a></li>
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
            <h3>Manage Examinee 👨‍🎓</h3>
            <p>Add, edit, or remove student accounts.</p>
        </div>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>

    <?php echo $message; ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card p-4 shadow-sm">
                <h5 class="fw-bold mb-4 text-primary">
                    <?php echo $edit_examinee_data ? 'Edit Examinee ID: ' . htmlspecialchars($edit_examinee_data['id']) : 'Add New Examinee'; ?>
                </h5>
                
                <form method="POST" action="manage_examinee.php">
                    <input type="hidden" name="action" value="<?php echo $edit_examinee_data ? 'update' : 'add'; ?>">
                    <?php if ($edit_examinee_data): ?>
                        <input type="hidden" name="examinee_id" value="<?php echo htmlspecialchars($edit_examinee_data['id']); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="fullname" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($edit_examinee_data['fullname'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($edit_examinee_data['email'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <?php echo $edit_examinee_data ? 'Reset Password (Leave blank to keep old password)' : 'Default Password'; ?>
                        </label>
                        <input type="password" class="form-control" id="password" name="password" <?php echo $edit_examinee_data ? '' : 'required'; ?>>
                        <?php if ($edit_examinee_data): ?>
                            <div class="form-text">Enter new password if you wish to reset it.</div>
                        <?php else: ?>
                            <div class="form-text">This will be the student's initial password.</div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-<?php echo $edit_examinee_data ? 'warning' : 'primary'; ?> fw-bold">
                            <i class="fa fa-<?php echo $edit_examinee_data ? 'save' : 'plus'; ?>"></i> 
                            <?php echo $edit_examinee_data ? 'Save Changes' : 'Add Examinee'; ?>
                        </button>
                        <?php if ($edit_examinee_data): ?>
                            <a href="manage_examinee.php" class="btn btn-secondary mt-2">Cancel Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-4 shadow-sm">
                <h5 class="fw-bold mb-4">Registered Examinees (<?php echo count($examinees); ?>)</h5>
                
                <?php if (!empty($examinees)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">ID</th>
                                    <th style="width: 30%;">Name</th>
                                    <th style="width: 30%;">Email</th>
                                    <th style="width: 15%;">Registered</th>
                                    <th style="width: 20%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($examinees as $examinee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($examinee['id']); ?></td>
                                        <td><?php echo htmlspecialchars($examinee['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($examinee['email']); ?></td>
                                        <td><?php echo date("Y-m-d", strtotime($examinee['created_at'])); ?></td>
                                        <td>
                                            <a href="manage_examinee.php?edit_id=<?php echo htmlspecialchars($examinee['id']); ?>" class="btn btn-sm btn-warning me-1" title="Edit">
                                                <i class="fa fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" action="manage_examinee.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this examinee? All their exam results will also be lost.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="examinee_id" value="<?php echo htmlspecialchars($examinee['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center mt-3">No student examinee accounts found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>