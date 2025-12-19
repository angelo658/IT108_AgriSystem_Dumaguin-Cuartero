<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = getPDOConnection();

// Filters
$filter_type = $_GET['type'] ?? '';
$filter_date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT al.*, u.username as user_username, aa.username as admin_username 
          FROM activity_logs al 
          LEFT JOIN users u ON al.user_id = u.id 
          LEFT JOIN admin_accounts aa ON al.admin_id = aa.id 
          WHERE 1=1";

$params = [];

if ($filter_type) {
    $query .= " AND al.action_type = ?";
    $params[] = $filter_type;
}

if ($filter_date) {
    $query .= " AND DATE(al.created_at) = ?";
    $params[] = $filter_date;
}

if ($search) {
    $query .= " AND (al.action_details LIKE ? OR u.username LIKE ? OR aa.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY al.created_at DESC LIMIT 100";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique action types for filter
$action_types = $conn->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs | <?php echo SITE_NAME; ?></title>
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
    width: 95%;
    max-width: 1600px;
    margin: 25px auto;
}

/* =========================
   FILTER FORM
========================= */
.filter-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-form input,
.filter-form select,
.filter-form button {
    padding: 10px 15px;
    border-radius: 8px;
    border: none;
    outline: none;
    font-size: 14px;
}

.filter-form input,
.filter-form select {
    background: #11243b;
    color: white;
    border: 1px solid #3AA655;
}

.filter-form button {
    background: #3AA655;
    color: white;
    cursor: pointer;
    transition: 0.3s;
}

.filter-form button:hover {
    background: #2c8f48;
}

/* =========================
   CARD COMPONENT
========================= */
.card {
    background: #11243b;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    border: 1px solid rgba(58,166,85,0.1);
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

/* =========================
   TABLE STYLES
========================= */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th,
td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: #f5f6fa;
    font-size: 13px;
}

th {
    background: rgba(21,59,128,0.5);
    color: #E8C547;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
}

tr:hover {
    background: rgba(58,166,85,0.08);
}

/* =========================
   ACTION TYPES / BADGES
========================= */
.action-type {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.action-type.login    { background: #2196f3; color: white; }
.action-type.logout   { background: #9e9e9e; color: white; }
.action-type.create   { background: #4caf50; color: white; }
.action-type.update   { background: #ff9800; color: white; }
.action-type.delete   { background: #f44336; color: white; }
.action-type.export   { background: #9c27b0; color: white; }
.action-type.default  { background: #607d8b; color: white; }

/* =========================
   EXPORT BUTTON
========================= */
.export-btn {
    background: #153B80;
    color: #E8C547;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-block;
    margin-left: 10px;
    transition: 0.3s;
}

.export-btn:hover {
    background: #1E4C7A;
}

</style>
</head>
<body>

<header>
<div style="display:flex;justify-content:space-between;align-items:center;">
<h2><i class="fas fa-history"></i> Activity Logs</h2>
<nav>
<ul>
<li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
<li><a href="registered_users.php"><i class="fas fa-users"></i> Users</a></li>
<li><a href="user_permit.php"><i class="fas fa-file-signature"></i> Permits</a></li>
<li><a href="user_documents.php"><i class="fas fa-file-alt"></i> Documents</a></li>
<li><a href="user_compliance_tasks.php"><i class="fas fa-tasks"></i> Compliance</a></li>
<li><a href="user_legal_resources.php"><i class="fas fa-book"></i> Resources</a></li>
<li><a href="activity_logs.php" class="active"><i class="fas fa-history"></i> Logs</a></li>
<li><a href="manage_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
<li><a href="admin_login.php" style="color:#ff4d4d;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</nav>
</div>
</header>

<div class="container">
    <form method="GET" class="filter-form">
        <select name="type">
            <option value="">All Action Types</option>
            <?php foreach($action_types as $type): ?>
                <option value="<?= $type ?>" <?= $filter_type == $type ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $type)) ?></option>
            <?php endforeach; ?>
        </select>
        
        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
        <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit"><i class="fas fa-search"></i> Filter</button>
        <a href="activity_logs.php" class="filter-form button" style="background:#9e9e9e;color:white;padding:10px 15px;border-radius:8px;text-decoration:none;"><i class="fas fa-redo"></i> Reset</a>
    </form>

    <div class="card">
        <h3><i class="fas fa-list"></i> Activity Logs (Last 100 entries)</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date/Time</th>
                    <th>Action Type</th>
                    <th>Details</th>
                    <th>User</th>
                    <th>Admin</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($logs) > 0): ?>
                    <?php foreach($logs as $log): 
                        $action_class = 'default';
                        if(strpos($log['action_type'], 'login') !== false) $action_class = 'login';
                        elseif(strpos($log['action_type'], 'logout') !== false) $action_class = 'logout';
                        elseif(strpos($log['action_type'], 'create') !== false || strpos($log['action_type'], 'add') !== false) $action_class = 'create';
                        elseif(strpos($log['action_type'], 'update') !== false || strpos($log['action_type'], 'edit') !== false) $action_class = 'update';
                        elseif(strpos($log['action_type'], 'delete') !== false) $action_class = 'delete';
                        elseif(strpos($log['action_type'], 'export') !== false) $action_class = 'export';
                    ?>
                    <tr>
                        <td><?= $log['id'] ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></td>
                        <td>
                            <span class="action-type <?= $action_class ?>">
                                <?= ucfirst(str_replace('_', ' ', $log['action_type'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['action_details']) ?></td>
                        <td><?= $log['user_username'] ?? '-' ?></td>
                        <td><?= $log['admin_username'] ?? '-' ?></td>
                        <td><?= $log['ip_address'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:30px;">No activity logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
