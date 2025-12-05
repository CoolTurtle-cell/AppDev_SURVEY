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
$conn->close();

if (!$survey) {
    die("Survey not found or inactive.");
}

// If user already gave consent for THIS survey in this session, redirect to survey
if (isset($_SESSION['consent_given_' . $surveyId]) && $_SESSION['consent_given_' . $surveyId] === true) {
    header("Location: mainsurvey.php?survey_id=" . $surveyId);
    exit();
}

// Handle consent submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['consent'])) {
    $_SESSION['consent_given_' . $surveyId] = true;
    $_SESSION['consent_timestamp_' . $surveyId] = time();
    $_SESSION['consent_ip'] = $_SERVER['REMOTE_ADDR'];
    
    header("Location: mainsurvey.php?survey_id=" . $surveyId);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consent Form - <?php echo htmlspecialchars($survey['survey_title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    
</head>
<body class="survey-page">
    <div class="survey-container">
        <div class="survey-header-card">
            <h1>Consent Form</h1>
            <p>Please read the information below before participating in: <strong><?php echo htmlspecialchars($survey['survey_title']); ?></strong></p>
        </div>

        <form method="POST" action="consent.php?survey_id=<?php echo $surveyId; ?>" id="consentForm">
            <div class="survey-question-card">
                <div class="question-label">
                    <h3>Participant Information & Consent</h3>
                </div>
                <div class="consent-text">
                    <p>
                        <strong>Purpose:</strong> You are invited to participate in this survey. 
                        Your responses will be used solely for service improvement and research evaluation.
                    </p>
                    <p>
                        <strong>Voluntary Participation:</strong> Participation is voluntary. You may decline to 
                        answer any question or withdraw at any time without penalty.
                    </p>
                    <p>
                        <strong>Confidentiality:</strong> All responses will remain confidential. Your personal 
                        information will be stored securely and only accessible to authorized personnel.
                    </p>
                    <p>
                        <strong>Data Usage:</strong> Your responses will be analyzed in aggregate form to improve 
                        our services. Individual responses will not be shared with third parties.
                    </p>
                    <p>
                        <strong>Your Rights:</strong> You have the right to request access to your data, request 
                        deletion, or withdraw consent at any time by contacting our support team.
                    </p>
                    <p style="margin-top: 25px;">
                        By checking the box below, you acknowledge that you have read and understood the 
                        information provided and consent to participate in this survey.
                    </p>
                </div>
                <label class="consent-checkbox" id="consentLabel">
                    <input type="checkbox" id="agreeCheckbox" name="consent" value="1" required>
                    <span>I agree to participate in this survey and consent to the collection and use of my responses as described above.</span>
                </label>
            </div>

            <div class="survey-actions">
                <button type="submit" class="submit-survey-btn">Continue to Survey</button>
            </div>
        </form>
    </div>

    <script>
        const checkbox = document.getElementById('agreeCheckbox');
        const label = document.getElementById('consentLabel');
        
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                label.classList.add('checked');
            } else {
                label.classList.remove('checked');
            }
        });

        document.getElementById('consentForm').addEventListener('submit', function(e) {
            if (!checkbox.checked) {
                e.preventDefault();
                alert("Please agree to the consent form before continuing.");
                checkbox.focus();
            }
        });
    </script>
</body>
</html>