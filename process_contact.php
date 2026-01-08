<?php
include('includes/config.php');

if(isset($_POST['send_msg'])) {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $msg = $_POST['message'];

    $sql = "INSERT INTO contact_messages (full_name, email, message) VALUES (:name, :email, :msg)";
    $query = $dbh->prepare($sql);
    $query->execute([':name' => $name, ':email' => $email, ':msg' => $msg]);

    echo "<script>alert('Message sent!'); window.location.href='index.php';</script>";
}
?>