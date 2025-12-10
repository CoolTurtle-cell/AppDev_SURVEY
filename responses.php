<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin.html");
    exit();
}

// Handle AJAX request for response details
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'get_response_details') {
        $responseId = intval($_POST['response_id']);
        
        // Get response info
        $responseQuery = "SELECT sr.*, s.survey_title 
                         FROM survey_responses sr 
                         JOIN surveys s ON sr.survey_id = s.id 
                         WHERE sr.id = ?";
        $stmt = $conn->prepare($responseQuery);
        $stmt->bind_param("i", $responseId);
        $stmt->execute();
        $response = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$response) {
            echo json_encode(['success' => false, 'message' => 'Response not found']);
            exit();
        }
        
        // Get all answers with questions
        $answersQuery = "SELECT sa.*, sq.question_text, sq.question_type, so.option_text
                        FROM survey_answers sa
                        JOIN survey_questions sq ON sa.question_id = sq.id
                        LEFT JOIN survey_options so ON sa.answer_value = so.id
                        WHERE sa.response_id = ?
                        ORDER BY sq.order_num";
        $stmt = $conn->prepare($answersQuery);
        $stmt->bind_param("i", $responseId);
        $stmt->execute();
        $answersResult = $stmt->get_result();
        
        $answers = [];
        while ($row = $answersResult->fetch_assoc()) {
            $answers[] = $row;
        }
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'response' => $response,
            'answers' => $answers
        ]);
        exit();
    }
}

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$surveyFilter = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : '';

// Build query with filters
$query = "SELECT sr.*, s.survey_title 
          FROM survey_responses sr 
          JOIN surveys s ON sr.survey_id = s.id 
          WHERE 1=1";

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

// Get all surveys for filter
$surveysQuery = "SELECT id, survey_title FROM surveys ORDER BY survey_title";
$surveysResult = $conn->query($surveysQuery);
$surveys = [];
while ($row = $surveysResult->fetch_assoc()) {
    $surveys[] = $row;
}

// Calculate statistics
$totalResponses = count($responses);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responses - Survey System</title>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-item label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 5px;
        }
        .filter-item input,
        .filter-item select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }
        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            flex: 1;
            min-width: 150px;
        }
        .stat-card h4 {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 5px;
        }
        .stat-card p {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        .response-table {
            width: 100%;
            border-collapse: collapse;
        }
        .response-table th {
            text-align: left;
            padding: 12px;
            background: #f8fafc;
            font-weight: 600;
            color: #334155;
            border-bottom: 2px solid #e2e8f0;
        }
        .response-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }
        .response-table tr:hover {
            background: #f8fafc;
        }
        .view-btn {
            background: #0080aa;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .view-btn:hover {
            background: #006a8f;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal-box-large {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .modal-header h2 {
            font-size: 22px;
            color: #1e293b;
        }
        .response-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .info-item {
            font-size: 14px;
        }
        .info-item strong {
            color: #64748b;
            display: block;
            margin-bottom: 3px;
        }
        .answer-item {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #0080aa;
        }
        .answer-question {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .answer-text {
            color: #475569;
        }
        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
    </style>
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
            <a href="surveys.php" class="nav-item">Surveys</a>
            <a href="user_management.php" class="nav-item">Users</a>
            <a href="responses.php" class="nav-item active">Responses</a>
            <a href="reports.php" class="nav-item">Reports</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>Responses</h1>
            <div class="user-profile">
                <div class="user-avatar">
                    <img src="icons/User_01.png" alt="User" class="icon">
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h4>
                    <p><?php echo htmlspecialchars($_SESSION['account_type']); ?></p>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            <!-- Statistics -->
            <div class="stats-bar">
                <div class="stat-card">
                    <h4>Total Responses</h4>
                    <p><?php echo $totalResponses; ?></p>
                </div>
                <div class="stat-card">
                    <h4>Active Surveys</h4>
                    <p><?php echo count($surveys); ?></p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" action="responses.php">
                    <div class="filter-grid">
                        <div class="filter-item">
                            <label>Date From</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="filter-item">
                            <label>Date To</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <div class="filter-item">
                            <label>Survey</label>
                            <select name="survey_id">
                                <option value="">All Surveys</option>
                                <?php foreach ($surveys as $survey): ?>
                                    <option value="<?php echo $survey['id']; ?>" <?php echo $surveyFilter == $survey['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($survey['survey_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                        </div>
                        <div class="filter-item">
                            <a href="responses.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Responses Table -->
            <div class="table-container">
                <?php if (empty($responses)): ?>
                    <div class="empty-state">
                        <p style="font-size: 18px; font-weight: 600;">No responses found</p>
                        <p>Try adjusting your filters or check back later.</p>
                    </div>
                <?php else: ?>
                    <table class="response-table">
                        <thead>
                            <tr>
                                <th>Response ID</th>
                                <th>Survey</th>
                                <th>Date Submitted</th>
                                <th>Respondent</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($responses as $response): ?>
                                <tr>
                                    <td>R-<?php echo str_pad($response['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($response['survey_title']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($response['date_submitted'])); ?></td>
                                    <td><?php echo htmlspecialchars($response['respondent_name'] ?: 'Anonymous'); ?></td>
                                    <td>
                                        <button class="view-btn" onclick="viewResponse(<?php echo $response['id']; ?>)">
                                            👁 View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Response Details Modal -->
    <div class="modal-overlay" id="responseModal">
        <div class="modal-box-large">
            <div class="modal-header">
                <h2 id="modalTitle">Response Details</h2>
            </div>
            <div id="modalContent">
                <p style="text-align: center; color: #94a3b8;">Loading...</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function viewResponse(responseId) {
            document.getElementById('responseModal').classList.add('show');
            document.getElementById('modalContent').innerHTML = '<p style="text-align: center; color: #94a3b8;">Loading...</p>';
            
            const formData = new FormData();
            formData.append('action', 'get_response_details');
            formData.append('response_id', responseId);
            
            fetch('responses.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayResponseDetails(data.response, data.answers);
                } else {
                    document.getElementById('modalContent').innerHTML = 
                        '<p style="text-align: center; color: #ef4444;">Error loading response</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('modalContent').innerHTML = 
                    '<p style="text-align: center; color: #ef4444;">Error loading response</p>';
            });
        }

        function displayResponseDetails(response, answers) {
            const infoHtml = `
                <div class="response-info">
                    <div class="info-item">
                        <strong>Response ID</strong>
                        R-${String(response.id).padStart(4, '0')}
                    </div>
                    <div class="info-item">
                        <strong>Survey</strong>
                        ${response.survey_title}
                    </div>
                    <div class="info-item">
                        <strong>Respondent</strong>
                        ${response.respondent_name || 'Anonymous'}
                    </div>
                    <div class="info-item">
                        <strong>Email</strong>
                        ${response.email || 'N/A'}
                    </div>
                    <div class="info-item">
                        <strong>Date Submitted</strong>
                        ${new Date(response.date_submitted).toLocaleString()}
                    </div>
                </div>
            `;
            
            let answersHtml = '<h3 style="margin-bottom: 15px; color: #1e293b;">Responses</h3>';
            
            if (answers.length === 0) {
                answersHtml += '<p style="color: #94a3b8;">No answers recorded</p>';
            } else {
                answers.forEach((answer, index) => {
                    let answerDisplay = '';
                    
                    if (answer.question_type === 'rating') {
                        answerDisplay = answer.answer_value ? `Rating: ${answer.answer_value}/5` : 'No rating';
                    } else if (answer.question_type === 'text') {
                        answerDisplay = answer.answer_text || 'No response';
                    } else if (answer.option_text) {
                        answerDisplay = answer.option_text;
                    } else {
                        answerDisplay = answer.answer_text || 'No response';
                    }
                    
                    answersHtml += `
                        <div class="answer-item">
                            <div class="answer-question">Q${index + 1}. ${answer.question_text}</div>
                            <div class="answer-text">${answerDisplay}</div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('modalContent').innerHTML = infoHtml + answersHtml;
        }

        function closeModal() {
            document.getElementById('responseModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('responseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>