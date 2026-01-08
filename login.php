<?php

session_start();
include('includes/config.php');

$msg = "";

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = md5($_POST['password']);

    $sql = "SELECT * FROM users WHERE email=:email AND password=:password";
    $query = $dbh->prepare($sql);
    $query->bindParam(':email', $email);
    $query->bindParam(':password', $password);
    $query->execute();

    if ($query->rowCount() > 0) {
        $user = $query->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user'] = $user; // ✅ store all user info in session

        // ✅ Redirect based on role
        if ($user['role'] == 'Teacher') {
            header("Location: admin/dashboard.php");
            exit;
        } elseif ($user['role'] == 'Student') {
            header("Location: student/exam_dashboard.php");
            exit;
        } else {
            $msg = "<div class='alert alert-danger'>Unknown user role.</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger'>Invalid email or password!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Online Exam</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #6f42c1, #007bff); font-family: 'Poppins', sans-serif; }
.card { border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
.card h3 { color: #333; }
</style>
</head>
<body>
<div class="container d-flex justify-content-center align-items-center vh-100">
  <div class="col-md-4">
    <div class="card p-4 bg-white">
      <h3 class="text-center fw-bold mb-4">Login to Continue</h3>
      <?php echo $msg; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
        <p class="text-center mt-3 text-muted">
          No account yet? <a href="register.php">Register here</a>
        </p>
        <!-- ✅ Added Back to Homepage button -->
        <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">← Back to Homepage</a>
      </form>
    </div>
  </div>
</div>
</body>
</html>
