
<?php

$navProfilePicture = "";

if (isset($_SESSION["user_id"])) {

    $userID = $_SESSION["user_id"];

    $sql = "SELECT ProfilePicture
            FROM `USER`
            WHERE UserID = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $userID);
        $stmt->execute();

        $result = $stmt->get_result();
        $userPicture = $result->fetch_assoc();

        $navProfilePicture = $userPicture["ProfilePicture"] ?? "";

        $stmt->close();
    }
}
?>
