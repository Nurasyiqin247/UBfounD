<?php
session_start();

// Verify the CSRF token
if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_POST["csrf_token"]) ||
    !isset($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
) {
    http_response_code(403);
    exit("Invalid logout request.");
}

// Clear session data
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.html");
exit();
?>