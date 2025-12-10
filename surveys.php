<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin.html");
    exit();
}

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'create_survey') {
        $title = trim($_POST['survey_title']);
        $description = trim($_POST['survey_description']);
        $status = $_POST['status'];
        $adminId = $_SESSION['admin_id'];
        
        $stmt = $conn->prepare("INSERT INTO surveys (survey_title, survey_description, status, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $description, $status, $adminId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Survey created successfully', 'survey_id' => $stmt->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create survey']);
        }
        $stmt->close();
    }
    elseif ($action === 'update_survey') {
        $surveyId = intval($_POST['survey_id']);
        $title = trim($_POST['survey_title']);
        $description = trim($_POST['survey_description']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE surveys SET survey_title = ?, survey_description = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssi", $title, $description, $status, $surveyId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Survey updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update survey']);
        }
        $stmt->close();
    }
    elseif ($action === 'delete_survey') {
        $surveyId = intval($_POST['survey_id']);
        $stmt = $conn->prepare("DELETE FROM surveys WHERE id = ?");
        $stmt->bind_param("i", $surveyId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Survey deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete survey']);
        }
        $stmt->close();
    }
    
    $conn->close();
    exit();
}

// Get the base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . "://" . $host . dirname($_SERVER['PHP_SELF']);

// Fetch all surveys
$surveys = [];
$query = "SELECT s.*, a.Name as creator_name, 
          (SELECT COUNT(*) FROM survey_questions WHERE survey_id = s.id) as question_count,
          (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id) as response_count
          FROM surveys s 
          LEFT JOIN admin a ON s.created_by = a.AdminID 
          ORDER BY s.created_at DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $surveys[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveys - Survey System</title>
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
            <a href="surveys.php" class="nav-item active">Surveys</a>
            <a href="user_management.php" class="nav-item">Users</a>
            <a href="responses.php" class="nav-item">Responses</a>
            <a href="reports.php" class="nav-item">Reports</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>Surveys</h1>
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
            <div class="content-header">
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <img src="icons/File_Add.png" alt="Add" class="btn-icon"> Create New Survey
                </button>
            </div>

            <div class="questions-container">
                <?php if (empty($surveys)): ?>
                    <div class="survey-card">
                        <p style="text-align: center; padding: 40px; color: #94a3b8;">
                            No surveys yet. Click "Create New Survey" to get started.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($surveys as $survey): ?>
                        <div class="survey-card">
                            <div class="survey-header">
                                <div>
                                    <h3 class="survey-title"><?php echo htmlspecialchars($survey['survey_title']); ?></h3>
                                    <p style="color: #64748b; font-size: 14px; margin-top: 5px;">
                                        <?php echo htmlspecialchars($survey['survey_description']); ?>
                                    </p>
                                </div>
                                <span class="survey-status <?php echo $survey['status']; ?>">
                                    <?php echo ucfirst($survey['status']); ?>
                                </span>
                            </div>
                            <div class="survey-meta">
                                <span>📝 <?php echo $survey['question_count']; ?> Questions</span>
                                <span>📊 <?php echo $survey['response_count']; ?> Responses</span>
                                <span>👤 By <?php echo htmlspecialchars($survey['creator_name'] ?? 'Unknown'); ?></span>
                                <span>📅 <?php echo date('M d, Y', strtotime($survey['created_at'])); ?></span>
                            </div>
                            <div class="survey-actions">
                                <a href="questions.php?survey_id=<?php echo $survey['id']; ?>" class="btn btn-primary">
                                    Manage Questions
                                </a>
                                <button class="btn btn-secondary" onclick="openShareModal(<?php echo $survey['id']; ?>, '<?php echo addslashes($survey['survey_title']); ?>')">
                                    🔗 Share & QR Code
                                </button>
                                <a href="consent.php?survey_id=<?php echo $survey['id']; ?>" target="_blank" class="btn btn-secondary">
                                    Preview
                                </a>
                                <button class="btn btn-secondary" onclick="editSurvey(<?php echo $survey['id']; ?>, '<?php echo addslashes($survey['survey_title']); ?>', '<?php echo addslashes($survey['survey_description']); ?>', '<?php echo $survey['status']; ?>')">
                                    Edit
                                </button>
                                <button class="btn btn-secondary" onclick="deleteSurvey(<?php echo $survey['id']; ?>)" style="background: #fee2e2; color: #dc2626;">
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Create/Edit Modal -->
    <div class="modal-overlay" id="surveyModal">
        <div class="modal-box">
            <h3 id="modalTitle">Create New Survey</h3>
            <form id="surveyForm">
                <input type="hidden" id="surveyId" value="">
                <div class="form-group">
                    <label>Survey Title *</label>
                    <input type="text" id="surveyTitle" required placeholder="e.g., Customer Feedback Survey">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="surveyDescription" rows="4" placeholder="Brief description of the survey..."></textarea>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select id="surveyStatus" required>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-save">Save Survey</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="modal-overlay" id="shareModal">
        <div class="modal-box">
            <div class="share-content">
                <h3 id="shareModalTitle">Share Survey</h3>
                
                <div class="qr-code-container">
                    <div id="qrcode"></div>
                </div>
                
                <button class="download-qr-btn" onclick="downloadQR()">Download QR Code</button>
                
                <div class="share-link-box">
                    <label style="text-align: left; display: block; margin-bottom: 8px; font-weight: 500;">Survey Link:</label>
                    <input type="text" id="surveyLink" readonly onclick="this.select()">
                    <button class="copy-btn" id="copyBtn" onclick="copyLink()">Copy Link</button>
                </div>
                
 
                
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeShareModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <script>
        let currentQRCode = null;
        let currentSurveyLink = '';

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Survey';
            document.getElementById('surveyForm').reset();
            document.getElementById('surveyId').value = '';
            document.getElementById('surveyModal').classList.add('show');
        }

        function editSurvey(id, title, description, status) {
            document.getElementById('modalTitle').textContent = 'Edit Survey';
            document.getElementById('surveyId').value = id;
            document.getElementById('surveyTitle').value = title;
            document.getElementById('surveyDescription').value = description;
            document.getElementById('surveyStatus').value = status;
            document.getElementById('surveyModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('surveyModal').classList.remove('show');
        }

        function openShareModal(surveyId, surveyTitle) {
            const baseUrl = window.location.origin + window.location.pathname.replace('surveys.php', '');
            currentSurveyLink = baseUrl + 'consent.php?survey_id=' + surveyId;
            
            document.getElementById('shareModalTitle').textContent = 'Share: ' + surveyTitle;
            document.getElementById('surveyLink').value = currentSurveyLink;
            
            // Clear previous QR code
            document.getElementById('qrcode').innerHTML = '';
            
            // Generate new QR code
            currentQRCode = new QRCode(document.getElementById('qrcode'), {
                text: currentSurveyLink,
                width: 256,
                height: 256,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
            
            document.getElementById('shareModal').classList.add('show');
        }

        function closeShareModal() {
            document.getElementById('shareModal').classList.remove('show');
            const copyBtn = document.getElementById('copyBtn');
            copyBtn.textContent = '📋 Copy Link';
            copyBtn.classList.remove('copied');
        }

        function copyLink() {
            const linkInput = document.getElementById('surveyLink');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // For mobile devices
            
            document.execCommand('copy');
            
            const copyBtn = document.getElementById('copyBtn');
            copyBtn.textContent = '✓ Copied!';
            copyBtn.classList.add('copied');
            
            setTimeout(() => {
                copyBtn.textContent = '📋 Copy Link';
                copyBtn.classList.remove('copied');
            }, 2000);
        }

        function downloadQR() {
            const canvas = document.querySelector('#qrcode canvas');
            if (canvas) {
                const link = document.createElement('a');
                link.download = 'survey-qr-code.png';
                link.href = canvas.toDataURL();
                link.click();
            }
        }

        document.getElementById('surveyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const surveyId = document.getElementById('surveyId').value;
            const action = surveyId ? 'update_survey' : 'create_survey';
            
            const formData = new FormData();
            formData.append('action', action);
            if (surveyId) formData.append('survey_id', surveyId);
            formData.append('survey_title', document.getElementById('surveyTitle').value);
            formData.append('survey_description', document.getElementById('surveyDescription').value);
            formData.append('status', document.getElementById('surveyStatus').value);
            
            fetch('surveys.php', {
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

        function deleteSurvey(id) {
            if (!confirm('Are you sure you want to delete this survey? All questions and responses will be deleted.')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_survey');
            formData.append('survey_id', id);
            
            fetch('surveys.php', {
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