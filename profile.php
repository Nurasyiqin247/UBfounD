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

            if ($stmt) {

                $stmt->bind_param(
                    "ssssi",
                    $name,
                    $studentID,
                    $email,
                    $phone,
                    $userID
                );

                if ($stmt->execute()) {
                    $message = "Your profile has been updated successfully!";
                    $messageType = "success";
                } else {
                    $message = "Unable to update your profile. Please try again.";
                    $messageType = "danger";
                }

                $stmt->close();

            } else {
                $message = "Database error. Please try again.";
                $messageType = "danger";
            }
        }
    }
}

// ==========================================
// RETRIEVE USER PROFILE INFORMATION
// ==========================================

$sql = "SELECT `Name`, `StudentID`, `Email`, `Phone`
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
// ==========================================

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

// Escape output before displaying it in HTML
function safeOutput($value) {
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profile - UbdFind</title>

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
                        <a class="nav-link active" href="profile.php">
                            Profile
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

                            <!-- Profile icon -->
                            <div class="fs-1 mb-3">
                                👤
                            </div>

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
                                <strong>Reports:</strong>
                                <?php echo $totalReports; ?>
                            </p>

                            <!-- Display returned items -->
                            <p class="mb-0">
                                <strong>Items Returned:</strong>
                                <?php echo $totalReturned; ?>
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
                            <hr class="my-4">
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
