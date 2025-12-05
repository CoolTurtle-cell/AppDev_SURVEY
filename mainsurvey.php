<?php
session_start();
require 'db_connect.php';

// Get survey ID from URL
$surveyId = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : 1;

// Get survey details
$surveyQuery = "SELECT * FROM surveys WHERE id = ? AND status = 'active'";
$surveyStmt = $conn->prepare($surveyQuery);
$surveyStmt->bind_param("i", $surveyId);
$surveyStmt->execute();
$surveyResult = $surveyStmt->get_result();
$survey = $surveyResult->fetch_assoc();
$surveyStmt->close();

if (!$survey) {
    die("Survey not found or inactive.");
}

// ========== IMPORTANT: Check for success BEFORE consent check ==========
// Show thank you message if survey was just submitted
if (isset($_GET['success']) && isset($_SESSION['survey_submitted_' . $surveyId])) {
    unset($_SESSION['survey_submitted_' . $surveyId]);
    $conn->close();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Thank You - <?php echo htmlspecialchars($survey['survey_title']); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="styles.css">

    </head>
    <body class="survey-page">
        <div class="thank-you-container">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            
            <h1 class="thank-you-title">Response Submitted!</h1>
            
            <p class="thank-you-message">
                Your response has been recorded successfully.
                <span class="survey-name"><?php echo htmlspecialchars($survey['survey_title']); ?></span>
            </p>
            
            <div class="response-recorded">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Response Recorded
            </div>
            
            <div class="divider"></div>
            
            <div class="actions-section">
                <h3>What would you like to do next?</h3>
                
                <div class="action-buttons">
                    <a href="consent.php?survey_id=<?php echo $surveyId; ?>" class="btn-action btn-primary-action">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7"></path>
                            <line x1="16" y1="5" x2="16" y2="11"></line>
                            <line x1="13" y1="8" x2="19" y2="8"></line>
                        </svg>
                        Submit Another Response
                    </a>
                </div>
                
                <p class="footer-note">
                    Your feedback helps us improve. Thank you for taking the time to share your thoughts!
                </p>
            </div>
        </div>
        
        <script>
            // Confetti celebration effect
            function createConfetti() {
                const colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', '#43e97b', '#38f9d7'];
                const confettiCount = 80;
                
                for (let i = 0; i < confettiCount; i++) {
                    setTimeout(() => {
                        const confetti = document.createElement('div');
                        confetti.className = 'confetti';
                        confetti.style.width = Math.random() * 10 + 5 + 'px';
                        confetti.style.height = confetti.style.width;
                        confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                        confetti.style.left = Math.random() * 100 + '%';
                        confetti.style.top = '-10px';
                        confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                        confetti.style.opacity = Math.random() * 0.8 + 0.2;
                        confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                        confetti.style.animationDelay = (Math.random() * 0.5) + 's';
                        document.body.appendChild(confetti);
                        
                        setTimeout(() => confetti.remove(), 5000);
                    }, Math.random() * 500);
                }
            }
            
            // Trigger confetti on page load
            window.addEventListener('load', () => {
                createConfetti();
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// ========== NOW check if consent was given (AFTER success check) ==========
if (!isset($_SESSION['consent_given_' . $surveyId]) || $_SESSION['consent_given_' . $surveyId] !== true) {
    header("Location: consent.php?survey_id=" . $surveyId);
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_survey'])) {
    $respondentName = !empty($_POST['user_name']) ? trim($_POST['user_name']) : 'Anonymous';
    $email = !empty($_POST['user_email']) ? trim($_POST['user_email']) : null;
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    // Calculate average score from rating questions
    $ratingSum = 0;
    $ratingCount = 0;
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'question_') === 0 && is_numeric($value)) {
            $ratingSum += intval($value);
            $ratingCount++;
        }
    }
    
    $avgScore = $ratingCount > 0 ? round($ratingSum / $ratingCount, 2) : null;
    
    // Insert main response
    $stmt = $conn->prepare("INSERT INTO survey_responses (survey_id, respondent_name, email, avg_score, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issds", $surveyId, $respondentName, $email, $avgScore, $ipAddress);
    
    if ($stmt->execute()) {
        $responseId = $stmt->insert_id;
        $stmt->close();
        
        // Insert consent log
        $consentStmt = $conn->prepare("INSERT INTO consent_logs (response_id, consent_given, ip_address) VALUES (?, 1, ?)");
        $consentStmt->bind_param("is", $responseId, $ipAddress);
        $consentStmt->execute();
        $consentStmt->close();
        
        // Insert individual answers
        $answerStmt = $conn->prepare("INSERT INTO survey_answers (response_id, question_id, answer_text, answer_value) VALUES (?, ?, ?, ?)");
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $questionId = str_replace('question_', '', $key);
                
                // Handle checkbox arrays
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                
                $answerValue = is_numeric($value) ? intval($value) : null;
                $answerStmt->bind_param("iisi", $responseId, $questionId, $value, $answerValue);
                $answerStmt->execute();
            }
        }
        $answerStmt->close();
        
        // Set survey submitted flag BEFORE clearing consent
        $_SESSION['survey_submitted_' . $surveyId] = true;
        
        // Clear consent session for this survey
        unset($_SESSION['consent_given_' . $surveyId]);
        unset($_SESSION['consent_timestamp_' . $surveyId]);
        
        // Redirect to thank you page
        $conn->close();
        header("Location: mainsurvey.php?survey_id=" . $surveyId . "&success=1");
        exit();
    } else {
        $error = "Failed to submit survey. Please try again.";
    }
}

// Fetch all active questions for this survey
$questions = [];
$query = "SELECT * FROM survey_questions WHERE survey_id = ? AND status = 'active' ORDER BY order_num ASC";
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($survey['survey_title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="survey-page">
    <div class="survey-container">
        <div class="survey-header-card">
            <h1><?php echo htmlspecialchars($survey['survey_title']); ?></h1>
            <p><?php echo htmlspecialchars($survey['survey_description']); ?></p>
        </div>

        <?php if (isset($error)): ?>
            <div class="survey-question-card" style="background: #fee2e2; border: 2px solid #fca5a5;">
                <p style="color: #dc2626; text-align: center; padding: 20px; margin: 0;">
                    <?php echo htmlspecialchars($error); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (empty($questions)): ?>
            <div class="survey-question-card">
                <p style="text-align: center; padding: 40px; color: #94a3b8;">
                    No survey questions available at this time. Please check back later.
                </p>
            </div>
        <?php else: ?>
            <form class="survey-form" id="surveyForm" method="POST" action="mainsurvey.php?survey_id=<?php echo $surveyId; ?>">

                <!-- User Information Section -->
                <div class="user-info-section">
                    <h2>Your Information</h2>
                    <p>These details are optional and help us improve our service.</p>

                    <div class="user-input-group">
                        <label>Name <span class="optional-tag">Optional</span></label>
                        <input type="text" name="user_name" placeholder="Enter your name (optional)">
                    </div>

                    <div class="user-input-group">
                        <label>Email <span class="optional-tag">Optional</span></label>
                        <input type="email" name="user_email" placeholder="Enter your email (optional)">
                    </div>
                </div>

                <!-- Dynamic Questions -->
                <?php foreach ($questions as $index => $question): ?>
                    <div class="survey-question-card">
                        <div class="question-label">
                            <span class="question-num">Q<?php echo $index + 1; ?></span>
                            <h3><?php echo htmlspecialchars($question['question_text']); ?></h3>
                            <span class="<?php echo $question['is_required'] ? 'required-badge' : 'optional-badge'; ?>">
                                <?php echo $question['is_required'] ? 'Required' : 'Optional'; ?>
                            </span>
                        </div>

                        <?php if ($question['question_type'] === 'rating'): ?>
                            <div class="rating-options">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="rating-option">
                                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="<?php echo $i; ?>" <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                        <span class="rating-label"><?php echo $i; ?> - <?php 
                                            $labels = ['Very Dissatisfied', 'Dissatisfied', 'Neutral', 'Satisfied', 'Very Satisfied'];
                                            echo $labels[$i-1];
                                        ?></span>
                                    </label>
                                <?php endfor; ?>
                            </div>

                        <?php elseif ($question['question_type'] === 'text'): ?>
                            <textarea class="survey-textarea" name="question_<?php echo $question['id']; ?>" placeholder="Type your answer here..." rows="5" <?php echo $question['is_required'] ? 'required' : ''; ?>></textarea>

                        <?php elseif ($question['question_type'] === 'multiple_choice'): ?>
                            <div class="choice-options">
                                <?php foreach ($question['options'] as $option): ?>
                                    <label class="choice-option">
                                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="<?php echo htmlspecialchars($option['option_text']); ?>" <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                        <span class="choice-label"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($question['question_type'] === 'checkbox'): ?>
                            <div class="choice-options">
                                <?php foreach ($question['options'] as $option): ?>
                                    <label class="choice-option">
                                        <input type="checkbox" name="question_<?php echo $question['id']; ?>[]" value="<?php echo htmlspecialchars($option['option_text']); ?>">
                                        <span class="choice-label"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($question['question_type'] === 'yes_no'): ?>
                            <div class="choice-options">
                                <?php foreach ($question['options'] as $option): ?>
                                    <label class="choice-option">
                                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="<?php echo htmlspecialchars($option['option_text']); ?>" <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                        <span class="choice-label"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="survey-actions">
                    <button type="submit" name="submit_survey" class="submit-survey-btn">Submit Survey</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('surveyForm').addEventListener('submit', function(e) {
            const checkboxGroups = document.querySelectorAll('.survey-question-card');
            let isValid = true;
            
            checkboxGroups.forEach(card => {
                const checkboxes = card.querySelectorAll('input[type="checkbox"]');
                if (checkboxes.length > 0) {
                    const required = card.querySelector('.required-badge');
                    if (required) {
                        const checked = Array.from(checkboxes).some(cb => cb.checked);
                        if (!checked) {
                            isValid = false;
                            alert('Please select at least one option for all required questions.');
                            e.preventDefault();
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>