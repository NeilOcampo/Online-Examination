<?php
session_start();
include('../includes/config.php');

// =====================
//  ACCESS VALIDATION
// =====================
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: exam_dashboard.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$exam_id = $_GET['id'];

// =====================
//  CHECK IF ALREADY SUBMITTED
// =====================
$sql_check = "SELECT * FROM exam_results WHERE user_id = :user_id AND exam_id = :exam_id";
$stmt_check = $dbh->prepare($sql_check);
$stmt_check->bindParam(':user_id', $user_id);
$stmt_check->bindParam(':exam_id', $exam_id);
$stmt_check->execute();

if ($stmt_check->rowCount() > 0) {
    die("<div class='alert alert-info text-center mt-5'>You have already submitted this exam.</div>");
}

// =====================
//  GET EXAM INFO
// =====================
$sql = "SELECT * FROM exams WHERE id = :id LIMIT 1";
$query = $dbh->prepare($sql);
$query->bindParam(':id', $exam_id);
$query->execute();
$exam = $query->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    echo "<div class='alert alert-danger text-center mt-5'>Exam not found.</div>";
    exit;
}

// =====================
//  GET QUESTIONS
// =====================
$sql2 = "SELECT * FROM questions WHERE exam_id = :exam_id ORDER BY id ASC";
$stmt = $dbh->prepare($sql2);
$stmt->bindParam(':exam_id', $exam_id);
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($exam['subject']); ?> | Take Exam</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #007bff, #6610f2);
    color: #fff;
    min-height: 100vh;
}
.card {
    background: #fff;
    color: #000;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.btn-submit {
    background-color: #ffc107;
    color: #000;
    border-radius: 10px;
}
.btn-submit:hover {
    background-color: #ffcd39;
}
.timer-box {
    position: fixed;
    top: 15px;
    right: 15px;
    background: #fff;
    color: #000;
    padding: 10px 15px;
    border-radius: 10px;
    font-weight: 600;
    box-shadow: 0 3px 10px rgba(0,0,0,0.3);
}
</style>

<script>
// ========================
//       TIMER LOGIC
// ========================
let timeLeft = <?php echo (int)$exam['time_limit']; ?> * 60;

// Persist timer across refreshes
if(localStorage.getItem('examEndTime_<?php echo $exam_id; ?>')){
    timeLeft = Math.floor((localStorage.getItem('examEndTime_<?php echo $exam_id; ?>') - new Date().getTime()) / 1000);
} else {
    let endTime = new Date().getTime() + timeLeft*1000;
    localStorage.setItem('examEndTime_<?php echo $exam_id; ?>', endTime);
}

function startTimer() {
    const timerDisplay = document.getElementById('timer');

    const interval = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;

        timerDisplay.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;

        if (timeLeft <= 0) {
            clearInterval(interval);
            localStorage.removeItem('examEndTime_<?php echo $exam_id; ?>');
            alert('Time is up! Your exam will be submitted automatically.');
            document.getElementById('examForm').submit();
        }

        timeLeft--;
    }, 1000);
}

window.onload = startTimer;
</script>

</head>
<body>

<!-- TIMER -->
<div class="timer-box">
⏱ Time Left: <span id="timer"></span>
</div>

<div class="container mt-5">
    <div class="card p-4">
        <h2 class="fw-bold text-center mb-4">
            <?php echo htmlspecialchars($exam['subject']); ?> Exam
        </h2>

        <form method="POST" action="exam_submitted.php" id="examForm">
            <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">

            <?php if (count($questions) > 0): ?>
                <?php $i = 1; ?>
                <?php foreach ($questions as $q): ?>
                    <div class="mb-4">
                        <h5><b><?php echo $i++ . ". "; ?></b><?php echo htmlspecialchars($q['question']); ?></h5>

                        <!-- OPTIONS -->
                        <div class="ms-3 mt-2">

                            <label class="d-block">
                                <input type="radio" name="answer_<?php echo $q['id']; ?>" value="A" required>
                                <?php echo htmlspecialchars($q['option_a']); ?>
                            </label>

                            <label class="d-block">
                                <input type="radio" name="answer_<?php echo $q['id']; ?>" value="B">
                                <?php echo htmlspecialchars($q['option_b']); ?>
                            </label>

                            <label class="d-block">
                                <input type="radio" name="answer_<?php echo $q['id']; ?>" value="C">
                                <?php echo htmlspecialchars($q['option_c']); ?>
                            </label>

                            <label class="d-block">
                                <input type="radio" name="answer_<?php echo $q['id']; ?>" value="D">
                                <?php echo htmlspecialchars($q['option_d']); ?>
                            </label>

                        </div>
                    </div>
                    <hr>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted">No questions found for this exam.</p>
            <?php endif; ?>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-submit px-5">Submit Exam</button>
                <a href="exam_dashboard.php" class="btn btn-outline-secondary px-5 ms-2">Cancel</a>
            </div>
        </form>

    </div>
</div>

</body>
</html>
