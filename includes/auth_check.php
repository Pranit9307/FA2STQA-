<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to access this page.";
    header("Location: login.php");
    exit();
}
?>
