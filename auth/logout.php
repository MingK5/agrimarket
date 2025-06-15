<?php
session_start();

// Regenerate session ID to prevent fixation attacks
session_regenerate_id(true);

// Clear all session variables
$_SESSION = [];
session_unset();
session_destroy();

// Expire the session cookie
setcookie(
    session_name(),     // session name (e.g., PHPSESSID)
    '',                 // empty value
    time() - 3600,      // expired in the past
    '/agrimarket/',    
    '',                 // domain (can stay blank)
    false,              // set to true if using HTTPS
    true                // HttpOnly to prevent JavaScript access
);

// Send no-cache headers to prevent back button access
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to landing page
header("Location: ../index.php");
exit();