<?php
session_start();
include('includes/config.php');

$msg = "";

if (isset($_POST['register'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = md5($_POST['password']);
    $confirm = md5($_POST['confirm']);
    $role = $_POST['role'];

    if ($password != $confirm) {
        $msg = "<div class='alert alert-danger'>Passwords do not match!</div>";
    } else {
        $sql = "SELECT * FROM users WHERE email=:email";
        $query = $dbh->prepare($sql);
        $query->bindParam(':email', $email);
        $query->execute();

        if ($query->rowCount() > 0) {
            $msg = "<div class='alert alert-warning'>Email already registered!</div>";
        } else {
            $sql = "INSERT INTO users(fullname, email, password, role) VALUES(:fullname, :email, :password, :role)";
            $query = $dbh->prepare($sql);
            $query->bindParam(':fullname', $fullname);
            $query->bindParam(':email', $email);
            $query->bindParam(':password', $password);
            $query->bindParam(':role', $role);
            $query->execute();

            $msg = "<div class='alert alert-success'>Registration successful! Redirecting...</div>";
            header("refresh:2;url=login.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | Online Exam</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f8f9fa; font-family: 'Poppins', sans-serif; }
.card { border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
</style>
</head>
<body>
<div class="container d-flex justify-content-center align-items-center vh-100">
  <div class="col-md-5">
    <div class="card p-4">
      <h3 class="text-center fw-bold mb-4">Create Account</h3>
      <?php echo $msg; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="fullname" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            <option value="">Select Role</option>
            <option value="Student">Student</option>
            <option value="Teacher">Teacher</option>
          </select>
        </div>
        <button type="submit" name="register" class="btn btn-primary w-100">Register</button>
        <p class="text-center mt-3">Already have an account? <a href="login.php">Login</a></p>
      </form>
    </div>
  </div>
</div>
</body>
</html>
