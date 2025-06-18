<?php
session_start();

// Prevent cached content after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

function checkLogin($userType_Id = null) {
    if (!isset($_SESSION['user'])) {
        header("Location: ../auth/login_customer_vendor.php");
        exit();
    }

    if ($userType_Id && $_SESSION['user']['userType_Id'] !== $userType_Id) {
        header("Location: ../unauthorized.php");
        exit();
    }
}
?>
