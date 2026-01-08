<?php
session_start();
// Security check: Only Teachers can access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher') {
    header("Location: ../login.php");
    exit;
}

include(__DIR__ . '/../includes/config.php');

$results = [];
$all_exams = [];
$search_performed = false;
$error_message = '';

// --- Fetch All Exams for Search Filter ---
try {
    $sql_exams = "SELECT id, subject FROM exams ORDER BY subject ASC";
    $query_exams = $dbh->query($sql_exams);
    $all_exams = $query_exams->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = '<div class="alert alert-danger">Error fetching exams: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// --- Search Logic (GET Handling) ---
if (!empty($_GET['action']) && $_GET['action'] === 'search') {
    $search_performed = true;
    
    $exam_id = $_GET['exam_id'] ?? '';
    $examinee_keyword = trim($_GET['examinee_keyword'] ?? '');
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    $sql_conditions = [];
    $params = [];

    // 1. Filter by Exam Subject
    if (!empty($exam_id)) {
        $sql_conditions[] = "er.exam_id = :exam_id";
        $params[':exam_id'] = $exam_id;
    }

    // 2. Filter by Examinee Name or Email
    if (!empty($examinee_keyword)) {
        $sql_conditions[] = "(u.fullname LIKE :keyword OR u.email LIKE :keyword)";
        $params[':keyword'] = '%' . $examinee_keyword . '%';
    }
    
    // 3. Filter by Date Range (Date Taken)
    if (!empty($date_from)) {
        $sql_conditions[] = "DATE(er.date_taken) >= :date_from";
        $params[':date_from'] = $date_from;
    }

    if (!empty($date_to)) {
        $sql_conditions[] = "DATE(er.date_taken) <= :date_to";
        $params[':date_to'] = $date_to;
    }

    // Combine all conditions
    $where_clause = '';
    if (!empty($sql_conditions)) {
        $where_clause = "WHERE " . implode(' AND ', $sql_conditions);
    }
    
    // Final Query Structure
    // It is CRITICAL to join users, exam_results, and exams tables
    $sql_search = "
        SELECT 
            er.id AS result_id, 
            er.score, 
            er.total_questions, 
            er.percentage, 
            er.date_taken, 
            u.fullname AS examinee_name, 
            u.email AS examinee_email,
            e.subject AS exam_subject
        FROM 
            exam_results er
        JOIN 
            users u ON er.user_id = u.id
        JOIN 
            exams e ON er.exam_id = e.id
        {$where_clause}
        ORDER BY 
            er.date_taken DESC, er.percentage DESC
    ";

    try {
        $query_results = $dbh->prepare($sql_search);
        $query_results->execute($params);
        $results = $query_results->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = '<div class="alert alert-danger">Search Query Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advance Search | Teacher</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* Reusing the established Blue/Boxed UI styles */
:root {
    --primary-color: #3b82f6; 
    --secondary-color: #14b8a6; 
    --blue-sidebar: #2563eb; 
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

/* Custom search style */
.search-card {
    border-top: 5px solid var(--primary-color);
}
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
        <li><a href="manage_account.php"><i class="fa fa-users"></i>Manage Account</a></li>
        <li><a href="system_log.php"><i class="fa fa-clipboard-list"></i>System Log</a></li>
        <li><a href="manage_status.php"><i class="fa fa-cog"></i>Manage Status</a></li>
        <li><a href="advance_search.php" class="active"><i class="fa fa-search"></i>Advance Search</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h3>Advanced Search & Reports 🔎</h3>
            <p>Find specific examinee results using multiple criteria.</p>
        </div>
        <a href="../logout.php" class="btn btn-danger">Logout</a>
    </div>

    <?php echo $error_message; ?>

    <div class="card p-4 shadow-sm mb-4 search-card">
        <h5 class="fw-bold text-primary mb-3">Search Filters</h5>
        <form method="GET" action="advance_search.php" class="row g-3">
            <input type="hidden" name="action" value="search">
            
            <div class="col-md-4">
                <label for="exam_id" class="form-label">Exam Subject</label>
                <select class="form-select" id="exam_id" name="exam_id">
                    <option value="">-- All Subjects --</option>
                    <?php foreach ($all_exams as $exam): ?>
                        <option value="<?php echo htmlspecialchars($exam['id']); ?>" 
                            <?php echo (isset($_GET['exam_id']) && $_GET['exam_id'] == $exam['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($exam['subject']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="examinee_keyword" class="form-label">Examinee Name/Email</label>
                <input type="text" class="form-control" id="examinee_keyword" name="examinee_keyword" 
                       placeholder="Enter name or email..." 
                       value="<?php echo htmlspecialchars($_GET['examinee_keyword'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
            </div>

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary me-2"><i class="fa fa-search"></i> Search Results</button>
                <a href="advance_search.php" class="btn btn-secondary"><i class="fa fa-redo"></i> Reset</a>
            </div>
        </form>
    </div>

    <div class="card p-4 shadow-sm">
        <h5 class="fw-bold mb-4">
            <?php 
                if ($search_performed) {
                    echo "Search Results (Found: " . count($results) . ")";
                } else {
                    echo "Latest Exam Results";
                }
            ?>
        </h5>
        
        <?php if (!empty($results)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 20%;">Examinee Name</th>
                            <th style="width: 25%;">Exam Subject</th>
                            <th style="width: 15%;">Date Taken</th>
                            <th style="width: 15%;">Score</th>
                            <th style="width: 15%;">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['result_id']); ?></td>
                                <td><?php echo htmlspecialchars($r['examinee_name']); ?> (<?php echo htmlspecialchars($r['examinee_email']); ?>)</td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($r['exam_subject']); ?></span></td>
                                <td><?php echo date("Y-m-d H:i", strtotime($r['date_taken'])); ?></td>
                                <td><span class="fw-bold"><?php echo htmlspecialchars($r['score']); ?> / <?php echo htmlspecialchars($r['total_questions']); ?></span></td>
                                <td>
                                    <span class="badge bg-<?php echo ($r['percentage'] >= 75) ? 'success' : 'danger'; ?> fs-6">
                                        <?php echo htmlspecialchars(number_format($r['percentage'], 2)); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($search_performed): ?>
            <div class="alert alert-warning text-center mt-3">No results found matching your criteria.</div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-3">
                Enter criteria above and click 'Search Results' to find specific exam records.
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>