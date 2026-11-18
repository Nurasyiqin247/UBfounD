<?php
session_start();


// Check whether the user is logged in
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
    4406);

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set character encoding
$conn->set_charset("utf8mb4");

// Get the logged-in user's ID
$userID = $_SESSION["user_id"];




/* ==========================================
   TEXT SIMILARITY FUNCTION
========================================== */

// Calculate similarity between two text values
function textSimilarity($value1, $value2) {

    $value1 = strtolower(trim((string)$value1));
    $value2 = strtolower(trim((string)$value2));

    // Return 0 if either value is empty
    if ($value1 === "" || $value2 === "") {
        return 0;
    }

    // Exact match
    if ($value1 === $value2) {
        return 100;
    }

    // Remove common descriptive words
    $removeWords = [
        "the", "a", "an", "colour", "color",
        "item", "with"
    ];

    $words1 = explode(" ", $value1);
    $words2 = explode(" ", $value2);

    $words1 = array_diff($words1, $removeWords);
    $words2 = array_diff($words2, $removeWords);

    $words1 = array_values(array_filter($words1));
    $words2 = array_values(array_filter($words2));

    // Check whether the descriptions share words
    $commonWords = array_intersect($words1, $words2);

    $allWords = array_unique(array_merge($words1, $words2));

    $wordScore = count($allWords) > 0
        ? (count($commonWords) / count($allWords)) * 100
        : 0;

    // Calculate spelling similarity
    $similarTextScore = 0;
    similar_text($value1, $value2, $similarTextScore);

    // Return the better score
    return round(max($wordScore, $similarTextScore), 2);
}


/* ==========================================
   COLOUR NORMALISATION
========================================== */

// Convert common colour variations into standard names
function normaliseColour($colour) {

    // Convert colour to lowercase
    $colour = strtolower(trim((string)$colour));

    // Group grey and gray together
    $colourGroups = [
        "grey" => "gray",
        "gray" => "gray",
        "silver" => "silver",
        "navy blue" => "blue",
        "dark blue" => "blue",
        "light blue" => "blue",
        "navy" => "blue",
        "maroon" => "red",
        "burgundy" => "red",
        "cream" => "white"
    ];

    // Return the standard colour name if available
    return $colourGroups[$colour] ?? $colour;
}


/* ==========================================
   MATCHING SCORE CALCULATION
========================================== */

// Calculate the matching score between lost and found items
function calculateMatchScore($lost, $found) {

    // Define the importance of each attribute
    $weights = [
        "category" => 25,
        "name" => 20,
        "colour" => 15,
        "brand" => 15,
        "size" => 10,
        "location" => 10,
        "date" => 5
    ];

    // Store the similarity scores
    $scores = [];

    // Compare item categories
    $scores["category"] = textSimilarity(
        $lost["CategoryName"],
        $found["CategoryName"]
    );

    // Compare item names
    $scores["name"] = textSimilarity(
        $lost["ItemName"],
        $found["ItemName"]
    );

    // Compare colours after normalising them
    $scores["colour"] = textSimilarity(
        normaliseColour($lost["Colour"]),
        normaliseColour($found["Colour"])
    );

    // Compare brands
    $scores["brand"] = textSimilarity(
        $lost["Brand"],
        $found["Brand"]
    );

    // Compare sizes
    $scores["size"] = textSimilarity(
        $lost["Size"],
        $found["Size"]
    );

    // Compare locations
    $scores["location"] = textSimilarity(
        $lost["Location"],
        $found["Location"]
    );

    // Compare the dates of the lost and found reports
    $lostDate = !empty($lost["DateOccurred"])
        ? strtotime($lost["DateOccurred"])
        : false;

    $foundDate = !empty($found["DateOccurred"])
        ? strtotime($found["DateOccurred"])
        : false;

    if ($lostDate !== false && $foundDate !== false) {

        // Calculate the difference between the dates
        $daysDifference = abs($lostDate - $foundDate) / 86400;

        // Give higher scores to dates that are closer
        if ($daysDifference == 0) {
            $scores["date"] = 100;
        } elseif ($daysDifference <= 3) {
            $scores["date"] = 80;
        } elseif ($daysDifference <= 7) {
            $scores["date"] = 60;
        } elseif ($daysDifference <= 14) {
            $scores["date"] = 40;
        } elseif ($daysDifference <= 30) {
            $scores["date"] = 20;
        } else {
            $scores["date"] = 0;
        }

    } else {
        $scores["date"] = 0;
    }
    // Map each attribute to its database column
    $columns = [
        // TEMPORARY: Display individual matching scores
       "category" => "CategoryName",
        "name" => "ItemName",
        "colour" => "Colour",
        "brand" => "Brand",
        "size" => "Size",
        "location" => "Location",
        "date" => "DateOccurred"
    ];

    // Initialise the total score and weight
    $totalScore = 0;
    $totalWeight = 0;

    // Calculate the weighted matching score
    foreach ($weights as $attribute => $weight) {

        $column = $columns[$attribute];

        // Only compare attributes that are available in both reports
        if (
            !empty($lost[$column]) &&
            !empty($found[$column])
        ) {

            // Add the weighted similarity score
            $totalScore += $scores[$attribute] * $weight;

            // Add the attribute's weight
            $totalWeight += $weight;
        }
    }

    // Avoid division by zero
    if ($totalWeight == 0) {
        return 0;
    }

    // Return the final matching percentage
    return round($totalScore / $totalWeight, 2);
}


/* ==========================================
   RETRIEVE LOST ITEM REPORTS
========================================== */

// Retrieve the logged-in user's open lost item reports
$lostSQL = "
    SELECT
        r.ReportID,
        r.DateOccurred,
        r.Location,
        p.ItemName,
        p.Size,
        p.Brand,
        p.Colour,
        c.CategoryName
    FROM `item_report` r
    JOIN `item_profile` p
        ON r.ItemProfileID = p.ItemProfileID
    JOIN `category` c
        ON p.CategoryID = c.CategoryID
    WHERE r.UserID = ?
        AND r.ReportType = 'Lost'
        AND r.Status = 'Open'
";

$lostStmt = $conn->prepare($lostSQL);
$lostStmt->bind_param("i", $userID);
$lostStmt->execute();

$lostResult = $lostStmt->get_result();

// Store all lost reports
$lostReports = [];

while ($row = $lostResult->fetch_assoc()) {
    $lostReports[] = $row;
}


/* ==========================================
   RETRIEVE FOUND ITEM REPORTS
========================================== */

// Retrieve open found item reports from other users
$foundSQL = "
    SELECT
        r.ReportID,
        r.DateOccurred,
        r.Location,
        p.ItemName,
        p.Size,
        p.Brand,
        p.Colour,
        c.CategoryName,
        (
            SELECT i.ImageLink
            FROM `item_image` i
            WHERE i.ItemProfileID = p.ItemProfileID
            ORDER BY i.DateUpload DESC
            LIMIT 1
        ) AS ImageLink
    FROM `item_report` r
    JOIN `item_profile` p
        ON r.ItemProfileID = p.ItemProfileID
    JOIN `category` c
        ON p.CategoryID = c.CategoryID
    WHERE r.ReportType = 'FOUND'
        AND r.Status = 'Found item is available to claim'
        AND r.UserID != ?
";

$foundStmt = $conn->prepare($foundSQL);
$foundStmt->bind_param("i", $userID);
$foundStmt->execute();

$foundResult = $foundStmt->get_result();


// Store found reports
$foundReports = [];

while ($row = $foundResult->fetch_assoc()) {
    $foundReports[] = $row;
}


/* ==========================================
   COMPARE LOST AND FOUND ITEMS
========================================== */

// Store all possible matches
$matches = [];

// Compare each found item against the user's lost reports
while ($found = $foundResult->fetch_assoc()) {

    // Compare against every lost report
    foreach ($lostReports as $lost) {

        // Calculate the matching percentage
        $score = calculateMatchScore($lost, $found);

        // Only display matches with at least 60% similarity
        if ($score >= 60) {

            // Determine the matching category
            if ($score >= 80) {
                $matchLevel = "High Match";
            } else {
                $matchLevel = "Possible Match";
            }

            // Store the matching item information
            $matches[] = [
                "ReportID" => $found["ReportID"],
                "ItemName" => $found["ItemName"],
                "CategoryName" => $found["CategoryName"],
                "Colour" => $found["Colour"],
                "Brand" => $found["Brand"],
                "Size" => $found["Size"],
                "Location" => $found["Location"],
                "DateOccurred" => $found["DateOccurred"],
                "ImageLink" => $found["ImageLink"],
                "Score" => $score,
                "MatchLevel" => $matchLevel
            ];

            // Stop after finding a match for this found report
            //break;
        }
    }
}

// Sort matches from highest to lowest score
usort($matches, function ($a, $b) {
    return $b["Score"] <=> $a["Score"];
});


// Store possible matches
$matches = [];

// Compare every found report with the user's lost reports
foreach ($foundReports as $found) {

    $bestScore = 0;
    $bestLostReport = null;

    // Compare against every lost report
    foreach ($lostReports as $lost) {

        $score = calculateMatchScore($lost, $found);
        // Keep the highest matching score
        if ($score > $bestScore) {

            $bestScore = $score;
            $bestLostReport = $lost;
        }
    }
    

    // Display matches with at least 60% similarity
    if ($bestScore >= 60 && $bestLostReport !== null) {

        $matches[] = [
            "ReportID" => $found["ReportID"],
            "ItemName" => $found["ItemName"],
            "CategoryName" => $found["CategoryName"],
            "Colour" => $found["Colour"],
            "Brand" => $found["Brand"],
            "Size" => $found["Size"],
            "Location" => $found["Location"],
            "DateOccurred" => $found["DateOccurred"],
            "ImageLink" => $found["ImageLink"],
            "LostItemName" => $bestLostReport["ItemName"],
            "Score" => $bestScore,

            "MatchLevel" => $bestScore >= 80
                ? "High Match"
                : "Possible Match"
        ];
    }
}

// Sort matches from highest to lowest
usort($matches, function ($a, $b) {
    return $b["Score"] <=> $a["Score"];
});


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Possible Matches | UBfounD</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

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


<?php ?>

<div class="container py-5">

    <h2 class="mb-3">Possible Matches</h2>

    <p class="text-muted mb-4">
        These found items have similar details to the lost items you reported.
    </p>

    <?php if (empty($lostReports)): ?>

        <div class="alert alert-info">
            You do not have any open lost item reports.
            Please report a lost item first to see possible matches.
        </div>

    <?php elseif (empty($matches)): ?>

        <div class="alert alert-info">
            No possible matches were found at the moment.
            Please check again later.
        </div>

    <?php else: ?>

        <div class="row">

            <?php foreach ($matches as $match): ?>

                <div class="col-md-4 mb-4">

                    <div class="card h-100 shadow-sm">

                        <?php if (!empty($match["ImageLink"])): ?>

                            <img
                                src="<?php echo htmlspecialchars($match["ImageLink"]); ?>"
                                class="card-img-top"
                                alt="Found item image"
                                style="height: 220px; object-fit: cover;"
                            >

                        <?php endif; ?>

                        <div class="card-body">

                            <h5 class="card-title">
                                <?php echo htmlspecialchars($match["ItemName"]); ?>
                            </h5>

                            <p class="mb-1">
                                <strong>Category:</strong>
                                <?php echo htmlspecialchars($match["CategoryName"]); ?>
                            </p>

                            <p class="mb-1">
                                <strong>Colour:</strong>
                                <?php echo htmlspecialchars($match["Colour"] ?: "Not specified"); ?>
                            </p>

                            <p class="mb-1">
                                <strong>Brand:</strong>
                                <?php echo htmlspecialchars($match["Brand"] ?: "Not specified"); ?>
                            </p>

                            <p class="mb-1">
                                <strong>Size:</strong>
                                <?php echo htmlspecialchars($match["Size"] ?: "Not specified"); ?>
                            </p>

                            <p class="mb-1">
                                <strong>Location:</strong>
                                <?php echo htmlspecialchars($match["Location"] ?: "Not specified"); ?>
                            </p>

                            <p class="mb-3">
                                <strong>Date:</strong>
                                <?php echo htmlspecialchars($match["DateOccurred"] ?: "Not specified"); ?>
                            </p>

                            <hr>

                            <p class="mb-1">
                                <strong>Matching Score:</strong>
                                <?php echo $match["Score"]; ?>%
                            </p>

                            <span class="badge <?php echo $match["Score"] >= 80 ? "bg-success" : "bg-warning text-dark"; ?>">
                                <?php echo htmlspecialchars($match["MatchLevel"]); ?>
                            </span>

                            <div class="mt-3">

                                <a
                                    href="item-details.php?id=<?php echo (int)$match["ReportID"]; ?>"
                                    class="btn btn-primary w-100"
                                >
                                    View Found Item
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

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


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php
// Close database statements and connection
$lostStmt->close();
$foundStmt->close();
$conn->close();
?>
