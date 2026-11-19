<?php
// ==================================================
// 1. START SESSION AND CHECK LOGIN
// ==================================================

// Start the session to access the logged-in user's information.
session_start();


// Check whether the user is logged in
if (!isset($_SESSION["user_id"])) {

    header("Location: login.html");
    exit();
}


// ==================================================
// 2. CONNECT TO THE DATABASE
// ==================================================

// Connect to the Lost and Found database.

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
   3. TEXT SIMILARITY FUNCTION
========================================== */

// This function compares two text values and returns a similarity score from 0 to 100.
function textSimilarity($value1, $value2) {

    // Convert both values into lowercase and remove unnecessary spaces.
    $value1 = strtolower(trim((string)$value1));
    $value2 = strtolower(trim((string)$value2));

    // Replace multiple spaces with a single space.
    $value1 = preg_replace('/\s+/', ' ', $value1);
    $value2 = preg_replace('/\s+/', ' ', $value2);

    // Return 0 if either value is empty.
    if ($value1 === "" || $value2 === "") {
        return 0;
    }

    // Return 100 if both values are exactly the same.
    if ($value1 === $value2) {
        return 100;
    }

    // Use similar_text() to calculate the percentage of similarity.
    similar_text($value1, $value2, $similarityPercentage);

    // Calculate the Levenshtein distance between the two values.
    $distance = levenshtein($value1, $value2);

    // Find the length of the longer text.
    $longestLength = max(strlen($value1), strlen($value2));

    // Convert the Levenshtein distance into a percentage score.
    $levenshteinScore = (1 - ($distance / $longestLength)) * 100;

    // Return whichever similarity score is higher.
    return max($similarityPercentage, $levenshteinScore);
}

// Calculate similarity between two text values
/*function textSimilarity($value1, $value2) {

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
}*/


/* ==========================================
  4. COLOUR NORMALISATION
========================================== */

// Convert common colour variations into standard names
/*function normaliseColour($colour) {

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
}*/

// This function treats different descriptions of the same colour as equivalent.
function normaliseColour($colour) {

    // Convert the colour into lowercase and remove unnecessary spaces.
    $colour = strtolower(trim((string)$colour));

    // Treat grey and gray as the same colour.
    if ($colour === "grey" || $colour === "gray") {
        return "gray";
    }

    // Treat different shades of blue as blue.
    if (
        $colour === "navy blue" ||
        $colour === "dark blue" ||
        $colour === "light blue" ||
        $colour === "navy"
    ) {
        return "blue";
    }

    // Treat maroon and burgundy as red.
    if ($colour === "maroon" || $colour === "burgundy") {
        return "red";
    }

    // Treat cream as white.
    if ($colour === "cream") {
        return "white";
    }

    // Return the original colour if no normalisation is needed.
    return $colour;
}


/* ==========================================
   6. MATCHING SCORE CALCULATION
========================================== */

// Calculate the matching score between lost and found items
/*function calculateMatchScore($lost, $found) {

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
}*/

//This function compares a lost report with a found report.
function calculateMatchScore($lost, $found) {

    // Assign a weight to each attribute.
    // All weights add up to 100.
    $weights = [
        "CategoryName" => 25,
        "ItemName"     => 20,
        "Colour"       => 15,
        "Brand"        => 15,
        "Size"         => 10,
        "Location"     => 10,
        "DateOccurred"  => 5
    ];

    // Store the total weighted similarity score.
    $totalScore = 0;

    // Store the total weight of the attributes being compared.
    $totalWeight = 0;

    // Compare each attribute between the lost and found reports.
    foreach ($weights as $column => $weight) {

        // Only compare an attribute when both reports contain a value.
        if (
            isset($lost[$column], $found[$column]) &&
            trim((string)$lost[$column]) !== "" &&
            trim((string)$found[$column]) !== ""
        ) {

            // Store the values that will be compared.
            $lostValue = $lost[$column];
            $foundValue = $found[$column];

            // Normalise the colours before comparing them.
            if ($column === "Colour") {
                $lostValue = normaliseColour($lostValue);
                $foundValue = normaliseColour($foundValue);
            }

            // Calculate similarity for ordinary text attributes.
            if ($column !== "DateOccurred") {

                // Compare the two text values.
                $attributeScore = textSimilarity($lostValue, $foundValue);

            } else {

                // Convert both dates into timestamps.
                $lostDate = strtotime($lostValue);
                $foundDate = strtotime($foundValue);

                // Check whether both dates are valid.
                if ($lostDate !== false && $foundDate !== false) {

                    // Calculate the absolute difference between the dates in days.
                    $daysDifference = abs($lostDate - $foundDate) / 86400;

                    // Give a higher score when the dates are closer together.
                    if ($daysDifference == 0) {
                        $attributeScore = 100;
                    } elseif ($daysDifference <= 3) {
                        $attributeScore = 80;
                    } elseif ($daysDifference <= 7) {
                        $attributeScore = 60;
                    } elseif ($daysDifference <= 14) {
                        $attributeScore = 40;
                    } elseif ($daysDifference <= 30) {
                        $attributeScore = 20;
                    } else {
                        $attributeScore = 0;
                    }

                } else {

                    // Give 0 if either date is invalid.
                    $attributeScore = 0;
                }
            }

            // Multiply the attribute score by its assigned weight.
            $totalScore += $attributeScore * $weight;

            // Add the attribute weight to the total available weight.
            $totalWeight += $weight;
        }
    }

    // Return 0 if there are no attributes available for comparison.
    if ($totalWeight == 0) {
        return 0;
    }

    // Calculate the weighted average and round it to two decimal places.
    return round($totalScore / $totalWeight, 2);
}



/* ==========================================
  7. RETRIEVE LOST ITEM REPORTS
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
//Prepare the SQL statement to retrieve lost item reports for the logged-in user
$lostStmt = $conn->prepare($lostSQL);

//Pass the logged-in user's ID as a parameter to the SQL statement
$lostStmt->bind_param("i", $userID);

//Execute the SQL statement to retrieve lost item reports
$lostStmt->execute();


//Retrieve the query result for lost item reports
$lostResult = $lostStmt->get_result();

// Store all lost reports
$lostReports = [];

while ($row = $lostResult->fetch_assoc()) {

    //Add the lost report to the array of lost reports
    $lostReports[] = $row;
}


/* ==========================================
   7. RETRIEVE FOUND ITEM REPORTS
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

//Prepare the SQL statement to retrieve found item reports from other users
$foundStmt = $conn->prepare($foundSQL);

//Pass the logged-in user's ID as a parameter to the SQL statement
$foundStmt->bind_param("i", $userID);

//Execute the SQL statement to retrieve found item reports
$foundStmt->execute();


$foundResult = $foundStmt->get_result();


// Store found reports
$foundReports = [];

// Fetch each found report and add it to the array of found reports
while ($row = $foundResult->fetch_assoc()) {
    $foundReports[] = $row;
}


/* ==========================================
   8. COMPARE FOUND REPORTS WITH ALL LOST REPORTS
========================================== */

// Store all possible matches
$matches = [];

// Compare each found item against the user's lost reports
/*while ($found = $foundResult->fetch_assoc()) {

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
}*/

// Go through every found report.
foreach ($foundReports as $found) {

    // Store the highest matching score for this found report.
    $highestScore = 0;

    // Store the best matching lost report.
    $bestLostReport = null;

    // Compare this found report with every lost report belonging to the user.
    foreach ($lostReports as $lost) {

        // Calculate the similarity score between the two reports.
        $score = calculateMatchScore($lost, $found);

        // Check whether the score reaches the minimum 60% threshold.
        if ($score >= 60) {

            // Check whether this score is higher than the previous best score.
            if ($score > $highestScore) {

                // Update the highest matching score.
                $highestScore = $score;

                // Remember the lost report that produced this score.
                $bestLostReport = $lost;
            }
        }
    }

    // Add the found report only after all lost reports have been checked.
    if ($bestLostReport !== null) {

        // Assign a match label based on the highest score.
        if ($highestScore >= 80) {
            $matchLevel = "High Match";
        } else {
            $matchLevel = "Possible Match";
        }

        // Store the found report and its highest matching score.
        $matches[] = [
            "ReportID" => $found["ReportID"],
            "ItemName" => $found["ItemName"],
            "CategoryName" => $found["CategoryName"],
            "Location" => $found["Location"],
            "DateOccured" => $found["DateOccurred"],
            "ImageLink" => $found["ImageLink"],
            "MatchScore" => $highestScore,
            "MatchLevel" => $matchLevel
        ];
    }
}


// Sort matches from highest to lowest
/*usort($matches, function ($a, $b) {
    return $b["Score"] <=> $a["Score"];
});*/

// ==================================================
// 9. SORT MATCHES BY HIGHEST SCORE
// ==================================================

// Arrange the matching reports from highest to lowest score.
usort($matches, function ($a, $b) {

    // Compare the matching scores in descending order.
    return $b["MatchScore"] <=> $a["MatchScore"];
});

// ==================================================
// 10. CLOSE DATABASE CONNECTIONS
// ==================================================

// Close the lost reports statement.
$lostStmt->close();

// Close the found reports statement.
$foundStmt->close();

// Close the database connection.
$conn->close();


?>


<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Set the character encoding. -->
    <meta charset="UTF-8">

    <!-- Make the page responsive on different screen sizes. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Set the browser tab title. -->
    <title>Possible Matches | UBfounD</title>

    <!-- Import Bootstrap 5.3.3 CSS. -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Import the website's custom stylesheet. -->
    <link rel="stylesheet" href="css/style.css">

</head>

<body>


    <!-- ==================================================
         11. NAVIGATION BAR
    =================================================== -->

    <!-- Create the main navigation bar. -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">

        <!-- Keep the navbar content aligned with the page. -->
        <div class="container">

            <!-- Display the website name. -->
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="\UBfounD\assets\images\ubfound-logo-version3d.png" 
                alt="UBfounD Logo" 
                class="navbar-logo">
            </a>

            <!-- Button for opening the navigation menu on smaller screens. -->
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarContent"
                aria-controls="navbarContent"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >

                <!-- Display the Bootstrap menu icon. -->
                <span class="navbar-toggler-icon"></span>

            </button>

            <!-- Navigation links that collapse on smaller screens. -->
            <div class="collapse navbar-collapse" id="navbarContent">

                <!-- Align the navigation links to the right. -->
                <ul class="navbar-nav ms-auto">

                    <!-- Link to the home page. -->
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>

                    <!-- Link to the lost items page. -->
                    <li class="nav-item">
                        <a class="nav-link" href="lost_items.php">Lost Items</a>
                    </li>

                    <!-- Link to the found items page. -->
                    <li class="nav-item">
                        <a class="nav-link" href="found_items.php">Found Items</a>
                    </li>

                    <!-- Link to the user's reports page. -->
                    <li class="nav-item">
                        <a class="nav-link" href="my_reports.php">My Reports</a>
                    </li>

                    <!-- Highlight Matches because this is the current page. -->
                    <li class="nav-item">
                        <a class="nav-link active" href="matches.php">Matches</a>
                    </li>

                    <!-- Link to the notifications page. -->
                    <li class="nav-item">
                        <a class="nav-link" href="notifications.php">Notifications</a>
                    </li>

                    <!-- Link to the user's profile page. -->
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>

                </ul>

            </div>

        </div>

    </nav>


    <!-- ==================================================
         12. POSSIBLE MATCHES PAGE CONTENT
    =================================================== -->

    <!-- Main container for the page content. -->
    <div class="container py-5">

        <!-- Page heading and introduction. -->
        <div class="mb-4">

            <!-- Main page heading. -->
            <h1 class="fw-bold">Possible Matches</h1>

            <!-- Short explanation of the matching feature. -->
            <p class="text-muted">
                Found items that may match your reported lost items.
            </p>

        </div>


        <!-- ==================================================
             13. DISPLAY MATCHING FOUND REPORTS
        =================================================== -->

        <?php if (count($matches) > 0): ?>

            <!-- Loop through every matching found report. -->
            <?php foreach ($matches as $match): ?>

                <!-- Create one horizontal card for each matching found report. -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">

                    <!-- Add spacing inside the card. -->
                    <div class="card-body p-4">

                        <!-- Arrange the image, information and buttons horizontally. -->
                        <div class="row align-items-center">


                            <!-- ==========================================
                                 ITEM IMAGE
                            =========================================== -->

                            <!-- Image occupies 3 columns on medium and larger screens. -->
                            <div class="col-md-3 mb-3 mb-md-0">

                                <!-- Create an image container with a fixed height. -->
                                <div
                                    class="rounded-3 bg-light d-flex align-items-center justify-content-center"
                                    style="height: 180px; overflow: hidden;"
                                >

                                    <?php if (!empty($match["ImageLink"])): ?>

                                        <!-- Display the image when an image link exists. -->
                                        <img
                                            src="<?php echo htmlspecialchars($match["ImageLink"]); ?>"
                                            alt="Found item image"
                                            class="img-fluid w-100 h-100"
                                            style="object-fit: contain;"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                        >

                                        <!-- Display this message if the image cannot load. -->
                                        <div
                                            class="w-100 h-100 align-items-center justify-content-center text-muted"
                                            style="display: none;"
                                        >
                                            Image Not Available
                                        </div>

                                    <?php else: ?>

                                        <!-- Display this message if no image link exists. -->
                                        <span class="text-muted">
                                            Image Not Available
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>


                            <!-- ==========================================
                                 ITEM INFORMATION
                            =========================================== -->

                            <!-- Item information occupies 6 columns on medium and larger screens. -->
                            <div class="col-md-6">

                                <!-- Display the match level as a badge. -->
                                <?php if ($match["MatchLevel"] === "High Match"): ?>

                                    <!-- Green badge for high matching scores. -->
                                    <span class="badge bg-success mb-2">
                                        <?php echo htmlspecialchars($match["MatchLevel"]); ?>
                                    </span>

                                <?php else: ?>

                                    <!-- Yellow badge for possible matching scores. -->
                                    <span class="badge bg-warning text-dark mb-2">
                                        <?php echo htmlspecialchars($match["MatchLevel"]); ?>
                                    </span>

                                <?php endif; ?>

                                <!-- Display the found item's name. -->
                                <h5 class="fw-bold mb-2">
                                    <?php echo htmlspecialchars($match["ItemName"]); ?>
                                </h5>

                                <!-- Display the category of the found item. -->
                                <p class="mb-1">
                                    <strong>Category:</strong>
                                    <?php echo htmlspecialchars($match["CategoryName"]); ?>
                                </p>

                                <!-- Display the date when the item was found. -->
                                <p class="mb-1">
                                    <strong>Date Found:</strong>

                                    <?php
                                    // Format the date for easier reading.
                                    echo !empty($match["DateOccurred"])
                                        ? htmlspecialchars(date("d M Y", strtotime($match["DateOccurred"])))
                                        : "Not Available";
                                    ?>

                                </p>

                                <!-- Display the location where the item was found. -->
                                <p class="mb-1">
                                    <strong>Location:</strong>

                                    <?php
                                    // Display the location or a fallback message.
                                    echo !empty($match["Location"])
                                        ? htmlspecialchars($match["Location"])
                                        : "Not Available";
                                    ?>

                                </p>

                            </div>


                            <!-- ==========================================
                                 ACTION BUTTONS
                            =========================================== -->

                            <!-- Buttons occupy 3 columns on medium and larger screens. -->
                            <div class="col-md-3 mt-3 mt-md-0">

                                <!-- Stack the buttons vertically. -->
                                <div class="d-grid gap-2">

                                    <!-- View Details will be connected later. -->
                                    <a
                                        href="item-details.php?id=<?php echo (int)$match['ReportID']; ?>"
                                        class="btn btn-primary"
                                    >
                                        View Details
                                    </a>

                                    <!-- Contact Finder will be connected later. -->
                                    <a
                                        href="#"
                                        onclick="return false;"
                                        class="btn btn-outline-primary"
                                    >
                                        Contact Finder
                                    </a>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <!-- Display a message when no matching reports are found. -->
            <div class="alert alert-info text-center rounded-4">

                <!-- Heading for the empty results message. -->
                <h5 class="fw-bold">No Possible Matches Found</h5>

                <!-- Explain why the page may be empty. -->
                <p class="mb-0">
                    There are currently no found items matching your open lost item reports.
                    Please check again later.
                </p>

            </div>

        <?php endif; ?>

    </div>


    <!-- ==================================================
         14. FOOTER
    =================================================== -->

    <!-- Display the website footer. -->
    <footer class="py-2">

        <!-- Display the copyright information. -->
        <p class="mb-0 text-muted">
            &copy; <?php echo date("Y"); ?> UBfounD. All Rights Reserved.
        </p>

    </footer>


    <!-- Import Bootstrap JavaScript for the responsive navigation menu. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>

