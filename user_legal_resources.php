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

/* ===== FETCH ALL LEGAL RESOURCES ===== */
$resources = $pdo->query("
    SELECT lr.*, lc.category_name 
    FROM legal_resources lr
    LEFT JOIN legal_categories lc ON lr.category_id = lc.id
    ORDER BY lr.created_at DESC
")->fetchAll();

/* ===== INSERT NEW RESOURCE ===== */
if(isset($_POST['add_resource'])){
    $stmt = $pdo->prepare("
        INSERT INTO legal_resources (title, description, category_id, resource_type, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $_POST['title'],
        $_POST['description'],
        $_POST['category_id'],
        $_POST['resource_type']
    ]);
    header("Location: user_legal_resources.php");
    exit;
}

/* ===== DELETE RESOURCE ===== */
if(isset($_GET['delete_id'])){
    $stmt = $pdo->prepare("DELETE FROM legal_resources WHERE id=?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: user_legal_resources.php");
    exit;
}

/* ===== FETCH CATEGORY LIST FOR DROPDOWN ===== */
$categories = $pdo->query("SELECT * FROM legal_categories")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Legal Resources - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* =========================================
   GLOBAL STYLES
========================================= */
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

/* =========================================
   LAYOUT CONTAINER
========================================= */
.container {
    width: 70%;
    max-width: 1400px;
    margin: 30px auto;
    display: flex;
    flex-direction: column;
    gap: 30px;
}

/* =========================================
   CARD STYLE
========================================= */
.card {
    background: #11243b;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
    transition: 0.3s;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.5);
}

.card h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #E8C547;
}

/* =========================================
   FORM ELEMENTS
========================================= */
input,
select,
textarea {
    width: 100%;
    padding: 12px;
    margin: 8px 0 15px;
    border-radius: 10px;
    border: 1px solid #ccc;
    font-size: 15px;
    outline: none;
}

/* Placeholder color */
input::placeholder,
textarea::placeholder,
select::placeholder {
    color: #999;
    opacity: 1;
}

button {
    background: #02661bff;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 15px;
    transition: 0.3s;
}

button:hover {
    background: #2E7D4A;
}

/* =========================================
   TABLE STYLE
========================================= */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th,
td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: #f5f6fa;
}

th {
    background: #153B80;
    color: #E8C547;
    font-weight: 500;
}

tr:hover {
    background: rgba(58, 166, 85, 0.1);
}

/* =========================================
   DELETE BUTTON
========================================= */
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

/* =========================================
   LABEL INDICATORS (Optional/Required)
========================================= */
.required {
    color: red;
    margin-left: 4px;
}

.optional {
    color: #bbb;
    font-size: 13px;
    margin-left: 4px;
}

</style>
</head>
<body>

<header>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h2><i class="fas fa-book"></i> User Legal Resources</h2>
    <nav>
      <ul>
        <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="registered_users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="user_permit.php"><i class="fas fa-file-signature"></i> Permits</a></li>
        <li><a href="user_documents.php"><i class="fas fa-file-alt"></i> Documents</a></li>
        <li><a href="user_compliance_tasks.php"><i class="fas fa-tasks"></i> Compliance</a></li>
        <li><a href="user_legal_resources.php" class="active"><i class="fas fa-book"></i> Resources</a></li>
        <li><a href="activity_logs.php"><i class="fas fa-history"></i> Logs</a></li>
        <li><a href="manage_announcements.php" ><i class="fas fa-bullhorn"></i> Announcements</a></li>
        <li><a href="admin_login.php" style="color:#ff4d4d;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </nav>
  </div>
</header>

<div class="container">

  <!-- ADD NEW RESOURCE -->
  <div class="card">
      <h3><i class="fas fa-plus-circle"></i> Add New Legal Resource</h3>
      <form method="post">
          <label>Title <span class="required">*</span></label>
          <input type="text" name="title" placeholder="Enter resource title" required>

          <label>Description <span class="required">*</span></label>
          <textarea name="description" placeholder="Enter resource description" required></textarea>

          <label>Category <span class="required">*</span></label>
          <select name="category_id" required>
              <option value="" disabled selected>Select category</option>
              <?php foreach($categories as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
              <?php endforeach; ?>
          </select>

          <label>Resource Type <span class="required">*</span></label>
          <select name="resource_type" required>
              <option value="" disabled selected>Select type</option>
              <option value="law">Law</option>
              <option value="regulation">Regulation</option>
              <option value="policy">Policy</option>
              <option value="guideline">Guideline</option>
              <option value="amendment">Amendment</option>
          </select>

          <button type="submit" name="add_resource"><i class="fas fa-plus"></i> Add Resource</button>
      </form>
  </div>


  <!-- ALL RESOURCES -->
  <div class="card">
      <h3><i class="fas fa-list"></i> All Legal Resources</h3>
      <table>
          <thead>
              <tr>
                  <th>Title</th>
                  <th>Category</th>
                  <th>Type</th>
                  <th>Date</th>
                  <th>Action</th>
              </tr>
          </thead>
          <tbody>
              <?php if($resources): ?>
                  <?php foreach($resources as $r): ?>
                      <tr>
                          <td><?= htmlspecialchars($r['title']) ?></td>
                          <td><?= htmlspecialchars($r['category_name']) ?></td>
                          <td><?= htmlspecialchars($r['resource_type']) ?></td>
                          <td><?= $r['created_at'] ?></td>
                          <td>
                              <a class="btn-danger" href="?delete_id=<?= $r['id'] ?>"><i class="fas fa-trash"></i> Delete</a>
                          </td>
                      </tr>
                  <?php endforeach; ?>
              <?php else: ?>
                  <tr>
                      <td colspan="5" style="text-align:center;">No legal resources found</td>
                  </tr>
              <?php endif; ?>
          </tbody>
      </table>
  </div>

</div>

</body>
</html>
