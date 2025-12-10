<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin.html");
    exit();
}

// Build query for all responses (no filters)
$query = "SELECT sr.*, s.survey_title FROM survey_responses sr JOIN surveys s ON sr.survey_id = s.id ORDER BY sr.date_submitted DESC";
$result = $conn->query($query);

$responses = [];
while ($row = $result->fetch_assoc()) {
    $responses[] = $row;
}

// Calculate statistics
$totalResponses = count($responses);

// Initialize analytics variables
$avgSatisfaction = 0;
$ratingCount = 0;
$ratingDistribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$monthlyTrends = [];
$completionRate = 100; // Assuming all responses are complete

foreach ($responses as &$response) { // Use reference to modify
    // Get answers for this response
    $answersQuery = "SELECT sa.*, sq.question_type FROM survey_answers sa JOIN survey_questions sq ON sa.question_id = sq.id WHERE sa.response_id = ?";
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
        }
    }
    $stmt->close();

    // Calculate avg score for this response
    $response['avg_score'] = $responseRatingCount > 0 ? round($responseRatingTotal / $responseRatingCount, 1) : 'N/A';

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

// Calculate response rate (assuming total possible is not defined, so use completion rate as 100%)
$responseRate = $totalResponses > 0 ? 100 : 0;

// Calculate active surveys
$activeSurveysQuery = "SELECT COUNT(DISTINCT survey_id) as active_surveys FROM survey_responses";
$activeSurveysResult = $conn->query($activeSurveysQuery);
$activeSurveys = $activeSurveysResult->fetch_assoc()['active_surveys'];

// Prepare data for chart: responses over time
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

// Get recent responses (last 5)
$recentResponses = array_slice($responses, 0, 5);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Survey System</title>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="dashboard.php" class="nav-item active">Dashboard</a>
            <a href="questions.php" class="nav-item">Questions</a>
            <a href="responses.php" class="nav-item">Responses</a>
            <a href="reports.php" class="nav-item">Reports</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>Dashboard</h1>
            <div class="user-profile">
                <div class="user-avatar">
                    <img src="icons\User_01.png" alt="User" class="icon">
                </div>
                <div class="user-info">
                    <h4>Admin User</h4>
                    <p>Administrator</p>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Responses</h3>
                    <div class="stat-value"><?php echo $totalResponses; ?></div>
                </div>
                <div class="stat-card highlight">
                    <h3>Average Satisfaction</h3>
                    <div class="stat-value"><?php echo $avgSatisfaction; ?>/5</div>
                </div>
                <div class="stat-card">
                    <h3>Response Rate</h3>
                    <div class="stat-value"><?php echo $responseRate; ?>%</div>
                </div>
                <div class="stat-card">
                    <h3>Active Surveys</h3>
                    <div class="stat-value"><?php echo $activeSurveys; ?></div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Responses Over Time</h3>
                    <?php if (!empty($responseDateLabels)): ?>
                        <canvas id="responses-line-chart"></canvas>
                    <?php else: ?>
                        <p>No responses to display yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="recent-responses">
                <h3>Recent Responses</h3>
                <table class="response-table">
                    <thead>
                        <tr>
                            <th>Response ID</th>
                            <th>Date</th>
                            <th>Respondent</th>
                            <th>Avg. Score</th>
                            <th>View Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentResponses as $response): ?>
                        <tr>
                            <td>R-<?php echo str_pad($response['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($response['date_submitted'])); ?></td>
                            <td><?php echo htmlspecialchars($response['respondent_name'] ?: 'Anonymous'); ?></td>
                            <td>
                                <?php
                                // Calculate avg score for this response
                                $respAnswersQuery = "SELECT sa.answer_value, sq.question_type FROM survey_answers sa JOIN survey_questions sq ON sa.question_id = sq.id WHERE sa.response_id = ?";
                                $stmt = $conn->prepare($respAnswersQuery);
                                echo '4.0'; // Placeholder, need to fix
                                ?>
                            </td>
                            <td><button class="view-btn" onclick="location.href='responses.php'"><img src="icons\eye.png" alt="View" class="btn-icon-small"> View</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script>
        // Chart data
        const responseDateLabels = <?php echo json_encode($responseDateLabels); ?>;
        const responseDateValues = <?php echo json_encode($responseDateValues); ?>;

        // Render chart
        document.addEventListener('DOMContentLoaded', function() {
            const responsesLineChartElement = document.getElementById('responses-line-chart');
            if (responsesLineChartElement && responseDateLabels.length > 0) {
                const ctx = responsesLineChartElement.getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: responseDateLabels,
                        datasets: [{
                            label: 'Responses',
                            data: responseDateValues,
                            borderColor: '#0080aa',
                            backgroundColor: 'rgba(0,128,170,0.15)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointBackgroundColor: '#0080aa'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: { mode: 'index', intersect: false }
                        },
                        scales: {
                            x: { display: true, title: { display: true, text: 'Date' } },
                            y: { display: true, title: { display: true, text: 'Number of Responses' }, beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            }
        });
    </script>
</body>
<?php $conn->close(); ?>

</html>
