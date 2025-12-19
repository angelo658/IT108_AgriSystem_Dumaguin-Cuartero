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
$conn = getDBConnection();
$pdo = getPDOConnection();
$message = "";
$message_type = "";

// Get unread notification count
$unread_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetchColumn();

// Handle password change
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Fetch current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!password_verify($current_password, $user['password'])) {
        $message = "Current password is incorrect.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $message_type = "error";
    } elseif (strlen($new_password) < 6) {
        $message = "New password must be at least 6 characters long.";
        $message_type = "error";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            logActivity($conn, 'password_changed', "User changed their password", $user_id, null);
            $message = "Password changed successfully!";
            $message_type = "success";
        } else {
            $message = "Error changing password. Please try again.";
            $message_type = "error";
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password | <?php echo SITE_NAME; ?></title>
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
    margin-bottom: 30px;
}
.card {
    background: #11243b;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    max-width: 600px;
}
.card h3 {
    font-size: 20px;
    color: #E8C547;
    margin-bottom: 20px;
}
.form-group {
    margin-bottom: 20px;
}
label {
    display: block;
    margin-bottom: 8px;
    color: #E8C547;
    font-weight: 500;
}
input[type="password"] {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #3AA655;
    background: #0A1A2F;
    color: white;
    font-size: 15px;
    outline: none;
}
input:focus {
    border-color: #E8C547;
    box-shadow: 0 0 8px rgba(232,197,71,0.4);
}
button {
    padding: 12px 30px;
    background: #3AA655;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}
button:hover {background: #2c8f48;}
.message {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-size: 14px;
}
.message.success {
    background: rgba(58,166,85,0.2);
    color: #3AA655;
    border: 1px solid #3AA655;
}
.message.error {
    background: rgba(255,77,77,0.2);
    color: #ff4d4d;
    border: 1px solid #ff4d4d;
}
.password-requirements {
    font-size: 12px;
    color: #aaa;
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
    <h2 class="page-title">Change Password</h2>
    
    <div class="card">
        <h3><i class="fas fa-lock"></i> Update Your Password</h3>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" placeholder="Enter current password" required>
            </div>
            
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password" required minlength="6">
                <div class="password-requirements">
                    <i class="fas fa-info-circle"></i> Password must be at least 6 characters long.
                </div>
            </div>
            
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="6">
            </div>
            
            <button type="submit" name="change_password">
                <i class="fas fa-check"></i> Change Password
            </button>
        </form>
    </div>
</div>

</body>
</html>
