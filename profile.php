<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

// Connect to the database
$conn = new mysqli(
    "localhost", 
    "root", 
    "1234", 
    "lostandfound", 
    4406
    );

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set character encoding
$conn->set_charset("utf8mb4");

// Set the character set to UTF-8
function e($value){
    return htmlspecialchars((string) ($value ??''),
    ENT_QUOTES, 
    'UTF-8'
    );
}

// Retrieve the logged-in user's profile picture
require_once __DIR__ . "/get-profile-picture.php";

// Get the logged-in user's ID
$userID = $_SESSION["user_id"];

// Create a CSRF token for form security
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

// ==========================================
// UPDATE PROFILE INFORMATION
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_profile"])) {

    // Check the CSRF token
    if (!isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {

        $message = "Invalid request. Please try again.";
        $messageType = "danger";

    } else {

        // Get values entered by the user
        $name = trim($_POST["name"] ?? "");
        $studentID = trim($_POST["student_id"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");

        // Validate the input fields
        if ($name === "" || $studentID === "" ||
            $email === "" || $phone === "") {

            $message = "Please fill in all fields.";
            $messageType = "danger";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $message = "Please enter a valid email address.";
            $messageType = "danger";

        } else {

            // Update the user's information in the USER table
            $sql = "UPDATE `USER`
                    SET `Name` = ?,
                        `StudentID` = ?,
                        `Email` = ?,
                        `Phone` = ?
                    WHERE `UserID` = ?";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                unlink($destination);

                $message = "Database preparation failed.";
                $messageType = "danger";

            } 
                else {

                $stmt->bind_param("ssssi",
                $name,
                $studentID, 
                $email,
                $phone,
                $filePath, 
                $userID
                );

                if ($stmt->execute()) {

                    $message = "Profile picture updated successfully!";
                    $messageType = "success";

                    // Delete the previous picture
                    if (
                        !empty($oldPicture) &&
                        strpos($oldPicture, "uploads/profile/") === 0
                    ) {
                        $oldFile = __DIR__ . "/" . $oldPicture;

                        if (is_file($oldFile)) {
                            unlink($oldFile);
                        }
                    }

                } else {

                    unlink($destination);

                    $message = "Unable to update your profile picture.";
                    $messageType = "danger";
                }

                $stmt->close();
            }
        }
    }
}

// ==========================================
// UPLOAD PROFILE PICTURE
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["upload_picture"])) {

    // Verify CSRF token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {

        $message = "Invalid request. Please try again.";
        $messageType = "danger";

    } elseif (
        !isset($_FILES["profile_picture"]) ||
        $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_OK
    ) {

        $message = "Please select a picture to upload.";
        $messageType = "danger";

    } else {

        $file = $_FILES["profile_picture"];

        // Maximum upload size: 2 MB
        $maxSize = 2 * 1024 * 1024;

        // Check file size
        if ($file["size"] > $maxSize) {

            $message = "Your picture must be smaller than 2 MB.";
            $messageType = "danger";

        } else {

            // Check the actual image type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file["tmp_name"]);

            $allowedTypes = [
                "image/jpeg" => "jpg",
                "image/png"  => "png"
            ];

            if (!isset($allowedTypes[$mimeType])) {

                $message = "Only JPG and PNG images are allowed.";
                $messageType = "danger";

            } elseif (getimagesize($file["tmp_name"]) === false) {

                $message = "The uploaded file is not a valid image.";
                $messageType = "danger";

            } else {

                // Create the upload directory if necessary
                $uploadDirectory = __DIR__ . "/uploads/profile/";

                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0755, true);
                }

                // Generate a unique filename
                $extension = $allowedTypes[$mimeType];

                $fileName = "user_" . $userID . "_" .
                            bin2hex(random_bytes(8)) . "." . $extension;

                $filePath = "uploads/profile/" . $fileName;

                $destination = $uploadDirectory . $fileName;

                // Move the uploaded picture
                if (move_uploaded_file($file["tmp_name"], $destination)) {

                    // Get the previous profile picture
                   $oldStmt = $conn->prepare(
                    "SELECT ProfilePicture FROM `USER` WHERE UserID = ?"
                    );

                    $oldStmt->bind_param("i", $userID);
                    $oldStmt->execute();

                    $oldResult = $oldStmt->get_result();
                    $oldData = $oldResult->fetch_assoc();

                    $oldPicture = $oldData["ProfilePicture"] ?? "";

                    $oldStmt->close();

                    // Save the new picture path in the database
                   $sql = "UPDATE `USER`
                           SET `ProfilePicture` = ?
                           WHERE `UserID` = ?";

                    $stmt = $conn->prepare($sql);

                    if (!$stmt) {

                    unlink($destination);

                    $message = "Database preparation failed.";
                    $messageType = "danger";

                    } else {

                    $stmt->bind_param("si", $filePath, $userID);

                    if ($stmt->execute()) {

                        $message = "Profile picture updated successfully!";
                        $messageType = "success";

                        // Remove the previous uploaded picture
                        if (
                            !empty($oldPicture) &&
                            strpos($oldPicture, "uploads/profile/") === 0
                        ) {

                            $oldFile = __DIR__ . "/" . $oldPicture;

                            if (is_file($oldFile)) {
                                unlink($oldFile);
                            }
                        }

                    } else {

                            unlink($destination);

                            $message = "Unable to update your profile picture.";
                            $messageType = "danger";
                        }
                    }
                         $stmt->close();
                        

                } 
            }
        }
    }
}


// ==========================================
// RETRIEVE USER PROFILE INFORMATION
// ==========================================

$sql = "SELECT `Name`, `StudentID`, `Email`, `Phone`,
        `ProfilePicture`
        FROM `USER`
        WHERE `UserID` = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to retrieve profile information.");
}

$stmt->bind_param("i", $userID);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

// Check whether the user exists
if (!$user) {
    session_destroy();
    header("Location: login.html");
    exit();
}

// ==========================================
// COUNT TOTAL REPORTS SUBMITTED
// ==========================================

$sql = "SELECT COUNT(*) AS total
        FROM `item_report`
        WHERE `UserID` = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();

$result = $stmt->get_result();
$reportData = $result->fetch_assoc();

$totalReports = $reportData["total"];

$stmt->close();

// ==========================================
// COUNT ITEMS RETURNED
//==========================================

// Assumption: Returned is the status used
// when an item has been successfully returned.

 $sql = "SELECT COUNT(*) AS total
        FROM `item_report`
        WHERE `UserID` = ?
         AND `Status` = 'Returned'";

 $stmt = $conn->prepare($sql);
 $stmt->bind_param("i", $userID);
 $stmt->execute();

 $result = $stmt->get_result();
 $returnedData = $result->fetch_assoc();

 $totalReturned = $returnedData["total"];

 $stmt->close();

 //Escape output before displaying it in HTML
function safeOutput($value) {
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
 }

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profile | UBfounD</title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Our own CSS file -->
    <link rel="stylesheet" href="css/style.css">

</head>

<body>

    <!-- ==================== NAVBAR ==================== -->

    <nav class="navbar navbar-expand-lg bg-white border-bottom">

        <div class="container">

            <a class="navbar-brand fw-bold" href="index.php">
                <img src="\UBfounD\assets\images\ubfound-logo-version3d.png" 
                alt="UBfounD Logo" 
                class="navbar-logo">
            </a>

            <!-- Mobile menu button -->
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
            >

                <span class="navbar-toggler-icon"></span>

            </button>

            <!-- Navigation links -->
            <div class="collapse navbar-collapse" id="navbarNav">

                <ul class="navbar-nav ms-auto">

                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="lost-items.php">Lost Items</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="found-items.php">Found Items</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="my-reports.php">My Reports</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="matches.php">Matches</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link notification-bell" href="notifications.php">
                            🔔
                        </a>
                    </li>

                   
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2" href="profile.php">

                              <?php if (
                                    !empty($navProfilePicture) &&
                                    file_exists(__DIR__ . "/" . $navProfilePicture)
                                   ): ?>

                                    <img
                                        src="<?php echo e($navProfilePicture); ?>"
                                        alt="Profile Picture"
                                        class="rounded-circle"
                                        width="35"
                                        height="35"
                                        style="object-fit: cover;"
                                    >

                                    <?php else: ?>

                                    <span
                                        class="rounded-circle bg-light d-inline-flex
                                            align-items-center justify-content-center"
                                        style="width: 35px; height: 35px;"
                                    >
                                        👤
                                    </span>


                                <?php endif; ?>

                        </a>
                    </li>

                </ul>

            </div>

        </div>

    </nav>


    <!-- ==================== PROFILE HEADER ==================== -->

    <section class="py-5">

        <div class="container">

            <h1 class="fw-bold">
                My Profile
            </h1>

            <p class="text-muted">
                View and manage your UBfounD account information.
            </p>

        </div>

    </section>


    <!-- ==================== PROFILE INFORMATION ==================== -->

    <section class="pb-5">

        <div class="container">

            <!-- Display success or error message -->
            <?php if ($message !== ""): ?>

                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">

                    <?php echo safeOutput($message); ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <div class="row g-4">

                <!-- ==================== PROFILE CARD ==================== -->

                <div class="col-lg-4">

                    <div class="card shadow-sm h-100">

                        <div class="card-body text-center p-4">

                            
                            <!-- Profile Picture -->
                                    <div class="mb-3">

                                      <?php if (!empty($user["ProfilePicture"]) &&
                                        file_exists(__DIR__ . "/" . $user["ProfilePicture"])): ?>

                                            <img
                                                src="<?php echo safeOutput($user["ProfilePicture"]); ?>"
                                                alt="Profile Picture"
                                                class="rounded-circle"
                                                width="130"
                                                height="130"
                                                style="object-fit: cover;"
                                            >

                                                    <?php else: ?>

                                                            <div
                                                                class="rounded-circle bg-light d-inline-flex
                                                                    align-items-center justify-content-center"
                                                                style="width: 130px; height: 130px; font-size: 65px;"
                                                            >
                                                                👤
                                                            </div>
                                                  <?php endif; ?>

                                   </div>
                                   <!-- Change Profile Picture Form -->
                                    <form method="POST"
                                        action="profile.php"
                                        enctype="multipart/form-data">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo safeOutput($_SESSION["csrf_token"]); ?>"
                                    >

                                    <div class="mb-2">

                                        <input
                                            type="file"
                                            name="profile_picture"
                                            class="form-control form-control-sm"
                                            accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                            required
                                        >

                                    </div>

                                    <button
                                        type="submit"
                                        name="upload_picture"
                                        class="btn btn-outline-primary btn-sm"
                                    >
                                        <i class="bi bi-camera mt-2"></i>
                                        Upload Picture
                                    </button>

                                    <p class="text-muted small mt-3">
                                        JPG or PNG only. Maximum size: 2 MB.
                                    </p>

                                </form>
                            <!-- Display user's name -->
                            <h4 class="fw-bold">
                                <?php echo safeOutput($user["Name"]); ?>
                            </h4>

                            <p class="text-muted mb-1">
                                Computer Science Student
                            </p>

                            <p class="text-muted">
                                Universiti Brunei Darussalam 
                            </p>

                            <hr>

                            <!-- Display total reports -->
                            <p class="mb-1">
                                <strong> Total Reports:</strong>
                                <?php echo $totalReports; ?>
                            </p>

                            <!-- Display returned items
                            <p class="mb-0">
                                <strong>Items Returned:</strong>
                                <?php echo $totalReturned; ?>
                            </p> -->

                                <p class="mb-0 mt-4">
                                    <!-- ====================LOG OUT BUTTON ==================== -->
    
                                    <form method="POST" action="logout.php"
                                        onsubmit="return confirm('Are you sure you want to log out?');">

                                        <input
                                            type="hidden"
                                                name="csrf_token"
                                                    value="<?php echo safeOutput($_SESSION['csrf_token']); ?>"
                                            >                   

                                            <button type="submit" class="btn btn-danger">
                                                Log Out
                                            </button>

                                    </form>
                                </p>

                        </div>

                    </div>

                </div>


                <!-- ==================== ACCOUNT DETAILS ==================== -->

                <div class="col-lg-8">

                    <div class="card shadow-sm">

                        <div class="card-body p-4">

                            <h4 class="fw-bold mb-4">
                                Account Information
                            </h4>

                            <!-- Profile update form -->
                            <form method="POST" action="profile.php">

                                <!-- CSRF security token -->
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo safeOutput($_SESSION["csrf_token"]); ?>"
                                >

                                <!-- Full Name -->
                                <div class="mb-3">

                                    <label class="form-label fw-semibold">
                                        Full Name
                                    </label>

                                    <input
                                        type="text"
                                        name="name"
                                        class="form-control"
                                        value="<?php echo safeOutput($user["Name"]); ?>"
                                        required
                                    >

                                </div>


                                <!-- Student ID -->
                                <div class="mb-3">

                                    <label class="form-label fw-semibold">
                                        Student ID
                                    </label>

                                    <input
                                        type="text"
                                        name="student_id"
                                        class="form-control"
                                        value="<?php echo safeOutput($user["StudentID"]); ?>"
                                        required
                                    >

                                </div>


                                <!-- Email -->
                                <div class="mb-3">

                                    <label class="form-label fw-semibold">
                                        Email Address
                                    </label>

                                    <input
                                        type="email"
                                        name="email"
                                        class="form-control"
                                        value="<?php echo safeOutput($user["Email"]); ?>"
                                        required
                                    >

                                </div>


                                <!-- Phone -->
                                <div class="mb-4">

                                    <label class="form-label fw-semibold">
                                        Phone Number
                                    </label>

                                    <input
                                        type="tel"
                                        name="phone"
                                        class="form-control"
                                        value="<?php echo safeOutput($user["Phone"]); ?>"
                                        required
                                    >

                                </div>


                                <!-- Save button -->
                                <button
                                    type="submit"
                                    name="update_profile"
                                    class="btn btn-primary"
                                >
                                    Save Changes
                                </button>

                            </form>

                        </div>

                    </div>


                    <!-- ==================== ACCOUNT SETTINGS ==================== -->

                    <div class="card shadow-sm mt-4">

                        <div class="card-body p-4">

                            <h4 class="fw-bold mb-3">
                                Account Settings
                            </h4>

                            <!-- Notification information -->
                            <div class="d-flex justify-content-between align-items-center mb-3">

                                <div>

                                    <strong>Email Notifications</strong>

                                    <p class="text-muted small mb-0">
                                        Receive updates about your reports and matches.
                                    </p>

                                </div>

                                <span class="badge bg-secondary">
                                    Available notifications
                                </span>

                            </div>

                            <hr>

                            <!-- Account information -->
                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <strong>Account Status</strong>

                                    <p class="text-muted small mb-0">
                                        Your account information is registered in UBfounD.
                                    </p>

                                </div>

                                <span class="badge bg-success">
                                    Registered
                                </span>

                            </div>
                                

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ==================== FOOTER ==================== -->

    <footer class="py-2">

        <div class="container text-center">

            <p class="fw-bold mb-1">
                UBfounD 
            </p>

            <p class="small mb-1">
                Campus Lost & Found System
            </p>

            <p class="small mb-1">
                Need help?
            </p>

            <p class="small mb-1">
                Email:
                <a href="mailto:lostandfound@ubd.edu.bn">
                    ubfound@ubd.edu.bn
                </a>
            </p>

            <p class="small mb-2">
                Phone: +673 246 XXXX
            </p>

            <p class="small mb-0">
                © 2026 UBfounD
            </p>

        </div>

    </footer>


    <!-- Bootstrap JavaScript -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    ></script>

</body>

</html>

<?php

// Close the database connection
$conn->close();

?>
