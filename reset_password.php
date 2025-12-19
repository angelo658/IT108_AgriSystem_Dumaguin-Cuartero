<?php
require_once 'config.php';

$message = "";
$message_type = "";
$valid_token = false;
$token = "";

if (isset($_GET['token'])) {
    $token = sanitizeInput($_GET['token']);
    $conn = getPDOConnection();
    
    // Verify token
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();
    
    if ($reset_request) {
        $valid_token = true;
    } else {
        $message = "Invalid or expired reset link.";
        $message_type = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_password'])) {
    $token = sanitizeInput($_POST['token']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $conn = getPDOConnection();
    
    // Verify token again
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();
    
    if (!$reset_request) {
        $message = "Invalid or expired reset link.";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
        $valid_token = true;
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "error";
        $valid_token = true;
    } else {
        // Update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $table = ($reset_request['user_type'] === 'admin') ? 'admin_accounts' : 'users';
        
        $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $reset_request['email']]);
        
        // Delete used token
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        
        // Log activity
        logActivity($conn, 'password_reset_completed', "Password reset completed for email: " . $reset_request['email']);
        
        $message = "Password reset successful! You can now login with your new password.";
        $message_type = "success";
        $valid_token = false;
        
        // Redirect to login after 3 seconds
        header("refresh:3;url=login.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password | <?php echo SITE_NAME; ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0;padding:0;box-sizing:border-box;font-family:"Poppins",sans-serif;}
body {
    background: linear-gradient(135deg, #081525, #0D2A45);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.reset-box {
    width: 450px;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(12px);
    padding: 40px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.15);
    box-shadow: 0 15px 40px rgba(0,0,0,0.45);
}
.icon-wrapper {
    text-align: center;
    margin-bottom: 20px;
}
.icon-wrapper i {
    font-size: 60px;
    color: #E8C67A;
}
h2 {
    text-align: center;
    margin-bottom: 10px;
    color: #E8C67A;
    font-size: 26px;
}
.description {
    text-align: center;
    margin-bottom: 25px;
    color: #ccc;
    font-size: 14px;
}
label {
    display: block;
    margin-bottom: 8px;
    color: #E8C67A;
    font-weight: 500;
}
input[type="password"] {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.35);
    background: rgba(255,255,255,0.12);
    color: white;
    font-size: 15px;
    outline: none;
    margin-bottom: 15px;
}
input:focus {
    border-color: #E8C67A;
    box-shadow: 0 0 8px rgba(232,198,122,0.6);
}
input::placeholder {color: rgba(230,230,230,0.7);}
button {
    width: 100%;
    padding: 12px;
    background: #E8C67A;
    color: #0A1A2F;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}
button:hover {background: #C9A657;}
.back-link {
    text-align: center;
    margin-top: 20px;
}
.back-link a {
    color: #E8C67A;
    text-decoration: none;
    font-size: 14px;
}
.back-link a:hover {text-decoration: underline;}
.message {
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 10px;
    font-size: 14px;
    text-align: center;
}
.message.success {
    background: rgba(130, 255, 130, 0.25);
    color: #d7ffd7;
    border: 1px solid rgba(130, 255, 130, 0.5);
}
.message.error {
    background: rgba(255, 77, 77, 0.25);
    color: #ffcccc;
    border: 1px solid rgba(255, 77, 77, 0.5);
}
.password-requirements {
    font-size: 12px;
    color: #aaa;
    margin-bottom: 15px;
    padding-left: 5px;
}
</style>
</head>
<body>

<div class="reset-box">
    <div class="icon-wrapper">
        <i class="fas fa-key"></i>
    </div>
    <h2>Reset Password</h2>
    
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($valid_token): ?>
        <p class="description">Enter your new password below.</p>
        
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <label>New Password</label>
            <input type="password" name="password" placeholder="Enter new password" required minlength="6">
            
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="6">
            
            <div class="password-requirements">
                <i class="fas fa-info-circle"></i> Password must be at least 6 characters long.
            </div>
            
            <button type="submit" name="reset_password"><i class="fas fa-check"></i> Reset Password</button>
        </form>
    <?php else: ?>
        <p class="description">The reset link is invalid or has expired. Please request a new one.</p>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>

</body>
</html>
