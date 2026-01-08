<?php
session_start();
// Security check: Only Teachers can access this page
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher') {
    header("Location: ../manage_question.php");
    exit;
}
include(__DIR__ . '/../includes/config.php');
$teacher = $_SESSION['user']['fullname'] ?? 'Teacher';

// Get message from session and clear it immediately (PRG pattern for alerts)
$msg = $_SESSION['msg'] ?? "";
unset($_SESSION['msg']); 

// --- 1. Fetch All Exams for Filter & Add Modal ---
$sql_exams = "SELECT id, subject FROM exams ORDER BY subject ASC";
$query_exams = $dbh->prepare($sql_exams);
$query_exams->execute();
$exams_list = $query_exams->fetchAll(PDO::FETCH_ASSOC);

// --- CRUD Operations Logic ---

// ADD NEW QUESTION
if (isset($_POST['add_question'])) {
    $exam_id = $_POST['exam_id'];
    $question = trim($_POST['question']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $correct_answer = $_POST['correct_answer'];

    $sql_insert = "INSERT INTO questions (exam_id, question, option_a, option_b, option_c, option_d, correct_answer) 
                   VALUES (:exam_id, :question, :option_a, :option_b, :option_c, :option_d, :correct_answer)";
    $query_insert = $dbh->prepare($sql_insert);
    
    $query_insert->bindParam(':exam_id', $exam_id);
    $query_insert->bindParam(':question', $question);
    $query_insert->bindParam(':option_a', $option_a);
    $query_insert->bindParam(':option_b', $option_b);
    $query_insert->bindParam(':option_c', $option_c);
    $query_insert->bindParam(':option_d', $option_d);
    $query_insert->bindParam(':correct_answer', $correct_answer);

    if ($query_insert->execute()) {
        $_SESSION['msg'] = "<div class='alert alert-success'>New question added successfully!</div>";
    } else {
        $_SESSION['msg'] = "<div class='alert alert-danger'>Failed to add question.</div>";
    }
    // PRG Redirect
    header("Location: manage_question.php?exam_id={$exam_id}");
    exit;
}

// DELETE QUESTION
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $exam_id_to_redirect = $_GET['exam_id'] ?? $exams_list[0]['id'] ?? '';

    $sql_delete = "DELETE FROM questions WHERE id = :id";
    $query_delete = $dbh->prepare($sql_delete);
    $query_delete->bindParam(':id', $id);
    
    if ($query_delete->execute()) {
        $_SESSION['msg'] = "<div class='alert alert-success'>Question deleted successfully!</div>";
    } else {
        $_SESSION['msg'] = "<div class='alert alert-danger'>Failed to delete question.</div>";
    }
    // PRG Redirect
    header("Location: manage_question.php?exam_id={$exam_id_to_redirect}");
    exit;
}

// EDIT QUESTION
if (isset($_POST['edit_question'])) {
    $id = $_POST['question_id'];
    $exam_id_to_redirect = $_POST['exam_id_hidden'] ?? $exams_list[0]['id'] ?? '';
    
    $question = trim($_POST['edit_question_text']);
    $option_a = trim($_POST['edit_option_a']);
    $option_b = trim($_POST['edit_option_b']);
    $option_c = trim($_POST['edit_option_c']);
    $option_d = trim($_POST['edit_option_d']);
    $correct_answer = $_POST['edit_correct_answer'];

    // NOTE: Ang query na ito ay HINDI gumagamit ng date_created. Ito ay tama.
    $sql_update = "UPDATE questions SET question = :question, option_a = :option_a, option_b = :option_b, 
                   option_c = :option_c, option_d = :option_d, correct_answer = :correct_answer 
                   WHERE id = :id";
    $query_update = $dbh->prepare($sql_update);
    
    $query_update->bindParam(':question', $question);
    $query_update->bindParam(':option_a', $option_a);
    $query_update->bindParam(':option_b', $option_b);
    $query_update->bindParam(':option_c', $option_c);
    $query_update->bindParam(':option_d', $option_d);
    $query_update->bindParam(':correct_answer', $correct_answer);
    $query_update->bindParam(':id', $id);

    if ($query_update->execute()) {
        $_SESSION['msg'] = "<div class='alert alert-success'>Question updated successfully!</div>";
    } else {
        $_SESSION['msg'] = "<div class='alert alert-danger'>Failed to update question.</div>";
    }
    // PRG Redirect
    header("Location: manage_question.php?exam_id={$exam_id_to_redirect}");
    exit;
}

// --- 2. Fetch Questions (Filtered by Exam) ---
$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : (empty($exams_list) ? 0 : $exams_list[0]['id']);

$questions = [];
$subject_name = "No Exam Selected";

if ($selected_exam_id > 0) {
    // NOTE: Ang query na ito ay HINDI gumagamit ng date_created. Ito ay tama.
    $sql_questions = "SELECT q.*, e.subject, e.id AS exam_id_q FROM questions q JOIN exams e ON q.exam_id = e.id WHERE q.exam_id = :exam_id ORDER BY q.id ASC";
    $query_questions = $dbh->prepare($sql_questions);
    $query_questions->bindParam(':exam_id', $selected_exam_id);
    $query_questions->execute();
    $questions = $query_questions->fetchAll(PDO::FETCH_ASSOC);

    // Get the subject name for display
    $sql_subject = "SELECT subject FROM exams WHERE id = :exam_id";
    $query_subject = $dbh->prepare($sql_subject);
    $query_subject->bindParam(':exam_id', $selected_exam_id);
    $query_subject->execute();
    $subject_name = $query_subject->fetchColumn() . " Questions";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Questions | Teacher</title>
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
        <li><a href="manage_question.php" class="active"><i class="fa fa-question-circle"></i>Manage Question</a></li>
        <li><a href="manage_account.php"><i class="fa fa-users"></i>Manage Account</a></li>
        <li><a href="system_log.php"><i class="fa fa-clipboard-list"></i>System Log</a></li>
        <li><a href="manage_status.php"><i class="fa fa-cog"></i>Manage Status</a></li>
        <li><a href="advance_search.php"><i class="fa fa-search"></i>Advance Search</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header mb-4">
        <div>
            <h3>Manage Questions ❓</h3>
            <p>Question Bank for <?php echo htmlspecialchars($subject_name); ?></p>
        </div>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>

    <?php echo $msg; ?>
    
    <div class="card p-3 shadow-sm mb-4">
        <div class="d-flex justify-content-between align-items-center">
            
            <form method="GET" class="d-flex align-items-center">
                <label for="exam_id" class="form-label me-2 mb-0 fw-bold">Filter By Exam:</label>
                <select name="exam_id" id="exam_id" class="form-select me-3" style="width: 250px;" onchange="this.form.submit()">
                    <option value="">-- Select Exam --</option>
                    <?php foreach ($exams_list as $exam): ?>
                        <option value="<?php echo $exam['id']; ?>" <?php echo ($selected_exam_id == $exam['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($exam['subject']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selected_exam_id): ?>
                    <a href="manage_question.php" class="btn btn-outline-secondary btn-sm">Clear Filter</a>
                <?php endif; ?>
            </form>
            
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal" <?php echo empty($exams_list) ? 'disabled' : ''; ?>>
                <i class="fa fa-plus-circle"></i> Add New Question
            </button>
        </div>
    </div>


    <div class="card p-3 shadow-sm">
        <h5 class="fw-bold mb-3"><?php echo htmlspecialchars($subject_name); ?></h5>
        <?php if ($selected_exam_id == 0 && empty($exams_list)): ?>
             <div class="alert alert-warning text-center">Please create an exam first in "Manage Exam" to add questions.</div>
        <?php elseif ($selected_exam_id > 0 && count($questions) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 40%;">Question</th>
                            <th style="width: 35%;">Options</th>
                            <th style="width: 10%;">Answer</th>
                            <th style="width: 10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $cnt = 1; foreach ($questions as $q): ?>
                            <tr>
                                <td><?php echo $cnt++; ?></td>
                                <td><?php echo htmlspecialchars($q['question']); ?></td>
                                <td>
                                    <ul class="list-unstyled mb-0 small">
                                        <li>A: <?php echo htmlspecialchars($q['option_a']); ?></li>
                                        <li>B: <?php echo htmlspecialchars($q['option_b']); ?></li>
                                        <li>C: <?php echo htmlspecialchars($q['option_c']); ?></li>
                                        <li>D: <?php echo htmlspecialchars($q['option_d']); ?></li>
                                    </ul>
                                </td>
                                <td><span class="badge bg-success"><?php echo htmlspecialchars($q['correct_answer']); ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-info text-white mb-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editQuestionModal" 
                                        data-id="<?php echo $q['id']; ?>" 
                                        data-exam-id="<?php echo $q['exam_id']; ?>"
                                        data-question="<?php echo htmlspecialchars($q['question']); ?>" 
                                        data-a="<?php echo htmlspecialchars($q['option_a']); ?>" 
                                        data-b="<?php echo htmlspecialchars($q['option_b']); ?>" 
                                        data-c="<?php echo htmlspecialchars($q['option_c']); ?>" 
                                        data-d="<?php echo htmlspecialchars($q['option_d']); ?>" 
                                        data-answer="<?php echo htmlspecialchars($q['correct_answer']); ?>">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    
                                    <a href="manage_question.php?delete_id=<?php echo $q['id']; ?>&exam_id=<?php echo $selected_exam_id; ?>" 
                                       onclick="return confirm('Are you sure you want to delete this question?');"
                                       class="btn btn-sm btn-danger"><i class="fa fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
             <div class="alert alert-info text-center">No questions found for this exam.</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addQuestionModal" tabindex="-1" aria-labelledby="addQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addQuestionModalLabel">Add New Question</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Exam</label>
                        <select name="exam_id" class="form-select" required>
                            <option value="">-- Select Exam --</option>
                            <?php foreach ($exams_list as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo ($selected_exam_id == $exam['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['subject']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Question Text</label>
                        <textarea name="question" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option A</label>
                            <input type="text" name="option_a" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option B</label>
                            <input type="text" name="option_b" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option C</label>
                            <input type="text" name="option_c" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option D</label>
                            <input type="text" name="option_d" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correct Answer</label>
                        <select name="correct_answer" class="form-select" required>
                            <option value="">Select Correct Option</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_question" class="btn btn-primary">Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="editQuestionModalLabel">Edit Question</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="question_id" id="edit-question-id">
                    <input type="hidden" name="exam_id_hidden" id="edit-exam-id-hidden">
                    <div class="mb-3">
                        <label class="form-label">Question Text</label>
                        <textarea name="edit_question_text" id="edit-question-text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option A</label>
                            <input type="text" name="edit_option_a" id="edit-option-a" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option B</label>
                            <input type="text" name="edit_option_b" id="edit-option-b" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option C</label>
                            <input type="text" name="edit_option_c" id="edit-option-c" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Option D</label>
                            <input type="text" name="edit_option_d" id="edit-option-d" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correct Answer</label>
                        <select name="edit_correct_answer" id="edit-correct-answer" class="form-select" required>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="edit_question" class="btn btn-info text-white">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript to populate the Edit Question Modal
document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editQuestionModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;
        
        // Extract info from data-bs-* attributes
        const id = button.getAttribute('data-id');
        const exam_id = button.getAttribute('data-exam-id'); // Added this
        const question = button.getAttribute('data-question');
        const option_a = button.getAttribute('data-a');
        const option_b = button.getAttribute('data-b');
        const option_c = button.getAttribute('data-c');
        const option_d = button.getAttribute('data-d');
        const correct_answer = button.getAttribute('data-answer');

        // Update the modal's fields
        document.getElementById('edit-question-id').value = id;
        document.getElementById('edit-exam-id-hidden').value = exam_id; // Added this
        document.getElementById('edit-question-text').value = question;
        document.getElementById('edit-option-a').value = option_a;
        document.getElementById('edit-option-b').value = option_b;
        document.getElementById('edit-option-c').value = option_c;
        document.getElementById('edit-option-d').value = option_d;
        document.getElementById('edit-correct-answer').value = correct_answer;
    });
});
</script>
</body>
</html>