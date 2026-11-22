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

    ORDER BY r.DateOccurred DESC
";

// 8. Prepare and execute the SQL query
$stmt = $conn->prepare($sql);
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

    <title>Recent Reports | UBFounD</title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Your existing stylesheet -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="recent-reports-page">

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">

            <a class="navbar-brand fw-bold" href="index.php">
                <img
                    src="assets/images/ubfound-logo-version3d.png"
                    alt="UBfounD Logo"
                    class="navbar-logo"
                >
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarContent"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto">
                    <!-- ms-auto pushes the links to the right -->

                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="lost-items.php">
                            Lost Items
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="found-items.php">
                            Found Items
                        </a>
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
                                        src="<?php echo safeOutput($navProfilePicture); ?>"
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


    <!-- MAIN CONTENT -->
    <main class="container py-5">

        <!-- Page heading -->
        <div class="mb-4">
            <h1 class="fw-bold">Recent Reports</h1>

            <p class="text-muted">
                Browse all lost and found reports submitted to UBfounD.
            </p>
        </div>


        <!-- Display reports -->
        <?php if (!empty($reports)): ?>

            <div class="row">

                <?php foreach ($reports as $report): ?>

                    <div class="col-md-6 col-lg-4 mb-4">

                        <div class="card item-card h-100">

                            <!-- ITEM IMAGE -->
                            <div class="recent-report-image-wrapper">

                                <?php if (!empty($report["ImageLink"])): ?>

                                    <img
                                        src="<?= e($report["ImageLink"]) ?>"
                                        alt="<?= e($report["ItemName"]) ?>"
                                        onerror="this.style.display='none';
                                        this.nextElementSibling.style.display='flex';"
                                    >

                                    <div
                                        class="no-image-placeholder"
                                        style="display:none;"
                                    >
                                        No image available
                                    </div>

                                <?php else: ?>

                                    <div class="no-image-placeholder">
                                        No image available
                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- CARD INFORMATION -->
                            <div class="card-body">

                                <!-- Report type badge -->
                                <?php if (strtolower($report["ReportType"]) === "lost"): ?>

                                    <span class="badge bg-danger mb-2">
                                        LOST
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-success mb-2">
                                        FOUND
                                    </span>

                                <?php endif; ?>


                                <!-- Item name -->
                                <h5 class="card-title fw-bold">
                                    <?= e($report["ItemName"]) ?>
                                </h5>


                                <!-- Category -->
                                <p class="mb-1">
                                    <strong>Category:</strong>
                                    <?= e($report["CategoryName"]) ?>
                                </p>


                                <!-- Date -->
                                <p class="mb-1">
                                    <strong>Date:</strong>

                                    <?= !empty($report["DateOccurred"])
                                        ? e(date(
                                            "d M Y",
                                            strtotime($report["DateOccurred"])
                                        ))
                                        : "Not Available"
                                    ?>
                                </p>


                                <!-- Location -->
                                <p class="mb-1">
                                    <strong>Location:</strong>
                                    <?= e($report["Location"] ?: "Not Available") ?>
                                </p>


                                <!-- Status -->
                                <p class="mb-3">
                                    <strong>Status:</strong>
                                    <?= e($report["Status"]) ?>
                                </p>


                                <!-- View details -->
                                <a
                                    href="item-details.php?id=<?= (int)$report["ReportID"] ?>"
                                    class="btn btn-primary w-100"
                                >
                                    View Details
                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <!-- Message when there are no reports -->
            <div class="alert alert-info text-center">
                <h5 class="fw-bold">No Reports Available</h5>

                <p class="mb-0">
                    There are currently no lost or found reports.
                </p>
            </div>

        <?php endif; ?>

    </main>


    <!-- FOOTER -->
    <footer class="py-3 text-center">
        <div class="container">
            <p class="mb-0">
                &copy; 2026 UBFounD - Campus Lost & Found System
            </p>
        </div>
    </footer>


    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
