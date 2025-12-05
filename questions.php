<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin.html");
    exit();
}

// Get survey ID from URL or default to first survey
$surveyId = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : null;

// If no survey_id, redirect to surveys page
if (!$surveyId) {
    header("Location: surveys.php");
    exit();
}

// Get survey details
$surveyQuery = "SELECT * FROM surveys WHERE id = ?";
$surveyStmt = $conn->prepare($surveyQuery);
$surveyStmt->bind_param("i", $surveyId);
$surveyStmt->execute();
$surveyResult = $surveyStmt->get_result();
$currentSurvey = $surveyResult->fetch_assoc();
$surveyStmt->close();

if (!$currentSurvey) {
    header("Location: surveys.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'add_question') {
        $questionText = trim($_POST['question_text']);
        $questionType = $_POST['question_type'];
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $surveyIdPost = intval($_POST['survey_id']);
        
        // Get next order number for this survey
        $orderQuery = "SELECT MAX(order_num) as max_order FROM survey_questions WHERE survey_id = ?";
        $orderStmt = $conn->prepare($orderQuery);
        $orderStmt->bind_param("i", $surveyIdPost);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        $orderRow = $orderResult->fetch_assoc();
        $orderNum = ($orderRow['max_order'] ?? 0) + 1;
        $orderStmt->close();
        
        $stmt = $conn->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type, is_required, order_num, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("issii", $surveyIdPost, $questionText, $questionType, $isRequired, $orderNum);
        
        if ($stmt->execute()) {
            $questionId = $stmt->insert_id;
            
            // Add options if provided
            if (isset($_POST['options']) && !empty($_POST['options'])) {
                $options = json_decode($_POST['options'], true);
                $optionStmt = $conn->prepare("INSERT INTO survey_options (question_id, option_text, option_value, order_num) VALUES (?, ?, ?, ?)");
                
                foreach ($options as $index => $option) {
                    $optionValue = strtolower(str_replace(' ', '_', $option));
                    $optionStmt->bind_param("issi", $questionId, $option, $optionValue, $index);
                    $optionStmt->execute();
                }
                $optionStmt->close();
            }
            
            echo json_encode(['success' => true, 'message' => 'Question added successfully', 'question_id' => $questionId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add question']);
        }
        $stmt->close();
    }
    elseif ($action === 'delete_question') {
        $questionId = intval($_POST['question_id']);
        $stmt = $conn->prepare("DELETE FROM survey_questions WHERE id = ?");
        $stmt->bind_param("i", $questionId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete question']);
        }
        $stmt->close();
    }
    elseif ($action === 'duplicate_question') {
        $questionId = intval($_POST['question_id']);
        
        // Get original question
        $stmt = $conn->prepare("SELECT * FROM survey_questions WHERE id = ?");
        $stmt->bind_param("i", $questionId);
        $stmt->execute();
        $question = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($question) {
            // Get next order number
            $orderQuery = "SELECT MAX(order_num) as max_order FROM survey_questions WHERE survey_id = ?";
            $orderStmt = $conn->prepare($orderQuery);
            $orderStmt->bind_param("i", $question['survey_id']);
            $orderStmt->execute();
            $orderResult = $orderStmt->get_result();
            $orderRow = $orderResult->fetch_assoc();
            $orderNum = ($orderRow['max_order'] ?? 0) + 1;
            $orderStmt->close();
            
            // Insert duplicate
            $stmt = $conn->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type, is_required, order_num, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issiis", $question['survey_id'], $question['question_text'], $question['question_type'], $question['is_required'], $orderNum, $question['status']);
            $stmt->execute();
            $newQuestionId = $stmt->insert_id;
            $stmt->close();
            
            // Copy options
            $optionsQuery = "SELECT * FROM survey_options WHERE question_id = ?";
            $optionsStmt = $conn->prepare($optionsQuery);
            $optionsStmt->bind_param("i", $questionId);
            $optionsStmt->execute();
            $optionsResult = $optionsStmt->get_result();
            
            if ($optionsResult->num_rows > 0) {
                $insertStmt = $conn->prepare("INSERT INTO survey_options (question_id, option_text, option_value, order_num) VALUES (?, ?, ?, ?)");
                while ($option = $optionsResult->fetch_assoc()) {
                    $insertStmt->bind_param("issi", $newQuestionId, $option['option_text'], $option['option_value'], $option['order_num']);
                    $insertStmt->execute();
                }
                $insertStmt->close();
            }
            $optionsStmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Question duplicated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Question not found']);
        }
    }
    
    $conn->close();
    exit();
}

// Fetch questions for this survey
$questions = [];
$query = "SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY order_num ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $surveyId);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Get options for this question
        $optionsQuery = "SELECT * FROM survey_options WHERE question_id = ? ORDER BY order_num ASC";
        $optionsStmt = $conn->prepare($optionsQuery);
        $optionsStmt->bind_param("i", $row['id']);
        $optionsStmt->execute();
        $optionsResult = $optionsStmt->get_result();
        
        $options = [];
        while ($option = $optionsResult->fetch_assoc()) {
            $options[] = $option;
        }
        $optionsStmt->close();
        
        $row['options'] = $options;
        $questions[] = $row;
    }
}
$stmt->close();

// Calculate statistics
$totalQuestions = count($questions);
$requiredCount = count(array_filter($questions, function($q) { return $q['is_required']; }));
$optionalCount = $totalQuestions - $requiredCount;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions - <?php echo htmlspecialchars($currentSurvey['survey_title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    
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
            <a href="dashboard.html" class="nav-item">Dashboard</a>
            <a href="surveys.php" class="nav-item">Surveys</a>
            <a href="user_management.php" class="nav-item">Users</a>
            <a href="questions.php?survey_id=<?php echo $surveyId; ?>" class="nav-item active">Questions</a>
            <a href="responses.php" class="nav-item">Responses</a>
            <a href="reports.html" class="nav-item">Reports</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>Questions</h1>
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

        <div class="questions-content">
            <div class="survey-header-info">
                <h2><?php echo htmlspecialchars($currentSurvey['survey_title']); ?></h2>
                <p><?php echo htmlspecialchars($currentSurvey['survey_description']); ?></p>
            </div>

            <div class="content-header">
                <button class="btn btn-primary" onclick="openAddModal()">
                    <img src="icons/File_Add.png" alt="Add" class="btn-icon"> Add Question
                </button>
                <div class="header-actions">
                    <a href="surveys.php" class="btn btn-secondary">
                        ← Back to Surveys
                    </a>
                    <a href="consent.php?survey_id=<?php echo $surveyId; ?>" target="_blank" class="btn btn-secondary">
                        <img src="icons/eye.png" alt="Preview" class="btn-icon"> Preview Survey
                    </a>
                </div>
            </div>

            <div class="questions-container" id="questionsContainer">
                <?php if (empty($questions)): ?>
                    <div class="question-card">
                        <p style="text-align: center; padding: 40px; color: #94a3b8;">
                            No questions yet. Click "Add Question" to create your first survey question.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($questions as $question): ?>
                        <div class="question-card" data-question-id="<?php echo $question['id']; ?>">
                            <div class="question-header">
                                <div class="question-number">Q<?php echo $question['order_num']; ?></div>
                                <div class="question-actions">
                                    <button class="icon-btn" title="Duplicate" onclick="duplicateQuestion(<?php echo $question['id']; ?>)">
                                        <img src="icons/Files.png" alt="Duplicate" class="action-icon">
                                    </button>
                                    <button class="icon-btn delete" title="Delete" onclick="deleteQuestion(<?php echo $question['id']; ?>)">
                                        <img src="icons/Trash_Empty.png" alt="Delete" class="action-icon">
                                    </button>
                                </div>
                            </div>
                            <div class="question-body">
                                <h3 class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></h3>
                                <div class="question-meta">
                                    <span class="question-type"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></span>
                                    <span class="question-status <?php echo $question['is_required'] ? 'required' : 'optional'; ?>">
                                        <?php echo $question['is_required'] ? 'Required' : 'Optional'; ?>
                                    </span>
                                </div>
                                <?php if (!empty($question['options'])): ?>
                                    <div class="question-options">
                                        <?php foreach ($question['options'] as $option): ?>
                                            <div class="option-preview"><?php echo htmlspecialchars($option['option_text']); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="questions-summary">
                <div class="summary-info">
                    <strong>Total Questions:</strong> <?php echo $totalQuestions; ?> | 
                    <strong>Required:</strong> <?php echo $requiredCount; ?> | 
                    <strong>Optional:</strong> <?php echo $optionalCount; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Question Modal -->
    <div class="add-question-modal" id="addQuestionModal">
        <div class="modal-box-large">
            <div class="modal-header">
                <h3>Add New Question</h3>
            </div>
            <div class="modal-body">
                <form id="addQuestionForm">
                    <input type="hidden" id="surveyId" value="<?php echo $surveyId; ?>">
                    
                    <div class="form-group">
                        <label>Question Text *</label>
                        <textarea id="questionText" required placeholder="Enter your question here..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Question Type *</label>
                        <select id="questionType" required onchange="toggleOptions()">
                            <option value="">Select question type</option>
                            <option value="rating">Rating Scale (1-5)</option>
                            <option value="text">Text Response</option>
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="checkbox">Checkbox (Multiple Select)</option>
                            <option value="yes_no">Yes/No</option>
                        </select>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="isRequired" checked>
                        <label for="isRequired" style="margin-bottom: 0;">This question is required</label>
                    </div>

                    <div class="options-container" id="optionsContainer">
                        <label>Options</label>
                        <div id="optionsList">
                            <div class="option-input-group">
                                <input type="text" class="option-input" placeholder="Option 1">
                            </div>
                        </div>
                        <button type="button" class="add-option-btn" onclick="addOption()">+ Add Option</button>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeAddModal()">Cancel</button>
                        <button type="submit" class="modal-btn modal-btn-save">Add Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let optionCount = 1;

        function openAddModal() {
            document.getElementById('addQuestionModal').classList.add('show');
        }

        function closeAddModal() {
            document.getElementById('addQuestionModal').classList.remove('show');
            document.getElementById('addQuestionForm').reset();
            optionCount = 1;
            document.getElementById('optionsList').innerHTML = '<div class="option-input-group"><input type="text" class="option-input" placeholder="Option 1"></div>';
            toggleOptions();
        }

        function toggleOptions() {
            const type = document.getElementById('questionType').value;
            const container = document.getElementById('optionsContainer');
            
            if (type === 'multiple_choice' || type === 'checkbox' || type === 'yes_no') {
                container.classList.add('show');
                
                // Pre-fill for yes/no
                if (type === 'yes_no') {
                    document.getElementById('optionsList').innerHTML = `
                        <div class="option-input-group">
                            <input type="text" class="option-input" value="Yes" readonly>
                        </div>
                        <div class="option-input-group">
                            <input type="text" class="option-input" value="No" readonly>
                        </div>
                    `;
                }
            } else {
                container.classList.remove('show');
            }
        }

        function addOption() {
            optionCount++;
            const optionHtml = `
                <div class="option-input-group">
                    <input type="text" class="option-input" placeholder="Option ${optionCount}">
                    <button type="button" class="remove-option-btn" onclick="removeOption(this)">Remove</button>
                </div>
            `;
            document.getElementById('optionsList').insertAdjacentHTML('beforeend', optionHtml);
        }

        function removeOption(btn) {
            btn.closest('.option-input-group').remove();
        }

        document.getElementById('addQuestionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const questionText = document.getElementById('questionText').value;
            const questionType = document.getElementById('questionType').value;
            const isRequired = document.getElementById('isRequired').checked ? 1 : 0;
            const surveyId = document.getElementById('surveyId').value;
            
            // Get options
            const optionInputs = document.querySelectorAll('.option-input');
            const options = [];
            optionInputs.forEach(input => {
                if (input.value.trim()) {
                    options.push(input.value.trim());
                }
            });
            
            // Send to server
            const formData = new FormData();
            formData.append('action', 'add_question');
            formData.append('survey_id', surveyId);
            formData.append('question_text', questionText);
            formData.append('question_type', questionType);
            formData.append('is_required', isRequired);
            formData.append('options', JSON.stringify(options));
            
            fetch('questions.php?survey_id=' + surveyId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        });

        function deleteQuestion(id) {
            if (!confirm('Are you sure you want to delete this question?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_question');
            formData.append('question_id', id);
            
            fetch('questions.php?survey_id=<?php echo $surveyId; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    document.querySelector(`[data-question-id="${id}"]`).remove();
                    location.reload();
                }
            });
        }

        function duplicateQuestion(id) {
            const formData = new FormData();
            formData.append('action', 'duplicate_question');
            formData.append('question_id', id);
            
            fetch('questions.php?survey_id=<?php echo $surveyId; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            });
        }
    </script>
</body>
</html>