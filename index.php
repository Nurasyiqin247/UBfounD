<?php
$conn = new mysqli(
    "localhost",
    "root",
    "1234",
    "lostandfound",
    4406
);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection failed. Please try again later.");
}

function e($value){
    return htmlspecialchars((string) ($value ??''),
    ENT_QUOTES, 
    'UTF-8'
    );
}

// To count the current number of lost items, we can run a query to count the rows in the item_report table where ReportType is 'Lost'.
$lostQuery = "
SELECT COUNT(*) 
AS total_lost 
FROM item_report 
WHERE ReportType = 'Lost'
";

$lostresult = $conn->query($lostQuery);
$totalLost = $lostresult->fetch_assoc()['total_lost'] ?? 0;

// To count the current number of found items, we can run a query to count the rows in the item_report table where ReportType is 'Found'.
$foundQuery = "
SELECT COUNT(*) 
AS total_found 
FROM item_report 
WHERE ReportType = 'Found'";

$foundresult = $conn->query($foundQuery);
$totalFound = $foundresult->fetch_assoc()['total_found'];

$recentQuery = "
SELECT r.ReportID, r.ReportType, r.DateReported, r.Location, p.ItemName, c.CategoryName
(
    SELECT ii.ImageLink
    FROM item_image AS ii
    WHERE ii.ItemProfileID = p.ItemProfileID
    ORDER BY ii.DateUpload DESC
    LIMIT 1
) AS ImageLink
FROM item_report AS r

INNER JOIN item_profile AS p 
ON r.ItemProfileID = p.ItemProfileID

LEFT JOIN category AS c
ON p.CategoryID = c.CategoryID

ORDER BY r.DateReported DESC

LIMIT 3
";
$recentResult = $conn->query($recentQuery);

$recentReports = $recentResult ? 
$recentResult->fetch_all(MYSQLI_ASSOC) 
: [];




// Retrieve lost and found reports for matching
$matchingQuery = "
    SELECT
        r.ReportID,
        r.ReportType,
        p.ItemName,
        p.CategoryID

    FROM item_report r

    INNER JOIN item_profile p
        ON r.ItemProfileID = p.ItemProfileID

    WHERE UPPER(TRIM(r.ReportType)) IN ('LOST', 'FOUND')
";

$matchingResult = $conn->query($matchingQuery);

$lostReports = [];
$foundReports = [];

if ($matchingResult) {

    while ($row = $matchingResult->fetch_assoc()) {

        if (strtoupper(trim($row['ReportType'])) === 'LOST') {
            $lostReports[] = $row;
        }

        if (strtoupper(trim($row['ReportType'])) === 'FOUND') {
            $foundReports[] = $row;
        }
    }
}


// Function to clean and separate item names
function getKeywords($itemName) {

    $name = strtolower($itemName);

    // Remove punctuation
    $name = preg_replace('/[^a-z0-9\s]/', ' ', $name);

    // Separate words
    $words = preg_split('/\s+/', trim($name));

    // Remove common words
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'my', 'of'];

    $keywords = array_diff($words, $stopWords);

    return array_unique(array_filter($keywords));
}


// Count potential matching pairs
$totalMatches = 0;

foreach ($lostReports as $lost) {

    foreach ($foundReports as $found) {

        // Both items must have the same category
        if (
            empty($lost['CategoryID']) ||
            $lost['CategoryID'] != $found['CategoryID']
        ) {
            continue;
        }

        $lostWords = getKeywords($lost['ItemName']);
        $foundWords = getKeywords($found['ItemName']);

        // Find shared keywords
        $commonWords = array_intersect(
            $lostWords,
            $foundWords
        );

        // Require at least one shared keyword
        if (count($commonWords) > 0) {

            $totalMatches++;

        }
    }
}

?>

<!DOCTYPE html>
<!-- Tells the browser that this is an HTML5 document -->

<html lang="en">
<!-- The entire webpage starts here -->

<head>
    <!-- The head contains information about the webpage -->

    <meta charset="UTF-8">
    <!-- Allows normal English characters and symbols to display correctly -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Makes the website responsive on phones, tablets and laptops -->

    <title>UbdFind - Lost & Found</title>
    <!-- Text shown in the browser tab -->

    <!-- Bootstrap CSS
         Bootstrap gives us ready-made buttons, cards, navbar, forms, etc. -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Our own CSS file -->
    <link rel="stylesheet" href="css/style.css">

</head>

<body>
    <!-- Everything visible on the webpage goes inside the body -->


    <!-- ==================== NAVIGATION BAR ==================== -->

    <nav class="navbar navbar-expand-lg border-bottom">
        <!-- Bootstrap navigation bar -->

        <div class="container">
            <!-- Keeps the content from becoming too wide -->

            <a class="navbar-brand" href="index.html">
                <img src="\UBfounD\assets\images\ubfound-logo-version3c.png" 
                alt="UBfounD Logo" 
                class="navbar-logo">
                
                <!-- Logo image -->
            </a>
            <!-- Website name/logo -->

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
            >
                <!-- Button appears on smaller screens -->

                <span class="navbar-toggler-icon"></span>
                <!-- Bootstrap menu icon -->

            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <!-- Contains the navigation links -->

                <ul class="navbar-nav ms-auto">
                    <!-- ms-auto pushes the links to the right -->

                    <li class="nav-item">
                        <a class="nav-link active" href="index.html">
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
                        <a class="nav-link" href="my-reports.html">
                            My Reports
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="matches.html">
                            Matches
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="notifications.html">
                            🔔
        
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="profile.html">
                            Profile
                        </a>
                    </li>

                </ul>

            </div>
        </div>
    </nav>


    <!-- ==================== HERO SECTION ==================== -->

    <section class="hero-section">
        <!-- Main introduction area -->

        <div class="container">
            <!-- Bootstrap container keeps the content centered -->

            <div class="row align-items-center">
                <!-- Bootstrap row creates two columns -->

                <div class="col-lg-7">
                    <!-- Left side of the hero section -->

                    <p class="text-uppercase small fw-bold mb-2">
                        UBD Lost & Found
                    </p>

                    <h1 class="display-5 fw-bold">
                        Lost something on campus?
                        <br>
                        Find it here at UbdFind.
                    </h1>
                    

                    <p class="lead text-muted">
                        Report and search for your belongings or help return an item to their rightful owner.
                    </p>
                    
                    <div class="search-box mb-3">
                        <input type="text" class="form-control form-control-lg"
                            placeholder="Search for an item...">
                        <button class="btn search-button">
                            🔎 Search
                        </button>
                    </div>

                    <!-- Main action buttons -->

                    <a href="report_lost_item.php" class="btn btn-lg action-button me-2">
                       Report Lost Item
                    </a>

                    <a href="report_found_item.php" class="btn btn-lg action-button">
                        Report Found Item
                    </a>

                </div>


                <div class="col-lg-5 mt-4 mt-lg-0">
                    <!-- Right side of the hero section -->

                    <div class="hero-card">
                        <!-- Simple visual card -->

                        <div class="hero-icon">
                            📦
                        </div>

                        <h4 class="fw-bold">
                            Find it. Report it. Return it.
                        </h4>

                        <p class="text-muted mb-0">
                            A simple way for students to manage
                            lost and found items around UBD.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ==================== STATISTICS ==================== -->

    <section class="py-5">
        <!-- py-5 adds vertical spacing -->

        <div class="container">

            <div class="row g-3">

                <!-- Lost items statistic -->

                <div class="col-md-4">
                    <div class="stat-card">

                        <h2><?php echo e($totalLost); ?></h2>

                        <p class="mb-0 text-muted">
                            Lost Items
                        </p>

                    </div>
                </div>


                <!-- Found items statistic -->

                <div class="col-md-4">
                    <div class="stat-card">

                        <h2><?php echo e($totalFound); ?></h2>

                        <p class="mb-0 text-muted">
                            Found Items
                        </p>

                    </div>
                </div>


                <!-- Matches statistic -->

                <div class="col-md-4">
                    <div class="stat-card">

                        <h2><?php echo e($totalMatches); ?></h2>

                        <p class="mb-0 text-muted">
                            Possible Matches
                        </p>

                    </div>
                </div>

            </div>

        </div>

    </section>


    <!-- ==================== RECENT REPORTS ==================== -->

    <section class="pb-5">

        <div class="container">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <!-- Places the title and button on opposite sides -->

                <div>

                    <h2 class="fw-bold mb-1">
                        Recent Reports
                    </h2>

                    <p class="text-muted mb-0">
                        Recently reported items at UBD.
                    </p>

                </div>

                <a href="lost-items.php" class="btn btn-outline-primary">
                    View All
                </a>

            </div>

            <!--RECENT REPORTS GRID-->
            <div class="row g-4">

                <!-- ITEM 1 -->
                 <?php if (!empty($recentReports)): ?>

                        <?php foreach ($recentReports as $report): ?>

                            <?php
                                 $isLost = strtoupper(trim($report['ReportType'])) === 'LOST';

                                $badgeClass = $isLost
                                     ? 'text-bg-danger'
                                     : 'text-bg-success';
                            ?>


                <div class="col-md-4">

                    <div class="card item-card h-100">

                        <!-- Placeholder image -->
                         <?php if (!empty($report['ImageLink'])): ?>

                        <img
                            src="<?= e($report['ImageLink']) ?>"
                            alt="<?= e($report['ItemName']) ?>"
                            class="card-img-top"
                            style="height: 220px; object-fit: contain;"
                        >

                         <?php else: ?>
                            <div class="item-image">
                            📱
                            </div>
                         <?php endif; ?>


                         <!-- Item information -->       
                        <div class="card-body">

                            <span class="badge <?= $badgeClass ?> mb-2">
                                <?= e(strtoupper($report['ReportType'])) ?>
                            </span>

                            <h5 class="card-title">
                                <?= e($report['ItemName']) ?>
                            </h5>

                            <p class="card-text text-muted">
                                <?= e($report['ReportLocation']) ?>
                            </p>

                                <P class="small text-muted">

                                   <?= e($report['CategoryName']?: 'Uncategorized') ?>

                                </P>

                            <a href="item-details.php?id=<?= urldecode($report['ReportID']) ?>
                            " class="btn btn-sm btn-outline-primary">
                                View Details
                            </a>

                        </div>

                    </div>

                </div>
                
                    <?php endforeach; ?>

                    <?php else: ?>

                        <div class="col-12 text center  ">
                            <p class="text-muted">
                                No recent reports found.
                            </p>
                        </div>

                    <?php endif; ?>

                <!-- ITEM 2 -->

                <div class="col-md-4">

                    <div class="card item-card h-100">

                        <div class="item-image">
                            👛
                        </div>

                        <div class="card-body">

                            <span class="badge text-bg-success mb-2">
                                FOUND
                            </span>

                            <h5 class="card-title">
                                Brown Wallet
                            </h5>

                            <p class="card-text text-muted">
                                Found near the Cafeteria.
                            </p>

                            <a href="item-details.php" class="btn btn-sm btn-outline-primary">
                                View Details
                            </a>

                        </div>

                    </div>

                </div>


                <!-- ITEM 3 -->

                <div class="col-md-4">

                    <div class="card item-card h-100">

                        <div class="item-image">
                            🔑
                        </div>

                        <div class="card-body">

                            <span class="badge text-bg-danger mb-2">
                                LOST
                            </span>

                            <h5 class="card-title">
                                Key Set
                            </h5>

                            <p class="card-text text-muted">
                                Reported near Block 8.
                            </p>

                            <a href="item-details.php" class="btn btn-sm btn-outline-primary">
                                View Details

                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ==================== FOOTER ==================== -->

    <footer class="border-top py-2">

        <div class="container text-center">

            <p class="mb-1 fw-semibold">
                UBFounD
            </p>

            <!-- Contact information -->
            <p class="small mb-1">
                Need help?
            </p>

            <p class="small mb-1">
                Email:
                <a href="mailto:UBFounD@ubd.edu.bn">
                    UBFounD@ubd.edu.bn
                </a>
            </p>

            <p class="small mb-3">
                Phone: +673 246 XXXX
            </p>

        </div>

    </footer>


    <!-- Bootstrap JavaScript -->
    <!-- This allows the mobile navigation menu to open -->

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>

</body>

</html>

