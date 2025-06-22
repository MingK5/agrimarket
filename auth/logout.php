<?php
session_start();

// capture the userType before we blow away the session
$userType = $_SESSION['user']['userType_Id'] ?? null;

// Regenerate session ID to prevent fixation attacks
session_regenerate_id(true);

// Clear all session variables
$_SESSION = [];
session_unset();
session_destroy();

// Expire the session cookie
setcookie(
    session_name(),   // session name (e.g., PHPSESSID)
    '',               // empty value
    time() - 3600,    // already expired
    '/agrimarket/',   // cookie path
    '',               // domain
    false,            // secure only?
    true              // HttpOnly
);

// Send no-cache headers to prevent back-button access
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect based on role
if ($userType === 1 || $userType === 2) {
    // Admins and Staff
    header("Location: http://localhost/agrimarket/login/admin");
} else {
    // Vendors and Customers
    header("Location: ../index.php");
}
exit();
