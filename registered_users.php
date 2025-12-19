<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = getPDOConnection();

/* ===== FILTER & SEARCH ===== */
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM users WHERE 1 ";
$params = [];

if($search != ''){
    $query .= " AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR contact LIKE ? OR barangay LIKE ? OR province LIKE ? OR region LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY created_at DESC ";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== DELETE USER ===== */
if(isset($_GET['delete_id'])){
    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: registered_users.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registered Users</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* =====================================
   GLOBAL RESET & BASE
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
    width: 95%;
    max-width: 1600px;
    margin: 25px auto;
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* =====================================
   CARD COMPONENT
===================================== */
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

/* =====================================
   TABLE STYLES
===================================== */
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

/* =====================================
   BUTTONS
===================================== */
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

/* =====================================
   FILTER FORM
===================================== */
.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.filter-form input,
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

</style>
</head>
<body>

<header>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h2><i class="fas fa-users"></i> Registered Users</h2>
    <nav>
      <ul>
        <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="registered_users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
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

  <!-- FILTER & SEARCH -->
  <form method="GET" class="filter-form">
    <input type="text" name="search" placeholder="Search by Name, Email, Contact, or Address" value="<?= htmlspecialchars($search) ?>">
    <button type="submit"><i class="fas fa-search"></i> Search</button>
    <a href="export_users.php?type=csv" style="background:#153B80;color:#E8C547;padding:8px 12px;border-radius:8px;text-decoration:none;margin-left:10px;"><i class="fas fa-download"></i> Export CSV</a>
  </form>

  <!-- USERS TABLE -->
  <div class="card">
    <h3><i class="fas fa-list"></i> All Registered Users</h3>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Firstname</th>
          <th>Lastname</th>
          <th>Email</th>
          <th>Contact</th>
          <th>Barngay</th>
          <th>Province</th>
          <th>Region</th>
          <th>Registered On</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if($users): ?>
          <?php foreach($users as $u): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td><?= htmlspecialchars($u['firstname']) ?></td>
              <td><?= htmlspecialchars($u['lastname']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars($u['contact'] ?? '-') ?></td>
              <td><?= htmlspecialchars($u['barangay'] ?? '-') ?></td>
              <td><?= htmlspecialchars($u['province'] ?? '-') ?></td>
              <td><?= htmlspecialchars($u['region'] ?? '-') ?></td>
              <td><?= $u['created_at'] ?></td>
              <td>
                <a class="btn-danger" href="?delete_id=<?= $u['id'] ?>" onclick="return confirm('Are you sure to delete this user?');">
                  <i class="fas fa-trash"></i> Delete
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" style="text-align:center;">No registered users found</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

</body>
</html>
