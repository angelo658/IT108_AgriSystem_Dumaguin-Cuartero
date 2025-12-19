<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = getPDOConnection();

/* ===== Dashboard Counts ===== */
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_documents = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$total_permits = $pdo->query("SELECT COUNT(*) FROM permits")->fetchColumn();
$total_tasks = $pdo->query("SELECT COUNT(*) FROM compliance_tasks")->fetchColumn();
$total_resources = $pdo->query("SELECT COUNT(*) FROM legal_resources")->fetchColumn();
$total_announcements = $pdo->query("SELECT COUNT(*) FROM announcements WHERE is_active=1")->fetchColumn();

/* ===== Today's Statistics ===== */
$today_users = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$today_permits = $pdo->query("SELECT COUNT(*) FROM permits WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$today_documents = $pdo->query("SELECT COUNT(*) FROM documents WHERE DATE(uploaded_at) = CURDATE()")->fetchColumn();
$today_activities = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();

/* ===== Permit Status Analytics ===== */
$pending_permits = $pdo->query("SELECT COUNT(*) FROM permits WHERE status='Pending'")->fetchColumn();
$approved_permits = $pdo->query("SELECT COUNT(*) FROM permits WHERE status='Approved'")->fetchColumn();
$rejected_permits = $pdo->query("SELECT COUNT(*) FROM permits WHERE status='Rejected'")->fetchColumn();

/* ===== Document Status Analytics ===== */
$pending_documents = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='Pending'")->fetchColumn();
$approved_documents = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='Approved'")->fetchColumn();
$rejected_documents = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='Rejected'")->fetchColumn();

/* ===== Task Status Analytics ===== */
$pending_tasks = $pdo->query("SELECT COUNT(*) FROM compliance_tasks WHERE status='pending'")->fetchColumn();
$completed_tasks = $pdo->query("SELECT COUNT(*) FROM compliance_tasks WHERE status='completed'")->fetchColumn();

/* ===== Recent Activities ===== */
$recent_permits = $pdo->query("
    SELECT p.*, u.username, u.firstname, u.lastname 
    FROM permits p 
    LEFT JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recent_documents = $pdo->query("
    SELECT d.*, u.username, u.firstname, u.lastname 
    FROM documents d 
    LEFT JOIN users u ON d.user_id = u.id 
    ORDER BY d.uploaded_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$recent_tasks = $pdo->query("SELECT * FROM compliance_tasks ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$recent_resources = $pdo->query("
    SELECT lr.id, lr.title, lr.resource_type, lr.created_at, lc.category_name AS category_name
    FROM legal_resources lr
    LEFT JOIN legal_categories lc ON lr.category_id = lc.id
    ORDER BY lr.created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ===== Monthly Statistics ===== */
$monthly_users = $pdo->query("SELECT COUNT(*) FROM users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$monthly_permits = $pdo->query("SELECT COUNT(*) FROM permits WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$monthly_documents = $pdo->query("SELECT COUNT(*) FROM documents WHERE MONTH(uploaded_at) = MONTH(CURDATE()) AND YEAR(uploaded_at) = YEAR(CURDATE())")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ===================================
   GLOBAL RESET & BASE STYLES
=================================== */
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

/* ===================================
   HEADER & NAVIGATION
=================================== */
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

/* ===================================
   MAIN CONTAINER
=================================== */
.container {
    width: 95%;
    max-width: 1600px;
    margin: 25px auto;
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* ===================================
   WELCOME BANNER
=================================== */
.welcome-banner {
    background: linear-gradient(135deg, #153B80, #1E4C7A);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.welcome-banner h2 {
    color: #E8C547;
    font-size: 28px;
    margin-bottom: 8px;
}

.welcome-banner p {
    color: #ccc;
    font-size: 14px;
}

.welcome-banner .date-time {
    text-align: right;
}

.welcome-banner .date-time .date {
    color: #E8C547;
    font-size: 16px;
    font-weight: 600;
}

.welcome-banner .date-time .time {
    color: #aaa;
    font-size: 13px;
    margin-top: 4px;
}

/* ===================================
   SECTION TITLES
=================================== */
.section-title {
    color: #E8C547;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: #3AA655;
}

/* ===================================
   STATISTICS GRID
=================================== */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px;
    margin-bottom: 15px;
}

.stat-card {
    background: linear-gradient(135deg, #11243b, #153B80);
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(58, 166, 85, 0.1);
    transition: all 0.3s;
}

.stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, rgba(58, 166, 85, 0.1), transparent);
    border-radius: 50%;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
    border-color: #3AA655;
}

.stat-card h3 {
    font-size: 14px;
    font-weight: 500;
    color: #aaa;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.stat-card p {
    font-size: 36px;
    font-weight: bold;
    color: #fff;
}

.stat-card .trend {
    font-size: 12px;
    color: #3AA655;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.stat-card .trend.down {
    color: #ff4d4d;
}

/* Icons */
.stat-icon {
    font-size: 45px;
    opacity: 0.8;
}

.stat-icon.users         { color: #FF9800; }
.stat-icon.documents     { color: #9C27B0; }
.stat-icon.permits       { color: #E91E63; }
.stat-icon.tasks         { color: #2196f3; }
.stat-icon.resources     { color: #3AA655; }
.stat-icon.announcements { color: #FFC107; }

/* ===================================
   QUICK STATS
=================================== */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.quick-stat {
    background: #11243b;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    border-left: 4px solid #3AA655;
    transition: all 0.3s;
}

.quick-stat:hover {
    background: #153B80;
    transform: scale(1.02);
}

.quick-stat h4 {
    color: #aaa;
    font-size: 13px;
    margin-bottom: 8px;
}

.quick-stat .number {
    font-size: 28px;
    font-weight: bold;
    color: #E8C547;
}

.quick-stat .label {
    font-size: 11px;
    color: #888;
    margin-top: 5px;
}

/* ===================================
   CARD COMPONENT
=================================== */
.card {
    background: #11243b;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(58, 166, 85, 0.1);
}

.card h3 {
    font-size: 18px;
    margin-bottom: 18px;
    color: #E8C547;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card h3 i {
    color: #3AA655;
}

/* ===================================
   TABLES & STATUS CHIPS
=================================== */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th,
td {
    padding: 14px 12px;
    text-align: left;
    font-size: 13px;
    color: #f5f6fa;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

th {
    background: rgba(21, 59, 128, 0.5);
    color: #E8C547;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 12px;
}

tr:hover {
    background: rgba(58, 166, 85, 0.08);
}

.chip {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    display: inline-block;
}

.chip.pending   { background: linear-gradient(135deg, #FF9800, #F57C00); }
.chip.approved  { background: linear-gradient(135deg, #4CAF50, #388E3C); }
.chip.rejected  { background: linear-gradient(135deg, #F44336, #C62828); }
.chip.completed { background: linear-gradient(135deg, #3AA655, #2c8f48); }

/* ===================================
   PROGRESS BARS
=================================== */
.progress-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.progress-card {
    background: #11243b;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(58, 166, 85, 0.1);
}

.progress-card h4 {
    color: #E8C547;
    margin-bottom: 15px;
    font-size: 15px;
}

.progress-item {
    margin-bottom: 15px;
}

.progress-item:last-child {
    margin-bottom: 0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
    font-size: 13px;
}

.progress-label .name {
    color: #ccc;
}

.progress-label .value {
    color: #E8C547;
    font-weight: 600;
}

.progress-bar {
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s;
}

.progress-fill.green  { background: linear-gradient(90deg, #3AA655, #4CAF50); }
.progress-fill.orange { background: linear-gradient(90deg, #FF9800, #FFA726); }
.progress-fill.red    { background: linear-gradient(90deg, #F44336, #E57373); }
.progress-fill.blue   { background: linear-gradient(90deg, #2196f3, #42A5F5); }

/* ===================================
   RESPONSIVE DESIGN
=================================== */
@media (max-width: 768px) {
    nav ul {
        flex-direction: column;
        gap: 8px;
        margin: 10px 20px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .welcome-banner {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
}

</style>
</head>
<body>

<header>
<div style="display:flex;justify-content:space-between;align-items:center;">
<h2><i class="fas fa-chart-line"></i> Dashboard</h2>
<nav>
<ul>
<li><a href="dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
<li><a href="registered_users.php"><i class="fas fa-users"></i> Users</a></li>
<li><a href="user_permit.php"><i class="fas fa-file-signature"></i> Permits</a></li>
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

<!-- Welcome Banner -->
<div class="welcome-banner">
    <div>
        <h2>Welcome back, Admin!</h2>
        <p>Here's what's happening with your agricultural compliance portal today.</p>
    </div>
    <div class="date-time">
        <div class="date"><i class="fas fa-calendar"></i> <?= date('F d, Y') ?></div>
        <div class="time"><i class="fas fa-clock"></i> <?= date('h:i A') ?></div>
    </div>
</div>

<!-- Main Statistics Cards -->
<h3 class="section-title"><i class="fas fa-chart-bar"></i> System Overview</h3>
<div class="stats">
    <div class="stat-card">
        <div>
            <h3>Total Users</h3>
            <p><?= $total_users ?></p>
            <div class="trend"><i class="fas fa-arrow-up"></i> +<?= $monthly_users ?> this month</div>
        </div>
        <i class="fas fa-users stat-icon users"></i>
    </div>
    <div class="stat-card">
        <div>
            <h3>Documents</h3>
            <p><?= $total_documents ?></p>
            <div class="trend"><i class="fas fa-arrow-up"></i> +<?= $monthly_documents ?> this month</div>
        </div>
        <i class="fas fa-file-alt stat-icon documents"></i>
    </div>
    <div class="stat-card">
        <div>
            <h3>Permits</h3>
            <p><?= $total_permits ?></p>
            <div class="trend"><i class="fas fa-arrow-up"></i> +<?= $monthly_permits ?> this month</div>
        </div>
        <i class="fas fa-file-signature stat-icon permits"></i>
    </div>
    <div class="stat-card">
        <div>
            <h3>Compliance Tasks</h3>
            <p><?= $total_tasks ?></p>
            <div class="trend <?= $pending_tasks > 0 ? '' : 'down' ?>"><i class="fas fa-<?= $pending_tasks > 0 ? 'exclamation-circle' : 'check-circle' ?>"></i> <?= $pending_tasks ?> pending</div>
        </div>
        <i class="fas fa-tasks stat-icon tasks"></i>
    </div>
    <div class="stat-card">
        <div>
            <h3>Legal Resources</h3>
            <p><?= $total_resources ?></p>
            <div class="trend"><i class="fas fa-book-open"></i> Available</div>
        </div>
        <i class="fas fa-book stat-icon resources"></i>
    </div>
    <div class="stat-card">
        <div>
            <h3>Announcements</h3>
            <p><?= $total_announcements ?></p>
            <div class="trend"><i class="fas fa-bullhorn"></i> Active</div>
        </div>
        <i class="fas fa-bullhorn stat-icon announcements"></i>
    </div>
</div>

<!-- Today's Activity -->
<h3 class="section-title"><i class="fas fa-calendar-day"></i> Today's Activity</h3>
<div class="quick-stats">
    <div class="quick-stat">
        <h4>NEW USERS</h4>
        <div class="number"><?= $today_users ?></div>
        <div class="label">Registered Today</div>
    </div>
    <div class="quick-stat">
        <h4>NEW PERMITS</h4>
        <div class="number"><?= $today_permits ?></div>
        <div class="label">Submitted Today</div>
    </div>
    <div class="quick-stat">
        <h4>NEW DOCUMENTS</h4>
        <div class="number"><?= $today_documents ?></div>
        <div class="label">Uploaded Today</div>
    </div>
    <div class="quick-stat">
        <h4>ACTIVITIES</h4>
        <div class="number"><?= $today_activities ?></div>
        <div class="label">Actions Logged</div>
    </div>
</div>

<!-- Status Overview -->
<h3 class="section-title"><i class="fas fa-chart-pie"></i> Status Overview</h3>
<div class="progress-section">
    <div class="progress-card">
        <h4><i class="fas fa-file-signature"></i> Permit Status Distribution</h4>
        <div class="progress-item">
            <div class="progress-label">
                <span class="name">Pending</span>
                <span class="value"><?= $pending_permits ?> (<?= $total_permits > 0 ? round($pending_permits/$total_permits*100) : 0 ?>%)</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill orange" style="width:<?= $total_permits > 0 ? ($pending_permits/$total_permits*100) : 0 ?>%"></div>
            </div>
        </div>
        <div class="progress-item">
            <div class="progress-label">
                <span class="name">Approved</span>
                <span class="value"><?= $approved_permits ?> (<?= $total_permits > 0 ? round($approved_permits/$total_permits*100) : 0 ?>%)</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill green" style="width:<?= $total_permits > 0 ? ($approved_permits/$total_permits*100) : 0 ?>%"></div>
            </div>
        </div>
        <div class="progress-item">
            <div class="progress-label">
                <span class="name">Rejected</span>
                <span class="value"><?= $rejected_permits ?> (<?= $total_permits > 0 ? round($rejected_permits/$total_permits*100) : 0 ?>%)</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill red" style="width:<?= $total_permits > 0 ? ($rejected_permits/$total_permits*100) : 0 ?>%"></div>
            </div>
        </div>
    </div>

    <div class="progress-card">
        <h4><i class="fas fa-file-alt"></i> Document Status Distribution</h4>
        <div class="progress-item">
            <div class="progress-label">
                <span class="name">Pending</span>
                <span class="value"><?= $pending_documents ?> (<?= $total_documents > 0 ? round($pending_documents/$total_documents*100) : 0 ?>%)</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill orange" style="width:<?php echo $total_documents > 0 ? ($pending_documents/$total_documents*100) : 0; ?>%"></div>
            </div>
        </div>
        <div class="progress-item">
            <div class="progress-label">
                <span class="name">Approved</span>
                <span class="value"><?= $approved_documents ?> (<?= $total_documents > 0 ? round($approved_documents/$total_documents*100) : 0 ?>%)</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill green" style="width:<?php echo $total_documents > 0 ? ($approved_documents/$total_documents*100) : 0; ?>%"></div>
            </div>
        </div>
        <div class="progress-item">
            <div class="progress-label">
                <span class="name">Rejected</span>
                <span class="value"><?= $rejected_documents ?> (<?= $total_documents > 0 ? round($rejected_documents/$total_documents*100) : 0 ?>%)</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill red" style="width:<?php echo $total_documents > 0 ? ($rejected_documents/$total_documents*100) : 0; ?>%"></div>
            </div>
        </div>
    </div>

    <div class="progress-card">
        <h4><i class="fas fa-tasks"></i> Task Completion Status</h4>
        <div class="progress-item">
            <div class="progress-label">
                <span class="name">Pending Tasks</span>
                <span class="value"><?= $pending_tasks ?> (<?= $total_tasks > 0 ? round($pending_tasks/$total_tasks*100) : 0 ?>%)</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill blue" style="width:<?php echo $total_tasks > 0 ? ($pending_tasks/$total_tasks*100) : 0; ?>%"></div>
            </div>
        </div>
        <div class="progress-item">
            <div class="progress-label">
                <span class="name">Completed Tasks</span>
                <span class="value"><?= $completed_tasks ?> (<?= $total_tasks > 0 ? round($completed_tasks/$total_tasks*100) : 0 ?>%)</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill green" style="width:<?php echo $total_tasks > 0 ? ($completed_tasks/$total_tasks*100) : 0; ?>%"></div>
            </div>
        </div>
    </div>
</div>

<!-- RECENT TASKS -->
<div class="card">
<h3><i class="fas fa-clock"></i> Recent User Tasks</h3>
<table>
<thead>
<tr>
<th>Task</th><th>User</th><th>Due Date</th><th>Status</th>
</tr>
</thead>
<tbody>
<?php foreach($recent_tasks as $t): ?>
<tr>
<td><?= htmlspecialchars($t['title']) ?></td>
<td><?= $t['user_id'] ?></td>
<td><?= $t['due_date'] ?></td>
<td><span class="chip <?= $t['status']=='completed'?'completed':($t['status']=='working'?'working':'pending'); ?>"><?= ucfirst($t['status']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- RECENT RESOURCES -->
<div class="card">
<h3><i class="fas fa-book"></i> Latest Legal Resources</h3>
<table>
<thead>
<tr>
<th>Title</th><th>Category</th><th>Type</th><th>Date</th>
</tr>
</thead>
<tbody>
<?php foreach($recent_resources as $r): ?>
<tr>
<td><?= htmlspecialchars($r['title']) ?></td>
<td><?= $r['category_name'] ?></td>
<td><?= $r['resource_type'] ?></td>
<td><?= $r['created_at'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

</div>
</script>

</body>
</html>
