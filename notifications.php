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
$conn = getPDOConnection();

// Mark as read
if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    header("Location: notifications.php");
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: notifications.php");
    exit;
}

// Fetch notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$unread_count = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_count->execute([$user_id]);
$unread = $unread_count->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | <?php echo SITE_NAME; ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0;padding:0;box-sizing:border-box;font-family:"Poppins",sans-serif;}
body {background: #0A1A2F;color: #f5f6fa;}

/* Sidebar */
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
.sidebar a i {margin-right: 12px;font-size: 18px;}
.sidebar a:hover {background: #15345A;color: #fff;}
.sidebar a.active {background: #1E4C7A;color: #fff;}

/* Main Content */
.main-content {
    margin-left: 270px;
    padding: 35px;
}
.page-title {
    font-size: 28px;
    color: #E8C547;
    margin-bottom: 10px;
}
.subtitle {
    color: #aaa;
    margin-bottom: 30px;
}
.action-bar {
    margin-bottom: 20px;
}
.btn {
    padding: 10px 20px;
    background: #3AA655;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: 0.3s;
}
.btn:hover {background: #2c8f48;}
.notif-card {
    background: #11243b;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    margin-bottom: 15px;
    border-left: 4px solid #3AA655;
    display: flex;
    justify-content: space-between;
    align-items: start;
}
.notif-card.unread {
    background: #153B80;
    border-left-color: #E8C547;
}
.notif-card.info {border-left-color: #2196f3;}
.notif-card.success {border-left-color: #4caf50;}
.notif-card.warning {border-left-color: #ff9800;}
.notif-card.danger {border-left-color: #f44336;}
.notif-content h4 {
    font-size: 16px;
    color: #E8C547;
    margin-bottom: 8px;
}
.notif-content p {
    font-size: 14px;
    color: #ccc;
    margin-bottom: 5px;
}
.notif-meta {
    font-size: 12px;
    color: #888;
}
.notif-actions {
    display: flex;
    gap: 10px;
    flex-direction: column;
}
.mark-read-btn {
    padding: 6px 12px;
    background: #3AA655;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
}
.mark-read-btn:hover {background: #2c8f48;}
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
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">🌿 AGRI Com</div>
    <a href="home.php"><i class="fa fa-home"></i> Home</a>
    <a href="compliance.php"><i class="fas fa-tasks"></i> Compliance Tools</a>
    <a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a>
    <a href="permit.php"><i class="fa fa-id-card"></i> Permit</a>
    <a href="documents.php"><i class="fa fa-file"></i> Documents</a>
    <a href="notifications.php" class="active"><i class="fas fa-bell"></i> Notifications <?php if($unread > 0): ?><span style="background:#ff4d4d;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;"><?= $unread ?></span><?php endif; ?></a>
    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
    <a href="login.php" style="margin-top:auto;color:#ff7675;"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    <h2 class="page-title"><i class="fas fa-bell"></i> Notifications</h2>
    <p class="subtitle">You have <?= $unread ?> unread notification<?= $unread != 1 ? 's' : '' ?></p>
    
    <div class="action-bar">
        <?php if ($unread > 0): ?>
            <a href="?mark_all_read=1" class="btn"><i class="fas fa-check-double"></i> Mark All as Read</a>
        <?php endif; ?>
    </div>

    <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $notif): ?>
            <div class="notif-card <?= $notif['is_read'] ? '' : 'unread' ?> <?= $notif['type'] ?>">
                <div class="notif-content">
                    <h4><?= htmlspecialchars($notif['title']) ?></h4>
                    <p><?= htmlspecialchars($notif['message']) ?></p>
                    <div class="notif-meta">
                        <i class="fas fa-clock"></i> <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                    </div>
                </div>
                <div class="notif-actions">
                    <?php if (!$notif['is_read']): ?>
                        <a href="?mark_read=<?= $notif['id'] ?>" class="mark-read-btn">
                            <i class="fas fa-check"></i> Mark Read
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h3>No Notifications</h3>
            <p>You don't have any notifications yet.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
