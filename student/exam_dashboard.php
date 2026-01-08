<?php 
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student') {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../includes/config.php');

$student = $_SESSION['user']['fullname'];

// Fetch exams
$sql = "SELECT * FROM exams ORDER BY subject ASC";
$query = $dbh->prepare($sql);
$query->execute();
$exams = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard | Exams</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #f4f7fc;
    min-height: 100vh;
    position: relative;
}

/* BACKGROUND SHAPES */
body::before {
    content: "";
    position: fixed;
    top: -80px;
    right: -80px;
    width: 300px;
    height: 300px;
    background: rgba(13,110,253,0.08);
    border-radius: 50%;
    z-index: -1;
}
body::after {
    content: "";
    position: fixed;
    bottom: -100px;
    left: -100px;
    width: 350px;
    height: 350px;
    background: rgba(111,66,193,0.08);
    border-radius: 50%;
    z-index: -1;
}

/* NAVBAR */
.navbar {
    background: rgba(255, 255, 255, 0.8) !important;
    backdrop-filter: blur(12px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

/* TITLE STYLING */
.page-title {
    font-weight: 800;
    font-size: 32px;
    background: linear-gradient(90deg, #0d6efd, #6610f2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* SECTION LABEL */
.section-label {
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 1px;
    color: #6c757d;
    text-align: center;
    margin-bottom: 10px;
    font-size: 13px;
}

/* EXAM CARD */
.exam-card {
    border: none;
    border-radius: 18px;
    padding: 25px;
    background: #ffffff;
    box-shadow: 0px 4px 20px rgba(0,0,0,0.08);
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}
.exam-card:hover {
    transform: translateY(-6px);
    box-shadow: 0px 8px 24px rgba(0,0,0,0.12);
}

/* BUBBLE ON CARD */
.exam-card::before {
    content: "";
    position: absolute;
    top: -40px;
    right: -40px;
    width: 100px;
    height: 100px;
    background: rgba(13,110,253,0.08);
    border-radius: 50%;
}

/* ICON */
.exam-icon {
    font-size: 34px;
    margin-bottom: 10px;
    color: #0d6efd;
}

/* SUBJECT TITLE */
.exam-title {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
}

/* DESCRIPTION */
.exam-desc {
    font-size: 14px;
    color: #555;
}

/* TIME LABEL */
.exam-time {
    font-weight: 600;
    color: #444;
}

/* BUTTON */
.btn-start {
    background: #0d6efd;
    border-radius: 10px;
    color: #fff;
    font-weight: 600;
    padding: 10px 0;
    transition: 0.3s ease;
}
.btn-start:hover {
    background: #0b5ed7;
    color: #fff;
    transform: scale(1.02);
}
</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-light px-4 py-3">
    <a class="navbar-brand fw-bold fs-4 text-primary">📘 Online Examination</a>

    <div class="ms-auto d-flex align-items-center">
        <span class="me-3 fw-semibold text-dark">Welcome, 
            <?php echo htmlspecialchars($student); ?>
        </span>

        <a href="../logout.php" class="btn btn-outline-primary btn-sm fw-semibold">
            Logout
        </a>
    </div>
</nav>

<!-- PAGE CONTENT -->
<div class="container py-5">

<!-- INSTRUCTIONS CARD -->
<div class="alert bg-white shadow-sm p-4 mb-5 rounded-4 w-100" 
     style="border-left: 6px solid #0d6efd; max-width: 1200px; margin: auto;">

    <h4 class="fw-bold mb-3 text-primary">📘 Examination Instructions</h4>
    <ul class="mb-0" style="line-height: 1.8;">
        <li>Read each question carefully before selecting your answer.</li>
        <li>You cannot go back to previous questions once submitted (if enabled by your instructor).</li>
        <li>Do not refresh or close the browser while taking the exam.</li>
        <li>The timer will start immediately after you click <strong>Start Exam</strong>.</li>
        <li>Once time is up, your answers will be submitted automatically.</li>
        <li>Make sure you have a stable internet connection during the examination.</li>
    </ul>
</div>

    <!-- SECTION LABEL & TITLE -->
    <div class="section-label"></div>
    <h2 class="text-center mb-5 page-title">Take Your Exam</h2>

    <div class="row g-4">
        <?php if (count($exams) > 0): ?>
            <?php foreach ($exams as $exam): ?>
                <div class="col-md-4">
                    <div class="exam-card">

                        <!-- ICON -->
                        <div class="exam-icon">📚</div>

                        <div class="exam-title">
                            <?php echo htmlspecialchars($exam['subject']); ?>
                        </div>

                        <p class="exam-desc mt-2">
                            <?php echo htmlspecialchars($exam['description']); ?>
                        </p>

                        <p class="exam-time">
                            ⏳ Time Limit: 
                            <span class="text-primary">
                                <?php echo $exam['time_limit']; ?> mins
                            </span>
                        </p>

                        <a href="start_exam.php?id=<?php echo $exam['id']; ?>" 
                           class="btn btn-start w-100 mt-3">
                           Start Exam
                        </a>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center mt-4 text-muted">No exams available at the moment.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
