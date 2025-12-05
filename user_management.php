<?php
session_start();
require 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is Super Admin
if ($_SESSION['account_type'] !== 'SuperAdmin') {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <script>
            alert("Access Denied: You must be a Super Admin to access User Management.");
            window.location.href = "dashboard.html";
        </script>
    </head>
    <body>
        <p>Redirecting...</p>
    </body>
    </html>';
    exit();
}

// Handle AJAX requests for approve, deny, delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $adminId = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE admin SET status = 'Approved' WHERE AdminID = ?");
        $stmt->bind_param("i", $adminId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User approved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to approve user']);
        }
        $stmt->close();
    }
    elseif ($action === 'deny') {
        $stmt = $conn->prepare("UPDATE admin SET status = 'Denied' WHERE AdminID = ?");
        $stmt->bind_param("i", $adminId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User denied successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to deny user']);
        }
        $stmt->close();
    }
    elseif ($action === 'delete') {
        // Prevent deleting yourself
        if ($adminId == $_SESSION['admin_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        } else {
            $stmt = $conn->prepare("DELETE FROM admin WHERE AdminID = ?");
            $stmt->bind_param("i", $adminId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
            }
            $stmt->close();
        }
    }
    elseif ($action === 'update') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $accountType = $_POST['account_type'];
        
        $stmt = $conn->prepare("UPDATE admin SET Name = ?, Email = ?, AccountType = ? WHERE AdminID = ?");
        $stmt->bind_param("sssi", $name, $email, $accountType, $adminId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user']);
        }
        $stmt->close();
    }
    
    $conn->close();
    exit();
}

// Fetch approved users
$approvedUsers = [];
$queryApproved = "SELECT AdminID, Name, Email, AccountType FROM admin WHERE status = 'Approved' ORDER BY Name ASC";
$resultApproved = $conn->query($queryApproved);
if ($resultApproved) {
    while ($row = $resultApproved->fetch_assoc()) {
        $approvedUsers[] = $row;
    }
}

// Fetch pending users
$pendingUsers = [];
$queryPending = "SELECT AdminID, Name, Email, AccountType FROM admin WHERE status = 'Pending' ORDER BY AdminID DESC";
$resultPending = $conn->query($queryPending);
if ($resultPending) {
    while ($row = $resultPending->fetch_assoc()) {
        $pendingUsers[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Survey System</title>
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .section-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title1, .section-title2 {
            font-size: 20px;
            color: #1e293b;
            font-weight: 600;
            font-family: 'League Spartan', sans-serif;
            margin-bottom: 20px;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            flex: 1;
            max-width: 400px;
        }

        .search-icon {
            width: 18px;
            height: 18px;
            opacity: 0.5;
        }

        .search-bar {
            border: none;
            background: none;
            outline: none;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            width: 100%;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table thead {
            background: #1f2a2d;
        }

        .users-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
        }

        .users-table td {
            padding: 14px 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 14px;
            color: #334155;
        }

        .users-table tbody tr:hover {
            background: #f8fafc;
        }

        .edit-btn {
            background-color: #2196F3;
        }

        .edit-btn:hover {
            background-color: #0b7dda;
        }

        .delete-btn {
            background-color: #ef4444;
        }

        .delete-btn:hover {
            background-color: #dc2626;
        }

        .approve-btn {
            background-color: #10b981;
        }

        .approve-btn:hover {
            background-color: #059669;
        }

        .deny-btn {
            background-color: #ef4444;
        }

        .deny-btn:hover {
            background-color: #dc2626;
        }

        /* Edit Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 22px;
            color: #1e293b;
            font-family: 'League Spartan', sans-serif;
        }

        .modal-body .form-group {
            margin-bottom: 15px;
        }

        .modal-body label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 5px;
        }

        .modal-body input,
        .modal-body select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .modal-body input:focus,
        .modal-body select:focus {
            outline: none;
            border-color: #0080aa;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .modal-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }

        .modal-btn-save {
            background-color: #0080aa;
            color: white;
        }

        .modal-btn-save:hover {
            background-color: #006d8f;
        }

        .modal-btn-cancel {
            background-color: white;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .modal-btn-cancel:hover {
            background-color: #f8fafc;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
            font-size: 14px;
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
            <a href="dashboard.html" class="nav-item">Dashboard</a>
            <a href="surveys.php" class="nav-item">Surveys</a>
            <a href="user_management.php" class="nav-item active">Users</a>
            <a href="responses.php" class="nav-item">Responses</a>
            <a href="reports.html" class="nav-item">Reports</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>User Management</h1>

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

            <!-- Approved Users Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title1">Active Users</h2>

                    <div class="search-wrapper">
                        <img src="icons/search.png" class="search-icon" alt="Search">
                        <input type="text" id="searchInput" class="search-bar" placeholder="Search by Name, Email, or Role">
                    </div>
                </div>

                <div class="table-container">
                    <table class="users-table" id="users-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($approvedUsers)): ?>
                                <tr>
                                    <td colspan="5" class="no-data">No active users found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($approvedUsers as $user): ?>
                                    <tr data-user-id="<?php echo $user['AdminID']; ?>">
                                        <td><?php echo htmlspecialchars($user['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['AccountType']); ?></td>
                                        <td>
                                            <button class="view-btn edit-btn" onclick="openEditModal(<?php echo $user['AdminID']; ?>, '<?php echo htmlspecialchars($user['Name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['Email'], ENT_QUOTES); ?>', '<?php echo $user['AccountType']; ?>')">
                                                <img src="icons/edit.png" class="btn-icon-small" alt="Edit">Edit
                                            </button>
                                        </td>
                                        <td>
                                            <button class="view-btn delete-btn" onclick="deleteUser(<?php echo $user['AdminID']; ?>, '<?php echo htmlspecialchars($user['Name'], ENT_QUOTES); ?>')">
                                                <img src="icons/delete.png" class="btn-icon-small" alt="Delete">Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- Pending Requests Section -->
            <div class="section-card">
                <h2 class="section-title2">Pending Requests</h2>

                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Requested Role</th>
                                <th>Approve</th>
                                <th>Deny</th>
                            </tr>
                        </thead>

                        <tbody id="pending-table-body">
                            <?php if (empty($pendingUsers)): ?>
                                <tr>
                                    <td colspan="5" class="no-data">No pending requests</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingUsers as $user): ?>
                                    <tr data-pending-id="<?php echo $user['AdminID']; ?>">
                                        <td><?php echo htmlspecialchars($user['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['AccountType']); ?></td>
                                        <td>
                                            <button class="view-btn approve-btn" onclick="approveUser(<?php echo $user['AdminID']; ?>, '<?php echo htmlspecialchars($user['Name'], ENT_QUOTES); ?>')">
                                                <img src="icons/check.png" class="btn-icon-small" alt="Approve">Approve
                                            </button>
                                        </td>
                                        <td>
                                            <button class="view-btn deny-btn" onclick="denyUser(<?php echo $user['AdminID']; ?>, '<?php echo htmlspecialchars($user['Name'], ENT_QUOTES); ?>')">
                                                <img src="icons/x.png" class="btn-icon-small" alt="Deny">Deny
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </main>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Edit User</h3>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-user-id">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="edit-name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="edit-email" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select id="edit-account-type">
                        <option value="Admin">Admin</option>
                        <option value="SuperAdmin">Super Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button class="modal-btn modal-btn-save" onclick="saveUser()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        // Search Function
        document.getElementById("searchInput").addEventListener("keyup", function () {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#users-table tbody tr");

            rows.forEach(row => {
                if (row.querySelector('.no-data')) return;
                
                let name = row.children[0].textContent.toLowerCase();
                let email = row.children[1].textContent.toLowerCase();
                let role = row.children[2].textContent.toLowerCase();

                if (name.includes(filter) || email.includes(filter) || role.includes(filter)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });

        // Open Edit Modal
        function openEditModal(id, name, email, accountType) {
            document.getElementById('edit-user-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-account-type').value = accountType;
            document.getElementById('editModal').classList.add('show');
        }

        // Close Edit Modal
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // Save User
        function saveUser() {
            const id = document.getElementById('edit-user-id').value;
            const name = document.getElementById('edit-name').value;
            const email = document.getElementById('edit-email').value;
            const accountType = document.getElementById('edit-account-type').value;

            fetch('user_management.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=update&admin_id=${id}&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&account_type=${accountType}`
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
        }

        // Delete User
        function deleteUser(id, name) {
            if (!confirm(`Are you sure you want to delete "${name}"?`)) return;

            fetch('user_management.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&admin_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    document.querySelector(`tr[data-user-id="${id}"]`).remove();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }

        // Approve User
        function approveUser(id, name) {
            if (!confirm(`Approve "${name}" as a user?`)) return;

            fetch('user_management.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=approve&admin_id=${id}`
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
        }

        // Deny User
        function denyUser(id, name) {
            if (!confirm(`Deny registration request from "${name}"?`)) return;

            fetch('user_management.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=deny&admin_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    document.querySelector(`tr[data-pending-id="${id}"]`).remove();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }

        // Close modal on outside click
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>

</body>
</html>