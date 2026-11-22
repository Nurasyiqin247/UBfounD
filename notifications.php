<?php

// Start the session
session_start();

// Check whether the user is logged in
if (!isset($_SESSION['user_id'])) {
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
    die("Database connection failed.");
}


// Set character encoding
$conn->set_charset("utf8mb4");

// Retrieve the logged-in user's profile picture
require_once __DIR__ . "/get-profile-picture.php";

// Helper function to protect displayed text
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Get the logged-in user's ID
$userID = $_SESSION['user_id'];

// Retrieve notifications belonging to this user
$sql = "SELECT
            NotificationID,
            ReportID,
            NotificationMessage,
            IsRead,
            DateCreated
        FROM notification
        WHERE UserID = ?
        ORDER BY DateCreated DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();

$result = $stmt->get_result();

$notifications = $result->fetch_all(MYSQLI_ASSOC);

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// Count unread notifications
$unreadCount = 0;

foreach ($notifications as $notification) {
    if ((int)$notification['IsRead'] === 0) {
        $unreadCount++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Notifications | UbfounD</title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Our own CSS file -->
    <link rel="stylesheet" href="css/style.css">

</head>


<body class="notifications-page">


    <!-- ==================== NAVBAR ==================== -->

    <nav class="navbar navbar-expand-lg bg-white border-bottom">

        <div class="container">

            <!-- Website name -->
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

                < <ul class="navbar-nav ms-auto">

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
                        <a class="nav-link" href="my-reports.php">
                            My Reports
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="matches.php">
                            Matches
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link notification-bell"
                            href="notifications.php"
                            aria-label="Notifications"
                        >
                            🔔
                        </a>
                    </li>

                        <li class="nav-item">
                                <a class="nav-link d-flex align-items-center gap-2"
                                    href="profile.php">

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


 
    <!-- ==================== PAGE HEADER ==================== -->

    <section class="py-5">

        <div class="container">

            <h1 class="fw-bold">
                Notifications
            </h1>

           <!--<p class="text-muted">
               Stay updated about your lost and found items.
            </p> -->

        </div>

    </section>
    
<!-- ==================== NOTIFICATIONS ==================== -->
 <main class="flex-grow-1"> 
        <section class="pb-5">

            <div class="container">

                <div class="row justify-content-center">

                    <div class="col-lg-8">
                        <!-- Mark All as Read button -->
                            <div class="text-end mb-3">

                                <form action="mark_all_notifications_read.php"
                                    method="POST">

                                <button
                                    type="submit"
                                    class="btn btn-outline-primary"
                                    <?= $unreadCount === 0 ? 'disabled' : '' ?>
                                    >
                                Mark All as Read
                                </button>

                                </form>

                            </div>

                        <?php if (!empty($notifications)): ?>

                            <?php foreach ($notifications as $notification): ?>

                                <div class="card mb-3 shadow-sm">

                                    <div class="card-body">

                                        <div class="d-flex align-items-start">

                                            <!-- Notification icon -->
                                            <div class="me-3 fs-4">
                                                🔔
                                            </div>

                                            <div class="flex-grow-1">

                                                <!-- Notification message -->
                                                <p class="mb-2">
                                                    <?= e($notification['NotificationMessage']) ?>
                                                </p>

                                                <!-- Notification date -->
                                                <small class="text-muted">
                                                    <?= e(date(
                                                        'd M Y, h:i A',
                                                        strtotime($notification['DateCreated'])
                                                    )) ?>
                                                </small>

                                                <!-- Read / Unread status -->
                                                <?php if ((int)$notification['IsRead'] === 0): ?>

                                                    <form action="mark_notification_read.php"
                                                        method="POST"
                                                            class="mt-2">

                                                            <input type="hidden"
                                                                    name="notification_id"
                                                                        value="<?= (int)$notification['NotificationID'] ?>">

                                                                    <button type="submit"
                                                                        class="btn btn-sm btn-outline-primary">

                                                                            Mark as Read

                                                                    </button>

                                                                    </form>

                                                                    <?php else: ?>

                                                            <span class="badge bg-secondary">
                                                            Read
                                                        </span>

                                                    <?php endif; ?>
                                                
                                                        
                                                        
                                                </div>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>
                            

                            <!-- Empty notification message -->
                            <div class="text-center py-5">

                                <div class="fs-1 mb-3">
                                    🔔
                                </div>

                                <h5 class="fw-bold">
                                    No Notifications Yet
                                </h5>

                                <p class="text-muted">
                                    You will see updates about your lost and found items here.
                                </p>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </section>
    </main>


    <!-- ==================== FOOTER ==================== -->

    <footer class="py-2">

        <div class="container text-center">

            <!-- Website name -->
            <p class="fw-bold mb-1">
                UBfounD
            </p>


            <!-- Website description -->
            <p class="small mb-1">
                Campus Lost & Found System
            </p>


            <!-- Help information -->
            <p class="small mb-1">
                Need help?
            </p>


            <!-- Email -->
            <p class="small mb-1">

                Email:

                <a href="mailto:lostandfound@university.edu">
                    Ubfound@ubd.edu.bn
                </a>

            </p>


            <!-- Phone -->
            <p class="small mb-2">
                Phone: +673 246 XXXX
            </p>


            <!-- Copyright -->
            <p class="small mb-0">
                © 2026 UBfounD. Helping UBD students return found items.
            </p>

        </div>

    </footer>



    <!-- Bootstrap JavaScript -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap

