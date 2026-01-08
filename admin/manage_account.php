<?php
session_start();
// Security check: Only Teachers can access this page
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher') {
    header("Location: ../manage_account.php");
    exit;
}
include(__DIR__ . '/../includes/config.php');
$teacher = $_SESSION['user']['fullname'] ?? 'Teacher';
$msg = ""; // Initialize message variable

// 1. ADD NEW USER
if (isset($_POST['add_user'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = md5($_POST['password']);
    $role = $_POST['role'];
    
    // Check if email already exists
    $sql_check = "SELECT id FROM users WHERE email = :email";
    $query_check = $dbh->prepare($sql_check);
    $query_check->bindParam(':email', $email);
    $query_check->execute();

    if ($query_check->rowCount() > 0) {
        $msg = "<div class='alert alert-warning'>Email address already exists!</div>";
    } else {
        // created_at will automatically be set by CURRENT_TIMESTAMP
        $sql_insert = "INSERT INTO users (fullname, email, password, role) VALUES (:fullname, :email, :password, :role)";
        $query_insert = $dbh->prepare($sql_insert);
        $query_insert->bindParam(':fullname', $fullname);
        $query_insert->bindParam(':email', $email);
        $query_insert->bindParam(':password', $password);
        $query_insert->bindParam(':role', $role);
        
        if ($query_insert->execute()) {
            $msg = "<div class='alert alert-success'>New user account created successfully!</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Failed to create account.</div>";
        }
    }
}

// 2. DELETE USER
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $sql_delete = "DELETE FROM users WHERE id = :id";
    $query_delete = $dbh->prepare($sql_delete);
    $query_delete->bindParam(':id', $id);
    
    if ($query_delete->execute()) {
        $msg = "<div class='alert alert-success'>Account deleted successfully!</div>";
        // Redirect to remove the GET parameter
        header("Location: manage_account.php");
        exit;
    } else {
        $msg = "<div class='alert alert-danger'>Failed to delete account.</div>";
    }
}

// 3. EDIT USER
if (isset($_POST['edit_user'])) {
    $id = $_POST['user_id'];
    $fullname = trim($_POST['edit_fullname']);
    $email = trim($_POST['edit_email']);
    $role = $_POST['edit_role'];
    $password = $_POST['edit_password']; // Optional change

    // Start building the query
    $sql_update = "UPDATE users SET fullname = :fullname, email = :email, role = :role";
    $params = [
        ':fullname' => $fullname,
        ':email' => $email,
        ':role' => $role,
        ':id' => $id
    ];

    // If password is provided, update it
    if (!empty($password)) {
        $sql_update .= ", password = :password";
        $params[':password'] = md5($password);
    }

    $sql_update .= " WHERE id = :id";
    $query_update = $dbh->prepare($sql_update);

    if ($query_update->execute($params)) {
        $msg = "<div class='alert alert-success'>Account updated successfully!</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Failed to update account.</div>";
    }
}

// 4. Fetch All Users (Using the correct column name 'created_at')
$sql_fetch = "SELECT id, fullname, email, role, created_at FROM users ORDER BY role, fullname";
$query_fetch = $dbh->prepare($sql_fetch);
$query_fetch->execute();
$users = $query_fetch->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Accounts | Teacher</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* Same styles as dashboard.php */
body { font-family: 'Poppins', sans-serif; background-color: #f3f4f8; margin: 0; }
.sidebar { width: 270px; height: 100vh; background: linear-gradient(180deg, #4f46e5, #2563eb); color: white; position: fixed; top: 0; left: 0; overflow-y: auto; box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1); border-top-right-radius: 20px; border-bottom-right-radius: 20px; }
.sidebar .logo { text-align: center; padding: 30px 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.15); }
.sidebar h4 { font-weight: 700; font-size: 18px; color: #f1f1f1; }
.sidebar ul { list-style: none; padding: 0; margin: 0; }
.sidebar ul li { margin: 5px 15px; }
.sidebar ul li a { display: flex; align-items: center; padding: 12px 18px; color: #f0f0f0; text-decoration: none; border-radius: 10px; transition: 0.3s ease; font-size: 15px; }
.sidebar ul li a i { margin-right: 10px; font-size: 17px; }
.sidebar ul li a:hover, .sidebar ul li a.active { background-color: rgba(255, 255, 255, 0.2); color: #fff; transform: translateX(5px); }
.main-content { margin-left: 270px; padding: 30px; }
.header { display: flex; justify-content: space-between; align-items: center; }
.header h3 { font-weight: 700; color: #1e293b; }
.header p { color: #64748b; margin: 0; }
.table thead { background-color: #4f46e5; color: white; }
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <img src="../images/OIP (22).jpg" alt="School Logo" style="width: 100px; border-radius: 50%;">
        <h4>Online Examination</h4>
    </div>
    <ul>
        <li><a href="dashboard.php"><i class="fa fa-home"></i>Home</a></li>
        <li><a href="manage_examinee.php"><i class="fa fa-user-graduate"></i>Manage Examinee</a></li>
        <li><a href="manage_exam.php"><i class="fa fa-file-alt"></i>Manage Exam</a></li>
        <li><a href="manage_question.php"><i class="fa fa-question-circle"></i>Manage Question</a></li>
        <li><a href="manage_account.php" class="active"><i class="fa fa-users"></i>Manage Account</a></li>
        <li><a href="system_log.php"><i class="fa fa-clipboard-list"></i>System Log</a></li>
        <li><a href="manage_status.php"><i class="fa fa-cog"></i>Manage Status</a></li>
        <li><a href="advance_search.php"><i class="fa fa-search"></i>Advance Search</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header mb-4">
        <div>
            <h3>Manage Accounts 👥</h3>
            <p>View and manage all user accounts (Teachers & Students).</p>
        </div>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>

    <?php echo $msg; ?>
    
    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa fa-plus-circle"></i> Add New Account
        </button>
    </div>

    <div class="card p-3 shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Date Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $cnt = 1; if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $cnt++; ?></td>
                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge bg-<?php echo ($user['role'] == 'Teacher') ? 'warning' : 'info'; ?>"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                <td><?php echo date("Y-m-d", strtotime($user['created_at'] ?? 'N/A')); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info text-white me-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editUserModal" 
                                        data-id="<?php echo $user['id']; ?>" 
                                        data-fullname="<?php echo htmlspecialchars($user['fullname']); ?>" 
                                        data-email="<?php echo htmlspecialchars($user['email']); ?>" 
                                        data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                        <i class="fa fa-edit"></i> Edit
                                    </button>
                                    
                                    <a href="manage_account.php?delete_id=<?php echo $user['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete this account?');"
                                       class="btn btn-sm btn-danger"><i class="fa fa-trash-alt"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No accounts found in the database.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addUserModalLabel">Create New User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="">Select Role</option>
                            <option value="Student">Student</option>
                            <option value="Teacher">Teacher</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="editUserModalLabel">Edit User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="edit_fullname" id="edit-fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="edit_email" id="edit-email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password (Leave blank to keep current)</label>
                        <input type="password" name="edit_password" class="form-control" placeholder="New Password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="edit_role" id="edit-role" class="form-select" required>
                            <option value="Student">Student</option>
                            <option value="Teacher">Teacher</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="edit_user" class="btn btn-info text-white">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript to populate the Edit User Modal
document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editUserModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;
        
        // Extract info from data-bs-* attributes
        const id = button.getAttribute('data-id');
        const fullname = button.getAttribute('data-fullname');
        const email = button.getAttribute('data-email');
        const role = button.getAttribute('data-role');

        // Update the modal's fields
        document.getElementById('edit-user-id').value = id;
        document.getElementById('edit-fullname').value = fullname;
        document.getElementById('edit-email').value = email;
        document.getElementById('edit-role').value = role;
    });
});
</script>
</body>
</html>