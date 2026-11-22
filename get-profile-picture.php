

<?php

// Start the session only if it has not started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default value when no profile picture is available
$navProfilePicture = null;

// Check whether the user is logged in
if (isset($_SESSION["user_id"])) {

    // Get the logged-in user's ID
    $userID = (int) $_SESSION["user_id"];

    // Retrieve the profile picture from the database
    $sql = "SELECT ProfilePicture
            FROM `USER`
            WHERE UserID = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $userID);
        $stmt->execute();

        $result = $stmt->get_result();
        $userPicture = $result->fetch_assoc();

        // Store the picture path
        if (!empty($userPicture["ProfilePicture"])) {
            $navProfilePicture = $userPicture["ProfilePicture"];
        }

        $stmt->close();
    }
}
?>
