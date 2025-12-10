<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin.html");
    exit();
}

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$surveyFilter = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : '';
$branchFilter = isset($_GET['branch']) ? $_GET['branch'] : '';

// Build query for responses with filters
$query = "SELECT sr.*, s.survey_title FROM survey_responses sr JOIN surveys s ON sr.survey_id = s.id WHERE 1=1";
$params = [];
$types = "";

if ($dateFrom) {
    $query .= " AND DATE(sr.date_submitted) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}
if ($dateTo) {
    $query .= " AND DATE(sr.date_submitted) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}
if ($surveyFilter) {
    $query .= " AND sr.survey_id = ?";
    $params[] = $surveyFilter;
    $types .= "i";
}
if ($branchFilter && $branchFilter != 'All Branches') {
    $query .= " AND sr.branch = ?";
    $params[] = $branchFilter;
    $types .= "s";
}

$query .= " ORDER BY sr.date_submitted DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$responses = [];
while ($row = $result->fetch_assoc()) {
    $responses[] = $row;
}
$stmt->close();

// Calculate statistics
$totalResponses = count($responses);

// Initialize analytics variables
$avgSatisfaction = 0;
$ratingCount = 0;
$branchScores = [];
$ratingDistribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$monthlyTrends = [];
$completionRate = 100; // Assuming all responses are complete
$questionAnalytics = [];

// Get questions for the selected survey (if any)
$surveyQuestions = [];
if ($surveyFilter) {
    $questionsQuery = "SELECT id, question_text, question_type FROM survey_questions WHERE survey_id = ? ORDER BY id";
    $stmt = $conn->prepare($questionsQuery);
    $stmt->bind_param("i", $surveyFilter);
    $stmt->execute();
    $questionsResult = $stmt->get_result();
    while ($question = $questionsResult->fetch_assoc()) {
        $surveyQuestions[] = $question;
        $questionAnalytics[$question['id']] = [
            'question_text' => $question['question_text'],
            'question_type' => $question['question_type'],
            'responses' => 0,
            'total_rating' => 0,
            'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'text_responses' => []
        ];
    }
    $stmt->close();
}

foreach ($responses as $response) {
    // Get answers for this response
    $answersQuery = "SELECT sa.*, sq.question_type, sq.question_text FROM survey_answers sa JOIN survey_questions sq ON sa.question_id = sq.id WHERE sa.response_id = ?";
    $stmt = $conn->prepare($answersQuery);
    $stmt->bind_param("i", $response['id']);
    $stmt->execute();
    $answersResult = $stmt->get_result();

    $responseRatingTotal = 0;
    $responseRatingCount = 0;
    $month = date('M Y', strtotime($response['date_submitted']));
    if (!isset($monthlyTrends[$month])) {
        $monthlyTrends[$month] = ['total' => 0, 'count' => 0];
    }

    while ($answer = $answersResult->fetch_assoc()) {
        if ($answer['question_type'] == 'rating' && is_numeric($answer['answer_value'])) {
            $rating = intval($answer['answer_value']);
            $avgSatisfaction += $rating;
            $ratingCount++;
            $responseRatingTotal += $rating;
            $responseRatingCount++;

            // Rating distribution
            if ($rating >= 1 && $rating <= 5) {
                $ratingDistribution[$rating]++;
            }

            $branch = $response['branch'] ?: 'Unknown';
            if (!isset($branchScores[$branch])) {
                $branchScores[$branch] = ['total' => 0, 'count' => 0];
            }
            $branchScores[$branch]['total'] += $rating;
            $branchScores[$branch]['count']++;

            // Question-specific analytics
            if (isset($questionAnalytics[$answer['question_id']])) {
                $questionAnalytics[$answer['question_id']]['responses']++;
                $questionAnalytics[$answer['question_id']]['total_rating'] += $rating;
                if ($rating >= 1 && $rating <= 5) {
                    $questionAnalytics[$answer['question_id']]['rating_distribution'][$rating]++;
                }
            }
        } elseif ($answer['question_type'] == 'text') {
            // Store text responses for analysis
            if (isset($questionAnalytics[$answer['question_id']])) {
                $questionAnalytics[$answer['question_id']]['responses']++;
                $questionAnalytics[$answer['question_id']]['text_responses'][] = $answer['answer_value'];
            }
        }
    }
    $stmt->close();

    // Add response rating to monthly trends
    if ($responseRatingCount > 0) {
        $monthlyTrends[$month]['total'] += $responseRatingTotal;
        $monthlyTrends[$month]['count']++;
    }
}

if ($ratingCount > 0) {
    $avgSatisfaction = round($avgSatisfaction / $ratingCount, 1);
} else {
    $avgSatisfaction = 'N/A';
}

// Calculate monthly averages
$monthlyAverages = [];
foreach ($monthlyTrends as $month => $data) {
    if ($data['count'] > 0) {
        $monthlyAverages[$month] = round($data['total'] / $data['count'], 1);
    }
}

// Find top and lowest branch
$topBranch = 'N/A';
$lowestBranch = 'N/A';
$topScore = -1;
$lowestScore = 6;

foreach ($branchScores as $branch => $data) {
    if ($data['count'] > 0) {
        $score = round($data['total'] / $data['count'], 1);
        if ($score > $topScore) {
            $topScore = $score;
            $topBranch = $branch;
        }
        if ($score < $lowestScore) {
            $lowestScore = $score;
            $lowestBranch = $branch;
        }
    }
}

// Calculate satisfaction levels
$satisfactionLevels = [
    'Very Dissatisfied' => $ratingDistribution[1] + $ratingDistribution[2],
    'Dissatisfied' => $ratingDistribution[3],
    'Neutral' => $ratingDistribution[4],
    'Satisfied' => $ratingDistribution[5]
];

// Get surveys for filter and summary data
$surveysQuery = "SELECT id, survey_title FROM surveys ORDER BY survey_title";
$surveysResult = $conn->query($surveysQuery);
$surveys = [];
$surveySummaries = [];

while ($row = $surveysResult->fetch_assoc()) {
    $surveys[] = $row;

    // Get summary data for each survey
    $surveyId = $row['id'];
    $surveyResponsesQuery = "SELECT COUNT(*) as response_count FROM survey_responses WHERE survey_id = ?";
    $stmt = $conn->prepare($surveyResponsesQuery);
    $stmt->bind_param("i", $surveyId);
    $stmt->execute();
    $responseResult = $stmt->get_result();
    $responseCount = $responseResult->fetch_assoc()['response_count'];
    $stmt->close();

    // Calculate average satisfaction for this survey
    $avgQuery = "
        SELECT AVG(sa.answer_value) as avg_rating
        FROM survey_answers sa
        JOIN survey_questions sq ON sa.question_id = sq.id
        WHERE sq.survey_id = ? AND sq.question_type = 'rating' AND sa.answer_value IS NOT NULL
    ";
    $stmt = $conn->prepare($avgQuery);
    $stmt->bind_param("i", $surveyId);
    $stmt->execute();
    $avgResult = $stmt->get_result();
    $avgRating = $avgResult->fetch_assoc()['avg_rating'];
    $stmt->close();

    $surveySummaries[] = [
        'id' => $surveyId,
        'title' => $row['survey_title'],
        'responses' => $responseCount,
        'avg_satisfaction' => $avgRating ? round($avgRating, 1) : 'N/A'
    ];
}

// Prepare data for charts
// Build response counts by submitted date (used for main chart)
$tempDateCounts = [];
foreach ($responses as $r) {
    $d = date('Y-m-d', strtotime($r['date_submitted']));
    if (!isset($tempDateCounts[$d])) {
        $tempDateCounts[$d] = 0;
    }
    $tempDateCounts[$d]++;
}
ksort($tempDateCounts);

$responseDateLabels = [];
$responseDateValues = [];
foreach ($tempDateCounts as $date => $count) {
    $responseDateLabels[] = date('M j, Y', strtotime($date));
    $responseDateValues[] = $count;
}

// If survey is selected, show question-specific analytics
if ($surveyFilter) {
    // Build question performance data
    $questionLabels = [];
    $questionAverages = [];
    $questionResponseCounts = [];
    
    foreach ($questionAnalytics as $analytics) {
        // Truncate long question text for chart display
        $displayText = strlen($analytics['question_text']) > 40 
            ? substr($analytics['question_text'], 0, 37) . '...' 
            : $analytics['question_text'];
        $questionLabels[] = $displayText;
        
        if ($analytics['question_type'] == 'rating' && $analytics['responses'] > 0) {
            $questionAverages[] = round($analytics['total_rating'] / $analytics['responses'], 1);
        } else {
            $questionAverages[] = 0;
        }
        $questionResponseCounts[] = $analytics['responses'];
    }
    
    $chartData = [
        // Replace pieChart with response-by-date for the visible chart
        'pieChart' => [
            'labels' => $responseDateLabels,
            'data' => $responseDateValues
        ],
    ];
} else {
    // Show overall survey analytics when no survey is selected
    $chartData = [
        // Replace pieChart with response-by-date for the visible chart
        'pieChart' => [
            'labels' => $responseDateLabels,
            'data' => $responseDateValues
        ],
    ];
}

$conn->close();

// Get survey title for export filename
$surveyTitle = '';
if ($surveyFilter && !empty($responses)) {
    $surveyTitle = $responses[0]['survey_title'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Survey System</title>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="survey-info">
                <div class="survey-logo">S</div>
                <div class="survey-details">
                    <h3>Survey</h3>
                    <p>Admin Panel</p>
                </div>
            </div>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">Dashboard</a>
            <a href="questions.php" class="nav-item">Questions</a>
            <a href="responses.php" class="nav-item">Responses</a>
            <a href="reports.php" class="nav-item active">Reports</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>Reports</h1>
            <div class="user-profile">
                <div class="user-avatar">
                    <img src="icons/User_01.png" alt="User" class="icon">
                </div>
                <div class="user-info">
                    <h4>Admin User</h4>
                    <p>Administrator</p>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            <!-- Filters -->
            <form method="GET" action="reports.php" class="filter-section">
                <div class="filter-grid">
                    <div class="filter-item">
                        <label>Select Survey</label>
                        <select name="survey_id">
                            <option value="">All Surveys</option>
                            <?php foreach ($surveys as $survey): ?>
                                <option value="<?php echo $survey['id']; ?>" <?php echo ($surveyFilter == $survey['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($survey['survey_title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="filter-item">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="reports.php" class="btn btn-secondary">Clear Filters</a>
                </div>
                <div class="export-buttons">
                    <button type="button" class="btn btn-secondary" id="export-excel">Export as Excel</button>
                    <button type="button" class="btn btn-secondary" id="export-pdf">Export as PDF</button>
                </div>
            </form>

            <!-- Summary Cards -->
            <?php if ($surveyFilter): ?>
            <div class="survey-summary" style="margin-bottom: 2rem; padding: 1rem; background-color: #f0f4f8; border-radius: 8px; border-left: 4px solid #3b82f6;">
                <h2 style="margin-top: 0; color: #1e40af;">Survey-Specific Analytics</h2>
                <p><strong>Respondents for this survey:</strong> <?php echo $totalResponses; ?></p>
            </div>
            <?php endif; ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Responses</h3>
                    <div class="stat-value"><?php echo $totalResponses; ?></div>
                </div>
                <div class="stat-card highlight">
                    <h3>Avg. Satisfaction</h3>
                    <div class="stat-value"><?php echo $avgSatisfaction; ?>/5</div>
                </div>
                <div class="stat-card">
                    <h3>Completion Rate</h3>
                    <div class="stat-value"><?php echo $completionRate; ?>%</div>
                </div>
                
            </div>

            <!-- Charts: only show Responses Over Time (line) -->
            <div class="charts-grid">
                <?php if (!empty($responseDateLabels)): ?>
                <div class="chart-card">
                    <h3>Responses Over Time</h3>
                    <canvas id="responses-line-chart"></canvas>
                </div>
                <?php else: ?>
                <div class="chart-card">
                    <h3>Responses Over Time</h3>
                    <p>No responses to display yet.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Question Analytics (shown when a specific survey is selected) -->
            <?php if ($surveyFilter && !empty($questionAnalytics)): ?>
            <div class="question-analytics-section">
                <h2>Question-by-Question Analytics</h2>
                <?php foreach ($questionAnalytics as $questionId => $analytics): ?>
                <div class="question-card">
                    <h3><?php echo htmlspecialchars($analytics['question_text']); ?></h3>
                    <div class="question-stats">
                        <div class="stat-item">
                            <span class="stat-label">Responses:</span>
                            <span class="stat-value"><?php echo $analytics['responses']; ?></span>
                        </div>
                        <?php if ($analytics['question_type'] == 'rating' && $analytics['responses'] > 0): ?>
                        <div class="stat-item">
                            <span class="stat-label">Average Rating:</span>
                            <span class="stat-value"><?php echo round($analytics['total_rating'] / $analytics['responses'], 1); ?>/5</span>
                        </div>
                        <div class="rating-distribution">
                            <h4>Rating Distribution:</h4>
                            <div class="rating-bars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-bar">
                                    <span class="rating-label"><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>:</span>
                                    <div class="bar-container">
                                        <div class="bar-fill" style="width: <?php echo $analytics['responses'] > 0 ? ($analytics['rating_distribution'][$i] / $analytics['responses']) * 100 : 0; ?>%"></div>
                                    </div>
                                    <span class="rating-count"><?php echo $analytics['rating_distribution'][$i]; ?></span>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php elseif ($analytics['question_type'] == 'text'): ?>
                        <div class="text-responses">
                            <h4>Sample Responses:</h4>
                            <ul>
                                <?php
                                $sampleResponses = array_slice($analytics['text_responses'], 0, 5); // Show first 5 responses
                                foreach ($sampleResponses as $response): ?>
                                <li><?php echo htmlspecialchars($response); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (count($analytics['text_responses']) > 5): ?>
                            <p><em>... and <?php echo count($analytics['text_responses']) - 5; ?> more responses</em></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
    <script>
        // Pass PHP data to JavaScript
        const chartData = <?php echo json_encode($chartData); ?>;
        const surveyFilter = <?php echo json_encode($surveyFilter); ?>;
        const questionAnalytics = <?php echo json_encode($questionAnalytics); ?>;
        const surveyTitle = <?php echo json_encode($surveyTitle); ?>;
        const surveySummaries = <?php echo json_encode($surveySummaries); ?>;
    </script>
    <script src="reports.js"></script>
</body>
</html>
