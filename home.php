<?php
session_start();
require_once 'config.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

checkSessionTimeout();

// DB connections
$conn = getDBConnection();
$pdo = getPDOConnection();

// Get user info from session
$userid = $_SESSION['user_id'];
$firstname = $_SESSION['firstname'] ?? "User";
$lastname = $_SESSION['lastname'] ?? "";
$last_login = $_SESSION['last_login'] ?? "First time login";

// Update last login timestamp
$_SESSION['last_login'] = date("Y-m-d H:i:s");

// Fetch real notifications from database
$stmt_notif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt_notif->execute([$userid]);
$notifications = $stmt_notif->fetchAll();
$unread_count = count($notifications);

// Fetch announcements from database
$stmt_ann = $pdo->prepare("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 4");
$stmt_ann->execute();
$announcements = $stmt_ann->fetchAll();

// Fetch user statistics
$pending_permits = $pdo->query("SELECT COUNT(*) FROM permits WHERE user_id = $userid AND status='Pending'")->fetchColumn();
$approved_permits = $pdo->query("SELECT COUNT(*) FROM permits WHERE user_id = $userid AND status='Approved'")->fetchColumn();
$pending_documents = $pdo->query("SELECT COUNT(*) FROM documents WHERE user_id = $userid AND status='Pending'")->fetchColumn();
$total_tasks = $pdo->query("SELECT COUNT(*) FROM compliance_tasks WHERE user_id = $userid")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Home | Agri Legal & Compliance Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* =========================================================
   GENERAL STYLES
========================================================= */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Poppins", sans-serif;
}

body {
    background: #0A1A2F;
    color: #f5f6fa;
    transition: 0.3s;
}

body.light-mode {
    background: #f4f4f4;
    color: #111;
}

/* =========================================================
   SIDEBAR
========================================================= */
.sidebar {
    background: #0A1A2F;
    box-shadow: 2px 0 8px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
    height: 100vh;
    left: 0;
    padding: 25px 20px;
    position: fixed;
    top: 0;
    width: 250px;
    z-index: 2000;
}

.sidebar .logo {
    color: #fff;
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 35px;
}

.sidebar a {
    align-items: center;
    border-radius: 8px;
    color: #d2d9e5;
    display: flex;
    font-size: 16px;
    margin-bottom: 10px;
    padding: 12px 15px;
    text-decoration: none;
    transition: 0.2s ease;
}

.sidebar a i {
    font-size: 18px;
    margin-right: 12px;
}

.sidebar a:hover {
    background: #15345A;
    color: #fff;
}

.sidebar a.active {
    background: #1E4C7A;
    color: #fff;
}

.main-content {
    margin-left: 250px;
    padding-bottom: 50px;
    position: relative;
}

/* =========================================================
   NOTIFICATION ICON
========================================================= */
.notification {
    color: #E8C547;
    cursor: pointer;
    font-size: 30px;
    position: absolute;
    right: 20px;
    top: 15px;
    transition: 0.2s;
}

.notification:hover {
    transform: scale(1.2);
}

.notification .badge {
    background: #E74C3C;
    border-radius: 50%;
    color: white;
    font-size: 14px;
    padding: 3px 7px;
    position: absolute;
    right: -12px;
    top: -5px;
}

/* =========================================================
   NOTIFICATION MODAL
========================================================= */
.notif-modal {
    background: #11243b;
    box-shadow: -4px 0 10px rgba(0,0,0,0.5);
    color: #fff;
    display: none;
    height: 100%;
    overflow-y: auto;
    position: fixed;
    right: 0;
    top: 0;
    transition: 0.3s;
    width: 320px;
    z-index: 3000;
}

.notif-modal.show {
    display: block;
}

.notif-modal-header {
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    padding: 15px;
}

.notif-close {
    cursor: pointer;
    font-size: 22px;
}

#notif-list {
    list-style: none;
    padding: 15px;
}

#notif-list li {
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 10px 0;
}

/* =========================================================
   SEARCH BAR
========================================================= */
.search-bar {
    display: flex;
    justify-content: center;
    margin: 20px 0;
}

.search-bar input {
    border: none;
    border-radius: 8px 0 0 8px;
    outline: none;
    padding: 10px 15px;
    width: 60%;
}

.search-bar button {
    background: #228b3cff;
    border: none;
    border-radius: 0 8px 8px 0;
    color: white;
    cursor: pointer;
    padding: 10px 15px;
}

/* =========================================================
   HERO SECTION
========================================================= */
.hero {
    align-items: center;
    background: #11243b;
    border-radius: 18px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.4);
    display: flex;
    gap: 40px;
    justify-content: space-between;
    margin: 20px auto 0;
    padding: 45px 5%;
    width: 90%;
}

.hero-text h1 {
    background: linear-gradient(135deg,#3AA655,#E8C547);
    -webkit-background-clip: text;
    color: transparent;
    font-size: 40px;
    font-weight: 700;
}

.hero-text p {
    color: #d1d1d1;
    font-size: 16px;
    margin: 15px 0;
}

.hero-btn {
    background: linear-gradient(135deg,#3AA655,#2e8a4f);
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,255,150,0.25);
    color: white;
    display: inline-block;
    font-weight: 700;
    margin-top: 10px;
    padding: 12px 20px;
    text-decoration: none;
    text-transform: uppercase;
    transition: 0.3s;
}

.hero-btn:hover {
    background: linear-gradient(135deg,#2e8a4f,#226c3c);
    transform: translateY(-2px);
}

.hero-img img {
    border-radius: 15px;
    box-shadow: 0 8px 18px rgba(0,0,0,0.5);
    width: 450px;
}

/* =========================================================
   TITLES
========================================================= */
.section-title {
    background: linear-gradient(135deg,#E8C547,#3AA655);
    -webkit-background-clip: text;
    color: transparent;
    font-size: 22px;
    font-weight: 700;
    margin: 25px 0 15px 10px;
}

/* =========================================================
   CARDS
========================================================= */
.quick-access {
    display: grid;
    gap: 20px;
    grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
    margin: 0 30px;
}

.card {
    background: #11243b;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.25);
    padding: 22px;
    text-align: center;
    transition: 0.3s;
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.4);
}

.card i.fa-file-signature {color:#E91E63;}
.card i.fa-clipboard-check {color:#2196F3;}
.card i.fa-folder {color:#FF9800;}
.card i.fa-scale-balanced {color:#3AA655;}

.card a {
    color: #E8C547;
    display: inline-block;
    font-weight: 600;
    margin-top: 12px;
    text-decoration: none;
    transition: 0.3s;
}

.card a:hover {
    color: #3AA655;
}

/* =========================================================
   ANNOUNCEMENTS
========================================================= */
.announcements {
    background: #11243b;
    border-left: 6px solid #E8C547;
    border-radius: 12px;
    margin: 0 30px;
    padding: 20px;
}

.announcements ul li {
    border-bottom: 1px solid rgba(255,255,255,0.10);
    color: #ddd;
    list-style: none;
    padding: 10px 0;
}

/* =========================================================
   RESPONSIVE
========================================================= */
@media(max-width:900px) {
    .hero {
        flex-direction: column;
        text-align: center;
    }

    .hero-img img {
        width: 100%;
    }
}

/* SIDEBAR ICON COLORS */
.sidebar a:nth-child(2) i { color: #5ac18e; }   /* Home */
.sidebar a:nth-child(3) i { color: #ffcc00; }   /* Compliance */
.sidebar a:nth-child(4) i { color: #8d99ae; }   /* Legal */
.sidebar a:nth-child(5) i { color: #f9844a; }   /* Permit */
.sidebar a:nth-child(6) i { color: #1E90FF; }   /* Documents */

/* =========================================================
   STATS GRID
========================================================= */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 0 30px 30px;
}

.stat-card {
    background: #11243b;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.25);
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.4);
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

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #E8C547;
}

.stat-label {
    font-size: 14px;
    color: #c7d8e0;
    margin-top: 5px;
}

</style>
</head>
<body>

<div class="sidebar">
  <div class="logo">🌿 AGRI Com</div>
    <a href="home.php" class="active"><i class="fa fa-home"></i> Home</a>
    <a href="compliance.php"><i class="fas fa-tasks"></i> Compliance Tools</a>
    <a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a>
    <a href="permit.php"><i class="fa fa-id-card"></i> Permit</a>
    <a href="documents.php"><i class="fa fa-file"></i> Documents</a>
    <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <?php if($unread_count > 0): ?><span style="background:#ff4d4d;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;"><?= $unread_count ?></span><?php endif; ?></a>
    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
    <a href="login.php" style="margin-top:auto;color:#ff7675;"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
  <div class="notification">
    <i class="fas fa-bell"></i>
    <?php if($unread_count > 0): ?>
      <span class="badge"><?php echo $unread_count; ?></span>
    <?php endif; ?>
  </div>

  <!-- Notification Modal -->
  <div id="notif-modal" class="notif-modal">
    <div class="notif-modal-header">
        <h3>Notifications</h3>
        <span id="notif-close" class="notif-close">&times;</span>
    </div>
    <ul id="notif-list">
        <?php if($unread_count > 0): ?>
            <?php foreach($notifications as $notif): ?>
                <li>
                    <div style="font-weight:600;color:#E8C547;margin-bottom:5px;"><?= htmlspecialchars($notif['title']) ?></div>
                    <div style="font-size:13px;"><?= htmlspecialchars($notif['message']) ?></div>
                    <div style="font-size:11px;color:#888;margin-top:5px;"><?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></div>
                </li>
            <?php endforeach; ?>
            <li style="text-align:center;padding-top:15px;">
                <a href="notifications.php" style="color:#3AA655;text-decoration:none;font-weight:600;">View All Notifications →</a>
            </li>
        <?php else: ?>
            <li>No new notifications.</li>
        <?php endif; ?>
    </ul>
  </div>

  <section class="hero">
    <div class="hero-text">
      <h1>Welcome, <?php echo $firstname . " " . $lastname; ?>!</h1>
      <p>Your all-in-one platform for agricultural permits, compliance monitoring, legal resources, and documentation.</p>
      <p style="font-size:14px;color:#aaa;margin-top:10px;"><i class="fas fa-clock"></i> Last Login: <?php echo date('F d, Y h:i A', strtotime($last_login)); ?></p>
      <a href="compliance.php" class="hero-btn">Get Started <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="hero-img">
      <img src="https://images.unsplash.com/photo-1501004318641-b39e6451bec6" alt="Farm">
    </div>
  </section>

  <!-- Quick Stats Dashboard -->
  <div class="container">
    <h2 class="section-title">Your Dashboard</h2>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:#E91E63;"><i class="fas fa-file-signature"></i></div>
        <div class="stat-content">
          <div class="stat-number"><?= $pending_permits ?></div>
          <div class="stat-label">Pending Permits</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#4CAF50;"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
          <div class="stat-number"><?= $approved_permits ?></div>
          <div class="stat-label">Approved Permits</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#FF9800;"><i class="fas fa-folder-open"></i></div>
        <div class="stat-content">
          <div class="stat-number"><?= $pending_documents ?></div>
          <div class="stat-label">Pending Documents</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#2196F3;"><i class="fas fa-tasks"></i></div>
        <div class="stat-content">
          <div class="stat-number"><?= $total_tasks ?></div>
          <div class="stat-label">Active Tasks</div>
        </div>
      </div>
    </div>
  </div>

  <div class="search-bar">
    <input type="text" placeholder="Search permits, documents, tools...">
    <button><i class="fas fa-search"></i></button>
  </div>

  <div class="container">
    <h2 class="section-title">Quick Access</h2>
    <div class="quick-access">
      <div class="card qa">
        <i class="fas fa-file-signature"></i>
        <h3>Apply Permit</h3>
        <p>Submit new permit applications with ease.</p>
        <a href="permit.php">Open</a>
      </div>
      <div class="card qa">
        <i class="fas fa-clipboard-check"></i>
        <h3>Compliance Tools</h3>
        <p>Monitor your progress and stay compliant.</p>
        <a href="compliance.php">Open</a>
      </div>
      <div class="card qa">
        <i class="fas fa-folder"></i>
        <h3>My Documents</h3>
        <p>View and track your submitted files.</p>
        <a href="documents.php">Open</a>
      </div>
      <div class="card qa">
        <i class="fas fa-scale-balanced"></i>
        <h3>Legal Resources</h3>
        <p>Access agricultural laws & regulations.</p>
        <a href="legal-resources.php">Open</a>
      </div>
    </div>

    <h2 class="section-title">Announcements</h2>
    <div class="announcements card">
      <ul>
        <?php if(count($announcements) > 0): ?>
            <?php foreach($announcements as $ann): ?>
                <li>
                    <i class="fas fa-bullhorn" style="color:#E8C547;margin-right:8px;"></i> 
                    <strong><?= htmlspecialchars($ann['title']) ?></strong> - <?= htmlspecialchars($ann['content']) ?>
                    <span style="font-size:11px;color:#888;margin-left:10px;">(<?= date('M d, Y', strtotime($ann['created_at'])) ?>)</span>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li><i class="fas fa-bullhorn"></i> No announcements at this time.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<script>
const notification = document.querySelector('.notification');
const modal = document.getElementById('notif-modal');
const closeBtn = document.getElementById('notif-close');
const badge = document.querySelector('.notification .badge');

notification.addEventListener('click', () => {
    modal.classList.add('show');
    if(badge) badge.style.display = 'none';
});

closeBtn.addEventListener('click', () => {
    modal.classList.remove('show');
});

window.addEventListener('click', e => {
    if(e.target == modal) modal.classList.remove('show');
});
</script>

</body>
</html>
