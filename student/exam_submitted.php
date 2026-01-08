<?php
session_start();
include('../includes/config.php');

// =====================
// ACCESS VALIDATION
// =====================
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$exam_id = $_POST['exam_id'] ?? 0;

// =====================
// CHECK IF ALREADY SUBMITTED
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
// FETCH CORRECT ANSWERS
// =====================
$sql_q = "SELECT id, correct_answer FROM questions WHERE exam_id = :exam_id";
$stmt_q = $dbh->prepare($sql_q);
$stmt_q->bindParam(':exam_id', $exam_id);
$stmt_q->execute();
$questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

$total_questions = count($questions);
$score = 0;

// =====================
// CALCULATE SCORE
// =====================
foreach ($questions as $q) {
    $qid = $q['id'];
    $correct = $q['correct_answer'];
    $user_answer = $_POST['answer_' . $qid] ?? '';

    if ($user_answer === $correct) {
        $score++;
    }
}

// =====================
// CALCULATE PERCENTAGE
// =====================
$percentage = $total_questions > 0 ? round(($score / $total_questions) * 100, 2) : 0;

// =====================
// INSERT RESULT
// =====================
$sql_insert = "INSERT INTO exam_results (user_id, exam_id, score, total_questions, percentage, date_taken) 
               VALUES (:user_id, :exam_id, :score, :total_questions, :percentage, NOW())";
$stmt_insert = $dbh->prepare($sql_insert);
$stmt_insert->bindParam(':user_id', $user_id);
$stmt_insert->bindParam(':exam_id', $exam_id);
$stmt_insert->bindParam(':score', $score);
$stmt_insert->bindParam(':total_questions', $total_questions);
$stmt_insert->bindParam(':percentage', $percentage);
$stmt_insert->execute();

// =====================
// CLEAR TIMER
// =====================
echo "<script>localStorage.removeItem('examEndTime_$exam_id');</script>";

// =====================
// CONFIRMATION
// =====================
echo "<script>
    alert('Exam submitted successfully!\\nScore: $score / $total_questions\\nPercentage: $percentage%');
    window.location.href='exam_dashboard.php';
</script>";
?>
