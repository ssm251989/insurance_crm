<?php
// index.php
session_start();

// If user is already logged in, send them straight to the dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Otherwise, send them to the login page
header('Location: login.php');
exit;
?>