<?php
session_start();
// --- SECURITY CHECK (Modify according to your login system) ---
// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher') {
//     header("Location: ../login.php");
//     exit;
// }

// --- IMPORTANT: Assume this path leads to your PDO database connection setup ($dbh) ---
include(__DIR__ . '/../includes/config.php');

$page_title = "Manage Exam Schedule";
$error_message = $success_message = '';

// --- CRUD LOGIC START ---

// 1. ADD/EDIT SCHEDULE (Handling form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $exam_date = $_POST['exam_date'];
    $start_time = $_POST['start_time'];
    $duration = trim($_POST['duration']);
    $target_level = trim($_POST['target_level']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $schedule_id = $_POST['schedule_id'] ?? null;

    if (empty($subject) || empty($exam_date) || empty($start_time) || empty($duration) || empty($target_level)) {
        $error_message = "Please fill in all required fields.";
    } else {
        try {
            if ($schedule_id) {
                // UPDATE OPERATION
                $sql = "UPDATE exam_schedule SET subject=?, exam_date=?, start_time=?, duration=?, target_level=?, is_active=? WHERE id=?";
                $stmt = $dbh->prepare($sql);
                $stmt->execute([$subject, $exam_date, $start_time, $duration, $target_level, $is_active, $schedule_id]);
                $success_message = "Schedule for **" . htmlspecialchars($subject) . "** successfully updated!";
            } else {
                // INSERT OPERATION
                $sql = "INSERT INTO exam_schedule (subject, exam_date, start_time, duration, target_level, is_active) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $dbh->prepare($sql);
                $stmt->execute([$subject, $exam_date, $start_time, $duration, $target_level, $is_active]);
                $success_message = "New schedule for **" . htmlspecialchars($subject) . "** successfully created!";
            }
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not save schedule. " . htmlspecialchars($e->getMessage());
        }
    }
}

// 2. DELETE SCHEDULE
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = $_GET['delete_id'];
        $stmt = $dbh->prepare("DELETE FROM exam_schedule WHERE id = ?");
        $stmt->execute([$delete_id]);
        $success_message = "Schedule ID #{$delete_id} successfully deleted.";
        header("Location: manage_schedule.php?msg=" . urlencode($success_message));
        exit;
    } catch (PDOException $e) {
        $error_message = "Database Error: Could not delete schedule. " . htmlspecialchars($e->getMessage());
    }
}

// 3. FETCH ALL SCHEDULES (for the table)
try {
    $sql_schedule = "SELECT * FROM exam_schedule ORDER BY exam_date ASC, start_time ASC";
    $schedules = $dbh->query($sql_schedule)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $schedules = [];
    $error_message = "Could not load schedules: " . htmlspecialchars($e->getMessage());
}

// Handle success message from redirect (after delete)
if (isset($_GET['msg'])) {
    $success_message = htmlspecialchars($_GET['msg']);
}

// --- CRUD LOGIC END ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?> | OES Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* Reusing the established Blue/Boxed UI styles from manage_exam.php */
:root {
    --primary-color: #3b82f6; /* Light Blue */
    --secondary-color: #14b8a6; /* Teal Accent */
    --blue-sidebar: #2563eb; /* Deep Blue for Sidebar */
    --link-hover-bg: rgba(255, 255, 255, 0.15); 
}
body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; margin: 0; }
.sidebar { width: 270px; height: 100vh; background-color: var(--blue-sidebar); color: white; position: fixed; top: 0; left: 0; box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2); }
/* Adjusted sidebar width for consistency with manage_exam.php */
.main-content { margin-left: 270px; padding: 30px; } 

.sidebar .logo { text-align: center; padding: 30px 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
.sidebar .logo img { width: 90px; height: 90px; border-radius: 50%; margin-bottom: 10px; object-fit: cover; border: 3px solid rgba(255,255,255,0.2); }
.sidebar h4 { font-weight: 700; font-size: 19px; color: #fff; letter-spacing: 0.5px; }
.sidebar ul { list-style: none; padding: 20px 0; margin: 0; }
.sidebar ul li { margin: 5px 15px; }
.sidebar ul li a { display: flex; align-items: center; padding: 12px 18px; color: #e2e8f0; text-decoration: none; border-radius: 8px; transition: 0.3s ease; font-size: 15px; border-left: 0px solid transparent; }
.sidebar ul li a i { margin-right: 15px; font-size: 17px; }
.sidebar ul li a:hover { background-color: var(--link-hover-bg); color: #fff; }
.sidebar ul li.active a { background-color: var(--primary-color); color: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.2); font-weight: 600; }

.header { background: white; padding: 20px 30px; margin: -30px -30px 30px -30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
.header h3 { font-weight: 700; color: var(--blue-sidebar); margin: 0; font-size: 24px; }

/* Custom styles for Manage Exam Table/Cards */
.activity-card { 
    background: white; 
    border-radius: 10px; 
    box-shadow: 0 4px 8px rgba(0,0,0,0.05); 
    padding: 20px; /* Added padding to match card style */
}
.table thead { background-color: var(--blue-sidebar); color: white; }
.table th, .table td { vertical-align: middle; } /* Align text middle */

/* Modal styling from manage_exam.php */
.modal-header { background-color: var(--primary-color); color: white; border-bottom: none; }
.modal-header .btn-close { filter: invert(1); }
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <img src="../images/OIP (22).jpg" alt="School Logo" style="width: 60px; height: auto;">
        <h4>Online Examination</h4>
    </div>
    <ul>
        <li><a href="dashboard.php"><i class="fa fa-home"></i>Home</a></li>
        <li><a href="manage_examinee.php"><i class="fa fa-user-graduate"></i>Manage Examinee</a></li>
        <li><a href="manage_exam.php"><i class="fa fa-file-alt"></i>Manage Exam</a></li>
        <li><a href="manage_question.php"><i class="fa fa-question-circle"></i>Manage Question</a></li>
        <li class="active"><a href="manage_schedule.php"><i class="fa fa-calendar-alt"></i>Manage Schedule</a></li>
        <li><a href="manage_account.php"><i class="fa fa-users"></i>Manage Account</a></li>
        <li><a href="system_log.php"><i class="fa fa-clipboard-list"></i>System Log</a></li>
        <li><a href="manage_status.php"><i class="fa fa-cog"></i>Manage Status</a></li>
        <li><a href="advance_search.php"><i class="fa fa-search"></i>Advance Search</a></li>
        <li><a href="../logout.php"><i class="fa fa-sign-out-alt"></i>Logout</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header">
        <h3><?php echo $page_title; ?> 📅</h3>
        <a href="../logout.php" class="btn btn-danger"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <button class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#scheduleModal" data-schedule-id="0">
        <i class="fa fa-plus-circle"></i> Add New Schedule
    </button>

    <div class="activity-card p-4">
        <h5 class="fw-bold mb-3">All Exam Schedules</h5>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="bg-dark text-white">
                    <tr>
                        <th>#</th>
                        <th>Subject</th>
                        <th>Date & Time</th>
                        <th>Duration</th>
                        <th>Level</th>
                        <th>Active (Public)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($schedules) > 0): ?>
                        <?php foreach ($schedules as $index => $sch): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($sch['subject']); ?></td>
                                <td>
                                    <?php echo date("M d, Y", strtotime($sch['exam_date'])); ?><br>
                                    <span class="badge bg-secondary"><?php echo date("h:i A", strtotime($sch['start_time'])); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($sch['duration']); ?></td>
                                <td><?php echo htmlspecialchars($sch['target_level']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $sch['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $sch['is_active'] ? 'YES' : 'NO'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-schedule-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#scheduleModal"
                                        data-schedule-id="<?php echo $sch['id']; ?>"
                                        data-subject="<?php echo htmlspecialchars($sch['subject']); ?>"
                                        data-date="<?php echo htmlspecialchars($sch['exam_date']); ?>"
                                        data-time="<?php echo htmlspecialchars($sch['start_time']); ?>"
                                        data-duration="<?php echo htmlspecialchars($sch['duration']); ?>"
                                        data-level="<?php echo htmlspecialchars($sch['target_level']); ?>"
                                        data-active="<?php echo $sch['is_active']; ?>"
                                        >
                                        <i class="fa fa-edit"></i> Edit
                                    </button>
                                    <a href="?delete_id=<?php echo $sch['id']; ?>" onclick="return confirm('Are you sure you want to delete this schedule? This action cannot be undone.')" class="btn btn-sm btn-danger">
                                        <i class="fa fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                          <tr><td colspan="7" class="text-center p-4 text-muted">No exam schedules created yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="scheduleModalLabel">Add New Schedule</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_schedule.php">
          <div class="modal-body">
              <input type="hidden" name="schedule_id" id="schedule_id_input">
              
              <div class="mb-3">
                  <label for="subject" class="form-label">Subject Title *</label>
                  <input type="text" class="form-control" id="subject" name="subject" required>
              </div>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label for="exam_date" class="form-label">Exam Date *</label>
                      <input type="date" class="form-control" id="exam_date" name="exam_date" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label for="start_time" class="form-label">Start Time *</label>
                      <input type="time" class="form-control" id="start_time" name="start_time" required>
                  </div>
              </div>
              <div class="mb-3">
                  <label for="duration" class="form-label">Duration (e.g., 45 Minutes, 1 Hour) *</label>
                  <input type="text" class="form-control" id="duration" name="duration" placeholder="e.g., 45 Minutes" required>
              </div>
              <div class="mb-3">
                  <label for="target_level" class="form-label">Target Level/Group (e.g., Grade 10, All) *</label>
                  <input type="text" class="form-control" id="target_level" name="target_level" required>
              </div>
              <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" checked>
                  <label class="form-check-label" for="is_active">Make Publicly Active (Show on Landing Page)</label>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary" id="modalSaveButton">Save Schedule</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const scheduleModal = document.getElementById('scheduleModal');
    
    scheduleModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; 
        const scheduleId = button.getAttribute('data-schedule-id');
        
        const modalTitle = scheduleModal.querySelector('.modal-title');
        const saveButton = scheduleModal.querySelector('#modalSaveButton');
        const form = scheduleModal.querySelector('form');

        // Reset form for "Add New"
        form.reset();
        scheduleModal.querySelector('#schedule_id_input').value = '';
        modalTitle.textContent = 'Add New Schedule';
        saveButton.textContent = 'Create Schedule';
        saveButton.classList.remove('btn-warning');
        saveButton.classList.add('btn-primary');

        if (scheduleId != 0) {
            // Populate form for "Edit"
            modalTitle.textContent = 'Edit Schedule (ID: ' + scheduleId + ')';
            saveButton.textContent = 'Update Schedule';
            saveButton.classList.remove('btn-primary');
            saveButton.classList.add('btn-warning');

            scheduleModal.querySelector('#schedule_id_input').value = scheduleId;
            scheduleModal.querySelector('#subject').value = button.getAttribute('data-subject');
            scheduleModal.querySelector('#exam_date').value = button.getAttribute('data-date');
            scheduleModal.querySelector('#start_time').value = button.getAttribute('data-time');
            scheduleModal.querySelector('#duration').value = button.getAttribute('data-duration');
            scheduleModal.querySelector('#target_level').value = button.getAttribute('data-level');
            
            const isActiveCheckbox = scheduleModal.querySelector('#is_active');
            isActiveCheckbox.checked = (button.getAttribute('data-active') == 1);
        }
    });
});
</script>

</body>
</html>