<?php
session_start();
include('../includes/config.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: exam_dashboard.php");
    exit;
}

$exam_id = $_GET['id'];
$sql = "SELECT * FROM exams WHERE id = :id";
$query = $dbh->prepare($sql);
$query->bindParam(':id', $exam_id);
$query->execute();
$exam = $query->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    echo "<div class='alert alert-danger text-center mt-5'>Exam not found.</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($exam['subject']); ?> | Exam Info</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #007bff, #6610f2);
  color: #fff;
  min-height: 100vh;
}
.card {
  border: none;
  border-radius: 15px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  background-color: #fff;
  color: #000;
}
.btn-start {
  background-color: #ffc107;
  color: #000;
  border-radius: 10px;
  transition: background 0.3s;
}
.btn-start:hover {
  background-color: #ffca2c;
}
</style>
</head>
<body>

<div class="container d-flex justify-content-center align-items-center vh-100">
  <div class="col-md-6">
    <div class="card p-4 text-center">
      <h2 class="fw-bold mb-3"><?php echo htmlspecialchars($exam['subject']); ?></h2>
      <p class="text-muted mb-2"><?php echo htmlspecialchars($exam['description']); ?></p>
      <p><strong>Time Limit:</strong> <?php echo htmlspecialchars($exam['time_limit']); ?> minutes</p>
      <hr>
      <h5 class="mt-3 mb-4 text-primary">Are you ready to start the exam?</h5>
      <div class="d-flex justify-content-center gap-3">
        <a href="take_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-start px-4">Start Now</a>
        <a href="exam_dashboard.php" class="btn btn-outline-secondary px-4">Back to Dashboard</a>
      </div>
    </div>
  </div>
</div>

</body>
</html>
