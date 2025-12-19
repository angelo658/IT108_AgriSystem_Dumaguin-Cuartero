<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// DB connection
$pdo = new PDO("mysql:host=localhost;dbname=portal;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Handle status update (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];

    if (in_array($action, ['Approved', 'Rejected'])) {
        $stmt = $pdo->prepare("UPDATE permits SET status = ? WHERE id = ?");
        $stmt->execute([$action, $id]);
    }
    header("Location: user_permit.php");
    exit;
}

// Fetch all permits INCLUDING Cancelled
$permits = $pdo->query("
    SELECT p.*, u.username 
    FROM permits p
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - User Permits</title>
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
    background: linear-gradient(135deg, #0A1A2F, #153B80, #1E4C7A);
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
   LAYOUT
========================= */
.container {
    width: 90%;
    margin: 30px auto;
}

/* =========================
   TABLE STYLES
========================= */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th,
td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

th {
    background: #153B80;
    color: #E8C547;
    font-weight: 500;
}

tr:hover {
    background: rgba(255,255,255,0.05);
}

/* =========================
   STATUS BADGES
========================= */
.status {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
    color: white;
}

.status.Pending {
    background: #ff9800;
}

.status.Approved {
    background: #3AA655;
}

.status.Rejected {
    background: #b71c1c;
}

.status.Cancelled {
    background: #6c757d;
}

/* =========================
   ACTION BUTTONS
========================= */
.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    color: white;
    margin-right: 5px;
    text-decoration: none;
    transition: 0.3s;
}

.action-btn.approve {
    background: #3AA655;
}

.action-btn.approve:hover {
    filter: brightness(1.2);
}

.action-btn.reject {
    background: #b71c1c;
}

.action-btn.reject:hover {
    filter: brightness(1.2);
}

</style>
</head>
<body>

<header>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h2><i class="fas fa-file-signature"></i> User Permit Requests</h2>
    <nav>
      <ul>
        <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="registered_users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="user_permit.php" class="active"><i class="fas fa-file-signature"></i> Permits</a></li>
        <li><a href="user_documents.php"><i class="fas fa-file-alt"></i> Documents</a></li>
        <li><a href="user_compliance_tasks.php"><i class="fas fa-tasks"></i> Compliance</a></li>
        <li><a href="user_legal_resources.php"><i class="fas fa-book"></i> Resources</a></li>
        <li><a href="activity_logs.php"><i class="fas fa-history"></i> Logs</a></li>
        <li><a href="manage_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        <li><a href="admin_login.php" style="color:#ff4d4d;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </nav>
  </div>
</header>

<div class="container">
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Permit Type</th>
                <th>Title</th>
                <th>Description</th>
                <th>Status</th>
                <th>Submitted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if(count($permits) > 0): ?>
            <?php foreach($permits as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['username']) ?></td>
                <td><?= htmlspecialchars($p['permit_type']) ?></td>
                <td><?= htmlspecialchars($p['permit_title']) ?></td>
                <td><?= htmlspecialchars($p['description']) ?></td>
                <td><span class="status <?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                <td><?= date("F d, Y", strtotime($p['created_at'])) ?></td>
                <td>
                    <?php if(strcasecmp($p['status'], "Pending") === 0): ?>
                        <a href="?action=Approved&id=<?= $p['id'] ?>" class="action-btn approve">
                            <i class="fas fa-check"></i> Approve
                        </a>
                        <a href="?action=Rejected&id=<?= $p['id'] ?>" class="action-btn reject">
                            <i class="fas fa-times"></i> Reject
                        </a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" style="text-align:center;">No permit applications yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
