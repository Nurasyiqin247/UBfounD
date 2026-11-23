<?php
// 1. Start the session
session_start();

// 2. Check whether the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

// 3. Connect to the database
$conn = new mysqli(
    "localhost",
    "root",
    "1234",
    "lostandfound",
    4406
);

// 4. Check the database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// 5. Set the character encoding
$conn->set_charset("utf8mb4");

// Retrieve the logged-in user's profile picture
require_once __DIR__ . "/get-profile-picture.php";

// 6. Helper function to safely display text
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Get the logged-in user's ID
$userID = $_SESSION['user_id'];

// 7. Retrieve all Lost and Found reports
$sql = "
    SELECT
        r.ReportID,
        r.ReportType,
        r.Status,
        r.DateOccurred,
        r.Location,
        p.ItemName,
        p.Colour,
        c.CategoryName,

        (
            SELECT i.ImageLink
            FROM item_image i
            WHERE i.ItemProfileID = p.ItemProfileID
            ORDER BY i.DateUpload DESC
            LIMIT 1
        ) AS ImageLink

    FROM item_report r

    JOIN item_profile p
        ON r.ItemProfileID = p.ItemProfileID

    JOIN category c
        ON p.CategoryID = c.CategoryID

    WHERE r.UserID= ?

    ORDER BY r.DateOccurred DESC
";

// 8. Prepare and execute the SQL query
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();

// 9. Store the reports
$result = $stmt->get_result();
$reports = $result->fetch_all(MYSQLI_ASSOC);

// 10. Close the database connection
$stmt->close();
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Reports | UbfounD</title>

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

<!-- MY REPORTS SECTION -->
<main class="container my-5">

    <div class="d-flex justify-content-between
                align-items-center mb-4">

        <div>
            <h2 class="fw-bold">My Reports</h2>

            <p class="text-muted">
                View all Lost and Found reports you have submitted.
            </p>
        </div>

        <a href="index.php"
           class="btn btn-outline-primary">
            Back to Home
        </a>

    </div>

    <!-- Number of reports -->
    <p class="mb-4">
        Total Reports:
        <strong><?php echo count($reports); ?></strong>
    </p>

    <?php if (empty($reports)): ?>

        <div class="alert alert-info">
            You have not submitted any Lost or Found reports yet.
        </div>

    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($reports as $report): ?>

                <div class="col-md-6 col-lg-4">

                    <div class="card h-100 shadow-sm">

                        <!-- Item image -->
                        <?php if (!empty($report["ImageLink"])): ?>

                            <img
                                src="<?php echo e($report["ImageLink"]); ?>"
                                class="card-img-top"
                                alt="Item image"
                                style="height: 220px;
                                       object-fit: cover;"
                            >

                        <?php else: ?>

                            <div class="bg-light d-flex
                                        align-items-center
                                        justify-content-center"
                                 style="height: 220px;">

                                <span class="text-muted">
                                    No image available
                                </span>

                            </div>

                        <?php endif; ?>

                        <!-- Report information -->
                        <div class="card-body">

                            <h5 class="card-title fw-bold">
                                <?php echo e($report["ItemName"]); ?>
                            </h5>

                            <p class="mb-2">
                                <strong>Report Type:</strong>

                                <?php echo e($report["ReportType"]); ?>
                            </p>

                            <p class="mb-2">
                                <strong>Category:</strong>

                                <?php echo e($report["CategoryName"]); ?>
                            </p>

                            <p class="mb-2">
                                <strong>Colour:</strong>

                                <?php echo e($report["Colour"]); ?>
                            </p>

                            <p class="mb-2">
                                <strong>Location:</strong>

                                <?php echo e($report["Location"]); ?>
                            </p>

                            <p class="mb-2">
                                <strong>Date Occurred:</strong>

                                <?php echo e($report["DateOccurred"]); ?>
                            </p>

                            <p class="mb-3">
                                <strong>Status:</strong>

                                <span class="badge bg-secondary">
                                    <?php echo e($report["Status"]); ?>
                                </span>
                            </p>

                            <!-- View details -->
                            <a
                                href="item-details.php?id=<?php
                                    echo (int) $report["ReportID"];
                                ?>"
                                class="btn btn-primary w-100"
                            >
                                View Details
                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>


    <!-- FOOTER -->
    <footer class="py-2">

        <div class="container text-center">

            <p class="fw-bold mb-1">
                UBfounD
            </p>

            <p class="small mb-1">
                Campus Lost &amp; Found System
            </p>

            <p class="small mb-1">
                Need help?
            </p>

            <p class="small mb-1">
                Email:
                <a href="mailto:lostandfound@university.edu">
                    <u>ubfound@ubd.edu.bn</u>
                </a>
            </p>

            <p class="small mb-2">
                Phone: +673 246 XXXX
            </p>

            <p class="small mb-0">
                &copy; 2026 UBfounD. Helping UBD students return found items.
            </p>

        </div>
    </footer>



    <!-- Import Bootstrap JavaScript for the responsive navigation menu. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>



