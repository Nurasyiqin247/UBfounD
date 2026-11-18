
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

if ($conn->connect_error) {
    die("Database connection failed.");
}

// Get notification ID
$notificationID = isset($_POST['notification_id'])
    ? (int)$_POST['notification_id']
    : 0;

// Get logged-in user ID
$userID = $_SESSION['user_id'];

// Update notification as read
$sql = "UPDATE notification
        SET IsRead = 1
        WHERE NotificationID = ?
        AND UserID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $notificationID, $userID);
$stmt->execute();

// Return to notification page
header("Location: notifications.php");
exit();

?>
