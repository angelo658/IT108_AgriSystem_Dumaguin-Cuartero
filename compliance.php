<?php
session_start();
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

checkSessionTimeout();

$user_id = $_SESSION['user_id'];
$firstname = $_SESSION['firstname'] ?? "User";
$lastname = $_SESSION['lastname'] ?? "";
$_SESSION['last_login'] = date("Y-m-d H:i:s");

// DB connections
$conn = getDBConnection();
$pdo = getPDOConnection();

// Handle task actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $title = $_POST['task_title'];
        $desc = $_POST['task_description'];
        $due_date = $_POST['due_date'];
        
        $stmt = $pdo->prepare("INSERT INTO compliance_tasks (user_id, title, description, due_date, status, created_at) 
                               VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$user_id, $title, $desc, $due_date]);
        
        logActivity($pdo, 'task_created', "User created compliance task: $title", $user_id);
        createNotification($pdo, $user_id, 'Task Created', "Your compliance task '$title' has been created successfully.", 'success');
        
    } elseif ($action === 'update_status' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        $new_status = $_POST['new_status'];
        
        $stmt = $pdo->prepare("UPDATE compliance_tasks SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $task_id, $user_id]);
        
        logActivity($pdo, 'task_updated', "User updated task ID $task_id status to: $new_status", $user_id);
        
    } elseif ($action === 'delete' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        
        $stmt = $pdo->prepare("DELETE FROM compliance_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$task_id, $user_id]);
        
        logActivity($pdo, 'task_deleted', "User deleted task ID: $task_id", $user_id);
    }
    
    header("Location: compliance.php");
    exit;
}

// Fetch user tasks
$stmt = $pdo->prepare("SELECT * FROM compliance_tasks WHERE user_id = ? ORDER BY due_date ASC");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();

// Get task statistics
$pending_tasks = $pdo->query("SELECT COUNT(*) FROM compliance_tasks WHERE user_id = $user_id AND status='pending'")->fetchColumn();
$completed_tasks = $pdo->query("SELECT COUNT(*) FROM compliance_tasks WHERE user_id = $user_id AND status='completed'")->fetchColumn();
$overdue_tasks = $pdo->query("SELECT COUNT(*) FROM compliance_tasks WHERE user_id = $user_id AND status!='completed' AND due_date < CURDATE()")->fetchColumn();
$total_tasks_count = $pdo->query("SELECT COUNT(*) FROM compliance_tasks WHERE user_id = $user_id")->fetchColumn();

// Get unread notification count
$unread_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Compliance Tasks | AGRI Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ===========================
   GLOBAL STYLES
=========================== */
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

/* ===========================
   SIDEBAR
=========================== */
.sidebar {
    width: 250px;
    height: 100vh;
    background: #0A1A2F;
    position: fixed;
    left: 0;
    top: 0;
    padding: 25px 20px;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 8px rgba(0, 0, 0, 0.3);
    z-index: 2000;
}

.sidebar .logo {
    font-size: 22px;
    font-weight: bold;
    color: #fff;
    margin-bottom: 35px;
}

.sidebar a {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    color: #d2d9e5;
    font-size: 16px;
    text-decoration: none;
    margin-bottom: 10px;
    border-radius: 8px;
    transition: 0.2s ease;
}

.sidebar a i {
    margin-right: 12px;
    font-size: 18px;
}

.sidebar a:hover {
    background: #15345A;
    color: #fff;
}

.sidebar a.active {
    background: #1E4C7A;
    color: #fff;
}

.sidebar a:nth-child(2) i { color: #5ac18e; }
.sidebar a:nth-child(3) i { color: #ffcc00; }
.sidebar a:nth-child(4) i { color: #8d99ae; }
.sidebar a:nth-child(5) i { color: #f9844a; }
.sidebar a:nth-child(6) i { color: #1E90FF; }

/* ===========================
   MAIN CONTENT
=========================== */
.main-content {
    margin-left: 250px;
    padding: 30px 20px;
}

h2.section-title {
    font-size: 26px;
    background: linear-gradient(90deg, #3AA655, #E8C547);
    -webkit-background-clip: text;
    color: transparent;
    margin-bottom: 16px;
}

/* ===========================
   STATS CARDS
=========================== */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background: #11243b;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.25);
    transition: 0.3s;
}

.stat-box:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
}

.stat-info h3 {
    font-size: 28px;
    color: #E8C547;
}

.stat-info p {
    font-size: 14px;
    color: #c7d8e0;
    margin-top: 5px;
}

/* ===========================
   FORM CARD
=========================== */
.form-card {
    background: #11243b;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
    margin-bottom: 30px;
}

.form-card h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #E8C547;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: 1px solid #3AA655;
    background: #0A1A2F;
    color: #f5f6fa;
    font-size: 15px;
}

textarea {
    height: 100px;
    resize: none;
}

button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    background: #228b3cff;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    opacity: 0.9;
}

/* ===========================
   TASK CARDS
=========================== */
.tasks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
}

.task-card {
    background: #11243b;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
    border-left: 4px solid #3AA655;
    transition: 0.2s;
}

.task-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.5);
}

.task-card.high { border-left-color: #E74C3C; }
.task-card.medium { border-left-color: #FF9800; }
.task-card.low { border-left-color: #3AA655; }

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.task-title {
    font-size: 18px;
    font-weight: 600;
    color: #E8C547;
}

.task-priority {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.priority-high { background: #ffe1e1; color: #ad0000; }
.priority-medium { background: #fff3cd; color: #856404; }
.priority-low { background: #d4edda; color: #155724; }

.task-desc {
    font-size: 14px;
    color: #c7d8e0;
    margin-bottom: 12px;
}

.task-meta {
    font-size: 13px;
    color: #888;
    margin-bottom: 15px;
}

.task-meta i {
    margin-right: 5px;
}

.task-status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 15px;
}

.status-pending { background: #dcedff; color: #0f5099; }
.status-in-progress { background: #fff3cd; color: #856404; }
.status-completed { background: #d4edda; color: #155724; }

.task-actions {
    display: flex;
    gap: 8px;
}

.task-actions button {
    width: auto;
    padding: 8px 12px;
    font-size: 13px;
}

.btn-progress { background: #FF9800; }
.btn-complete { background: #4CAF50; }
.btn-delete { background: #E74C3C; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #aaa;
}

.empty-state i {
    font-size: 60px;
    margin-bottom: 20px;
    color: #3AA655;
}

/* ===========================
   RESPONSIVE
=========================== */
@media (max-width: 900px) {
    .main-content {
        margin-left: 0;
    }
}
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">🌿 AGRI Com</div>
    <a href="home.php"><i class="fa fa-home"></i> Home</a>
    <a href="compliance.php" class="active"><i class="fas fa-tasks"></i> Compliance Tools</a>
    <a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a>
    <a href="permit.php"><i class="fa fa-id-card"></i> Permit</a>
    <a href="documents.php"><i class="fa fa-file"></i> Documents</a>
    <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <?php if($unread_count > 0): ?><span style="background:#ff4d4d;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;"><?= $unread_count ?></span><?php endif; ?></a>
    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
    <a href="login.php" style="margin-top:auto;color:#ff7675;"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    
    <h2 class="section-title">Compliance Task Management</h2>
    
    <!-- Task Statistics -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-icon" style="background:#2196F3;"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3><?= $pending_tasks ?></h3>
                <p>Pending Tasks</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon" style="background:#4CAF50;"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3><?= $completed_tasks ?></h3>
                <p>Completed</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon" style="background:#E74C3C;"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <h3><?= $overdue_tasks ?></h3>
                <p>Overdue</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon" style="background:#9C27B0;"><i class="fas fa-tasks"></i></div>
            <div class="stat-info">
                <h3><?= $total_tasks_count ?></h3>
                <p>Total Tasks</p>
            </div>
        </div>
    </div>
    
    <!-- Create New Task -->
    <div class="form-card">
        <h3><i class="fas fa-plus-circle"></i> Create New Task</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <label>Task Title</label>
            <input type="text" name="task_title" placeholder="Ex: Submit Environmental Impact Assessment" required>
            
            <label>Description</label>
            <textarea name="task_description" placeholder="Task details and requirements..." required></textarea>
            
            <label>Due Date</label>
            <input type="date" name="due_date" required>
            
            <button type="submit">Create Task</button>
        </form>
    </div>
    
    <!-- Task List -->
    <h2 class="section-title">My Tasks</h2>
    
    <div class="tasks-grid">
        <?php if (count($tasks) > 0): ?>
            <?php foreach($tasks as $task): ?>
                <?php 
                    $status_class = strtolower(str_replace(' ', '-', $task['status']));
                    $is_overdue = ($task['status'] != 'completed' && strtotime($task['due_date']) < time());
                ?>
                <div class="task-card">
                    <div class="task-header">
                        <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                    </div>
                    
                    <div class="task-desc"><?= htmlspecialchars($task['description']) ?></div>
                    
                    <div class="task-meta">
                        <i class="fas fa-calendar"></i> Due: <?= date('M d, Y', strtotime($task['due_date'])) ?>
                        <?php if($is_overdue): ?>
                            <span style="color:#E74C3C;font-weight:600;margin-left:10px;">
                                <i class="fas fa-exclamation-circle"></i> OVERDUE
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="task-status status-<?= $status_class ?>">
                        <?= ucfirst($task['status']) ?>
                    </div>
                    
                    <div class="task-actions">
                        <?php if($task['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <input type="hidden" name="new_status" value="completed">
                                <button type="submit" class="btn-complete">Mark Complete</button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <button type="submit" class="btn-delete" onclick="return confirm('Delete this task?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h3>No Tasks Yet</h3>
                <p>Create your first compliance task to get started!</p>
            </div>
        <?php endif; ?>
    </div>
    
</div>

</body>
</html>
