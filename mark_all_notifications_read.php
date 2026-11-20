
<?php

// Start session
session_start();

// Check whether the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Connect to database
$conn = new mysqli(
    "localhost",
    "root",
    "1234",
    "lostandfound",
    4406
);

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed.");
}

// Get logged-in user's ID
$userID = (int)$_SESSION['user_id'];

// Mark all of this user's notifications as read
$sql = "UPDATE notification
        SET IsRead = 1
        WHERE UserID = ?
        AND IsRead = 0";

$stmt = $conn->prepare($sql);

//check the statement preparation whether it is successful or not
if (!$stmt) {
    die("SQL preparation failed.");
}


$stmt->bind_param("i", $userID);
$stmt->execute();

// Close database resources
$stmt->close();
$conn->close();

// Return to notifications page
header("Location: notifications.php");
exit();

