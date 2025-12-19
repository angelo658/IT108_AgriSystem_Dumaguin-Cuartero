<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = getPDOConnection();
$message = "";
$message_type = "";

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_announcement'])) {
        $title = sanitizeInput($_POST['title']);
        $content = sanitizeInput($_POST['content']);
        $priority = sanitizeInput($_POST['priority']);
        
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, priority, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $priority, $_SESSION['admin_id']]);
        
        logActivity($conn, 'create_announcement', "Created announcement: $title", null, $_SESSION['admin_id']);
        $message = "Announcement created successfully!";
        $message_type = "success";
    } elseif (isset($_POST['edit_announcement'])) {
        $id = intval($_POST['announcement_id']);
        $title = sanitizeInput($_POST['title']);
        $content = sanitizeInput($_POST['content']);
        $priority = sanitizeInput($_POST['priority']);
        
        $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, priority=? WHERE id=?");
        $stmt->execute([$title, $content, $priority, $id]);
        
        logActivity($conn, 'update_announcement', "Updated announcement ID: $id", null, $_SESSION['admin_id']);
        $message = "Announcement updated successfully!";
        $message_type = "success";
    } elseif (isset($_POST['toggle_status'])) {
        $id = intval($_POST['announcement_id']);
        $stmt = $conn->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id=?");
        $stmt->execute([$id]);
        
        logActivity($conn, 'toggle_announcement', "Toggled announcement status ID: $id", null, $_SESSION['admin_id']);
        $message = "Announcement status updated!";
        $message_type = "success";
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id=?");
    $stmt->execute([$id]);
    
    logActivity($conn, 'delete_announcement', "Deleted announcement ID: $id", null, $_SESSION['admin_id']);
    header("Location: manage_announcements.php?deleted=1");
    exit;
}

// Fetch announcements
$announcements = $conn->query("SELECT a.*, aa.username as created_by_name FROM announcements a LEFT JOIN admin_accounts aa ON a.created_by = aa.id ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Announcements | <?php echo SITE_NAME; ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* =====================================
   GLOBAL RESET & BASE STYLES
===================================== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Poppins", sans-serif;
}

body {
    background: #0A1A2F;
    color: #f5f6fa;
}

/* =====================================
   HEADER & NAVIGATION
===================================== */
header {
    background: linear-gradient(135deg, #0A1A2F, #153B80, #1E4C7A);
    color: white;
    padding: 18px 0;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.6);
    position: sticky;
    top: 0;
    z-index: 1000;
}

header h2 {
    margin-left: 25px;
    font-weight: 600;
    font-size: 24px;
}

nav ul {
    list-style: none;
    display: flex;
    gap: 8px;
    margin-right: 25px;
    flex-wrap: wrap;
}

nav ul li a {
    color: #E8C547;
    text-decoration: none;
    font-weight: 500;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
}

nav ul li a i {
    font-size: 14px;
}

nav ul li a:hover {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    transform: translateY(-2px);
}

nav ul li a.active {
    background: rgba(58, 166, 85, 0.25);
    color: white;
    border: 1px solid #3AA655;
}

/* =====================================
   MAIN CONTAINER
===================================== */
.container {
    width: 90%;
    max-width: 1200px;
    margin: 30px auto;
}

/* =====================================
   CARDS
===================================== */
.card {
    background: #11243b;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
    margin-bottom: 25px;
}

.card h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #E8C547;
}

/* =====================================
   FORMS
===================================== */
.form-group {
    margin-bottom: 15px;
}

label {
    display: block;
    margin-bottom: 5px;
    color: #E8C547;
    font-weight: 500;
}

input,
textarea,
select {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #3AA655;
    background: #0A1A2F;
    color: white;
    font-size: 14px;
    outline: none;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

/* =====================================
   BUTTONS
===================================== */
button {
    padding: 10px 20px;
    background: #3AA655;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    background: #2c8f48;
}

/* =====================================
   TABLES
===================================== */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th,
td {
    padding: 12px 10px;
    text-align: left;
    color: #f5f6fa;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

th {
    background: #153B80;
    color: #E8C547;
    font-weight: 500;
}

tr:hover {
    background: rgba(58, 166, 85, 0.1);
}

/* =====================================
   PRIORITY BADGES
===================================== */
.priority {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.priority.high {
    background: #f44336;
    color: white;
}

.priority.medium {
    background: #ff9800;
    color: white;
}

.priority.low {
    background: #4caf50;
    color: white;
}

/* =====================================
   STATUS BADGES
===================================== */
.status {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.status.active {
    background: #4caf50;
    color: white;
}

.status.inactive {
    background: #9e9e9e;
    color: white;
}

/* =====================================
   ACTION BUTTONS
===================================== */
.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    cursor: pointer;
    color: white;
    margin-right: 5px;
    text-decoration: none;
    transition: 0.3s;
}

.action-btn.edit {
    background: #2196f3;
}

.action-btn.delete {
    background: #f44336;
}

.action-btn.toggle {
    background: #ff9800;
}

/* =====================================
   MESSAGES / ALERTS
===================================== */
.message {
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    font-size: 14px;
    text-align: center;
}

.message.success {
    background: rgba(58, 166, 85, 0.2);
    color: #3AA655;
    border: 1px solid #3AA655;
}

.message.error {
    background: rgba(255, 77, 77, 0.2);
    color: #ff4d4d;
    border: 1px solid #ff4d4d;
}

</style>
</head>
<body>

<header>
<div style="display:flex;justify-content:space-between;align-items:center;">
<h2><i class="fas fa-bullhorn"></i> Manage Announcements</h2>
<nav>
<ul>
<li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
<li><a href="registered_users.php"><i class="fas fa-users"></i> Users</a></li>
<li><a href="user_permit.php"><i class="fas fa-file-signature"></i> Permits</a></li>
<li><a href="user_documents.php"><i class="fas fa-file-alt"></i> Documents</a></li>
<li><a href="user_compliance_tasks.php"><i class="fas fa-tasks"></i> Compliance</a></li>
<li><a href="user_legal_resources.php"><i class="fas fa-book"></i> Resources</a></li>
<li><a href="activity_logs.php"><i class="fas fa-history"></i> Logs</a></li>
<li><a href="manage_announcements.php" class="active"><i class="fas fa-bullhorn"></i> Announcements</a></li>
<li><a href="admin_login.php" style="color:#ff4d4d;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</nav>
</div>
</header>

<div class="container">
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['deleted'])): ?>
        <div class="message success">Announcement deleted successfully!</div>
    <?php endif; ?>

    <!-- Add New Announcement -->
    <div class="card">
        <h3><i class="fas fa-plus-circle"></i> Add New Announcement</h3>
        <form method="POST">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Content</label>
                <textarea name="content" required></textarea>
            </div>
            <div class="form-group">
                <label>Priority</label>
                <select name="priority" required>
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
            <button type="submit" name="add_announcement"><i class="fas fa-save"></i> Add Announcement</button>
        </form>
    </div>

    <!-- Announcements List -->
    <div class="card">
        <h3><i class="fas fa-list"></i> All Announcements</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($announcements) > 0): ?>
                    <?php foreach ($announcements as $ann): ?>
                    <tr>
                        <td><?= $ann['id'] ?></td>
                        <td><?= htmlspecialchars($ann['title']) ?></td>
                        <td><span class="priority <?= $ann['priority'] ?>"><?= ucfirst($ann['priority']) ?></span></td>
                        <td><span class="status <?= $ann['is_active'] ? 'active' : 'inactive' ?>"><?= $ann['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= $ann['created_by_name'] ?? 'Unknown' ?></td>
                        <td><?= date('M d, Y', strtotime($ann['created_at'])) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                                <button type="submit" name="toggle_status" class="action-btn toggle">
                                    <i class="fas fa-toggle-on"></i> Toggle
                                </button>
                            </form>
                            <a href="?delete=<?= $ann['id'] ?>" class="action-btn delete" onclick="return confirm('Delete this announcement?');">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:30px;">No announcements found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
