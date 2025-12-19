<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=portal;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

/* ===== CREATE TASK FOR SPECIFIC USERS ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_task') {
    $title = $_POST['task_title'];
    $description = $_POST['task_description'];
    $due_date = $_POST['due_date'];
    $selected_users = $_POST['selected_users'] ?? [];
    
    if (!empty($selected_users)) {
        $stmt = $pdo->prepare("INSERT INTO compliance_tasks (user_id, title, description, due_date, status, created_at) 
                               VALUES (?, ?, ?, ?, 'pending', NOW())");
        
        foreach ($selected_users as $user_id) {
            $stmt->execute([$user_id, $title, $description, $due_date]);
            
            // Create notification for each user
            createNotification($pdo, $user_id, 'New Compliance Task', "You have been assigned a new task: $title", 'info');
        }
        
        logActivity($pdo, 'admin_task_created', "Admin created compliance task for " . count($selected_users) . " user(s): $title", null, $_SESSION['admin_id']);
        
        header("Location: user_compliance_tasks.php?success=1");
        exit;
    }
}

/* ===== FILTER & SEARCH ===== */
$filterUser = $_GET['user'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT compliance_tasks.*, users.username, users.firstname, users.lastname
          FROM compliance_tasks
          LEFT JOIN users ON users.id = compliance_tasks.user_id
          WHERE 1 ";

$params = [];

if($filterUser != '') {
    $query .= " AND (users.username LIKE ? OR users.firstname LIKE ? OR users.lastname LIKE ?) ";
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
}

if($filterStatus != '') {
    $query .= " AND compliance_tasks.status = ? ";
    $params[] = $filterStatus;
}

if($search != '') {
    $query .= " AND (compliance_tasks.title LIKE ? OR compliance_tasks.description LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY compliance_tasks.created_at DESC ";

$tasks = $pdo->prepare($query);
$tasks->execute($params);
$tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);

/* ===== DELETE TASK ===== */
if(isset($_GET['delete_id'])){
    $stmt = $pdo->prepare("DELETE FROM compliance_tasks WHERE id=?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: user_compliance_tasks.php");
    exit;
}

/* ===== TASK SUMMARY ===== */
$total = count($tasks);
$pending = count(array_filter($tasks, fn($t) => $t['status']=='pending'));
$working = count(array_filter($tasks, fn($t) => $t['status']=='working'));
$completed = count(array_filter($tasks, fn($t) => $t['status']=='completed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Compliance Tools</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* =========================
   GLOBAL RESET & BASE
========================= */
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

/* =========================
   HEADER & NAVIGATION
========================= */
header {
    background: linear-gradient(135deg,#0A1A2F,#153B80,#1E4C7A);
    color: white;
    padding: 18px 0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.6);
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
    transition: all 0.3s;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}

nav ul li a i {
    font-size: 14px;
}

nav ul li a:hover {
    background: rgba(255,255,255,0.15);
    color: white;
    transform: translateY(-2px);
}

nav ul li a.active {
    background: rgba(58,166,85,0.25);
    color: white;
    border: 1px solid #3AA655;
}

/* =========================
   LAYOUT CONTAINER
========================= */
.container {
    width: 90%;
    max-width: 1400px;
    margin: 30px auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* =========================
   CARD COMPONENTS
========================= */
.card {
    background: #11243b;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    transition: 0.3s;
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(0,0,0,0.5);
}

.card h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #E8C547;
}

/* =========================
   TABLES
========================= */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th,
td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    color: #f5f6fa;
}

th {
    background: #153B80;
    color: #E8C547;
    font-weight: 500;
}

tr:hover {
    background: rgba(58,166,85,0.1);
}

/* =========================
   STATUS LABELS
========================= */
.status-box {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: white;
    display: inline-block;
}

.completed {
    background: #3AA655;
}

.pending {
    background: #FF9800;
}

.working {
    background: #2196F3;
}

/* =========================
   BUTTONS
========================= */
.btn-danger {
    background: #b71c1c;
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    transition: 0.3s;
}

.btn-danger:hover {
    background: #7f0000;
}

/* =========================
   FILTER FORM
========================= */
.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.filter-form input,
.filter-form select,
.filter-form button {
    padding: 8px 12px;
    border-radius: 8px;
    border: none;
    outline: none;
    font-size: 14px;
}

.filter-form button {
    background: #02661b;
    color: white;
    cursor: pointer;
}

/* =========================
   STATS
========================= */
.stats {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.stat-card {
    flex: 1;
    background: #153B80;
    padding: 15px;
    border-radius: 12px;
    text-align: center;
}

.stat-card h4 {
    font-size: 16px;
    color: #E8C547;
    margin-bottom: 5px;
}

.stat-card p {
    font-size: 22px;
    font-weight: bold;
}

.overdue {
    color: red;
    font-weight: bold;
}

/* =========================
   CREATE TASK SECTION
========================= */
.create-task-section {
    background: #11243b;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    margin-bottom: 30px;
}

.create-task-section h3 {
    font-size: 20px;
    margin-bottom: 20px;
    color: #E8C547;
}

/* =========================
   USER SELECTION
========================= */
.user-selection {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #3AA655;
    border-radius: 8px;
    padding: 15px;
    background: #0A1A2F;
    margin-bottom: 15px;
}

.user-checkbox {
    display: flex;
    align-items: center;
    padding: 10px;
    margin-bottom: 8px;
    background: #153B80;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.2s;
}

.user-checkbox:hover {
    background: #1E4C7A;
}

.user-checkbox input[type="checkbox"] {
    margin-right: 12px;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.user-checkbox label {
    cursor: pointer;
    flex: 1;
    color: #f5f6fa;
}

/* =========================
   FORM ELEMENTS
========================= */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #E8C547;
    font-weight: 500;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #3AA655;
    background: #0A1A2F;
    color: #f5f6fa;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

/* =========================
   ACTION BUTTONS
========================= */
.btn-create {
    background: #228b3cff;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 15px;
    transition: 0.3s;
}

.btn-create:hover {
    background: #1a6b2e;
}

.select-all-btn {
    background: #153B80;
    color: #E8C547;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    margin-bottom: 10px;
    font-size: 13px;
}

.select-all-btn:hover {
    background: #1E4C7A;
}

/* =========================
   SUCCESS MESSAGE
========================= */
.success-message {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #c3e6cb;
}

</style>
</head>
<body>

<header>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h2><i class="fas fa-tasks"></i> User Compliance Tools</h2>
    <nav>
      <ul>
        <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="registered_users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="user_permit.php"><i class="fas fa-file-signature"></i> Permits</a></li>
        <li><a href="user_documents.php"><i class="fas fa-file-alt"></i> Documents</a></li>
        <li><a href="user_compliance_tasks.php" class="active"><i class="fas fa-tasks"></i> Compliance</a></li>
        <li><a href="user_legal_resources.php"><i class="fas fa-book"></i> Resources</a></li>
        <li><a href="activity_logs.php"><i class="fas fa-history"></i> Logs</a></li>
        <li><a href="manage_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        <li><a href="admin_login.php" style="color:#ff4d4d;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </nav>
  </div>
</header>

<div class="container">

  <?php if(isset($_GET['success'])): ?>
    <div class="success-message">
      <i class="fas fa-check-circle"></i> Task successfully created and assigned to selected users!
    </div>
  <?php endif; ?>

  <!-- CREATE NEW TASK FORM -->
  <div class="create-task-section">
    <h3><i class="fas fa-plus-circle"></i> Create New Task for Users</h3>
    <form method="POST">
      <input type="hidden" name="action" value="create_task">
      
      <div class="form-group">
        <label><i class="fas fa-heading"></i> Task Title</label>
        <input type="text" name="task_title" placeholder="Enter task title" required>
      </div>
      
      <div class="form-group">
        <label><i class="fas fa-align-left"></i> Task Description</label>
        <textarea name="task_description" placeholder="Enter task description" required></textarea>
      </div>
      
      <div class="form-group">
        <label><i class="fas fa-calendar"></i> Due Date</label>
        <input type="date" name="due_date" required min="<?= date('Y-m-d') ?>">
      </div>
      
      <div class="form-group">
        <label><i class="fas fa-users"></i> Select Users to Assign Task</label>
        <button type="button" class="select-all-btn" onclick="toggleSelectAll()"><i class="fas fa-check-double"></i> Select All / Deselect All</button>
        <div class="user-selection">
          <?php
          // Fetch all users
          $users = $pdo->query("SELECT id, username, firstname, lastname, email FROM users ORDER BY firstname, lastname")->fetchAll(PDO::FETCH_ASSOC);
          
          if (count($users) > 0):
            foreach($users as $user):
          ?>
            <div class="user-checkbox">
              <input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>" id="user_<?= $user['id'] ?>">
              <label for="user_<?= $user['id'] ?>">
                <strong><?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></strong> 
                (<?= htmlspecialchars($user['username']) ?>) - <?= htmlspecialchars($user['email']) ?>
              </label>
            </div>
          <?php 
            endforeach;
          else:
          ?>
            <p style="text-align:center;color:#aaa;">No users found</p>
          <?php endif; ?>
        </div>
      </div>
      
      <button type="submit" class="btn-create"><i class="fas fa-paper-plane"></i> Create and Assign Task</button>
    </form>
  </div>

  <!-- TASK SUMMARY -->
  <div class="stats">
    <div class="stat-card">
      <h4>Total Tasks</h4>
      <p><?= $total ?></p>
    </div>
    <div class="stat-card">
      <h4>Pending</h4>
      <p><?= $pending ?></p>
    </div>
    <div class="stat-card">
      <h4>Working</h4>
      <p><?= $working ?></p>
    </div>
    <div class="stat-card">
      <h4>Completed</h4>
      <p><?= $completed ?></p>
    </div>
  </div>

  <!-- FILTER & SEARCH -->
  <form method="GET" class="filter-form">
    <input type="text" name="user" placeholder="Search by User" value="<?= htmlspecialchars($filterUser) ?>">
    <select name="status">
      <option value="">All Status</option>
      <option value="pending" <?= $filterStatus=='pending'?'selected':'' ?>>Pending</option>
      <option value="working" <?= $filterStatus=='working'?'selected':'' ?>>Working</option>
      <option value="completed" <?= $filterStatus=='completed'?'selected':'' ?>>Completed</option>
    </select>
    <input type="text" name="search" placeholder="Search by Title or Description" value="<?= htmlspecialchars($search) ?>">
    <button type="submit"><i class="fas fa-filter"></i> Filter</button>
  </form>

  <!-- TASK TABLE -->
  <div class="card">
    <h3><i class="fas fa-list"></i> All User Tasks</h3>
    <table>
      <thead>
        <tr>
          <th>Firstname</th>
          <th>Lastname</th>
          <th>Username</th>
          <th>Title</th>
          <th>Description</th>
          <th>Due Date</th>
          <th>Status</th>
          <th>Created</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if($tasks): ?>
          <?php foreach($tasks as $t): 
            $isOverdue = strtotime($t['due_date']) < time() && $t['status'] != 'completed';
          ?>
            <tr>
              <td><?= htmlspecialchars($t['firstname'] ?? '') ?></td>
              <td><?= htmlspecialchars($t['lastname'] ?? '') ?></td>
              <td><?= htmlspecialchars($t['username'] ?? '') ?></td>
              <td><?= htmlspecialchars($t['title']) ?></td>
              <td><?= htmlspecialchars($t['description']) ?></td>
              <td class="<?= $isOverdue?'overdue':'' ?>"><?= $t['due_date'] ?></td>
              <td><span class="status-box <?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
              <td><?= $t['created_at'] ?></td>
              <td>
                <a class="btn-danger" href="?delete_id=<?= $t['id'] ?>"><i class="fas fa-trash"></i> Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="9" style="text-align:center;">No tasks recorded</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<script>
function toggleSelectAll() {
  const checkboxes = document.querySelectorAll('input[name="selected_users[]"');
  const allChecked = Array.from(checkboxes).every(cb => cb.checked);
  
  checkboxes.forEach(cb => {
    cb.checked = !allChecked;
  });
}
</script>

</body>
</html>
