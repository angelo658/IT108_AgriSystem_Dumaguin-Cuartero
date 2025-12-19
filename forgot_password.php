<?php
require_once 'config.php';

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = sanitizeInput($_POST['email']);
    $user_type = sanitizeInput($_POST['user_type']);
    
    $conn = getPDOConnection();
    
    // Check if email exists
    $table = ($user_type === 'admin') ? 'admin_accounts' : 'users';
    $stmt = $conn->prepare("SELECT id, email FROM $table WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Generate reset token
        $token = generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Save token to database
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $token, $user_type, $expires_at]);
        
        // Create reset link
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/PORTAL/PHP/reset_password.php?token=" . $token;
        
        // In production, send email here
        // For now, display the link
        $message = "Password reset link: <a href='$reset_link' target='_blank'>Click here to reset</a><br>Link expires in 1 hour.";
        $message_type = "success";
        
        // Log activity
        logActivity($conn, 'password_reset_request', "Password reset requested for email: $email", $user['id'] ?? null, null);
    } else {
        $message = "Email address not found.";
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password | <?php echo SITE_NAME; ?></title>
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
input[type="email"], select {
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
select option {
    background: #0D2A45;
    color: white;
}
input:focus, select:focus {
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
</style>
</head>
<body>

<div class="reset-box">
    <div class="icon-wrapper">
        <i class="fas fa-lock"></i>
    </div>
    <h2>Forgot Password?</h2>
    <p class="description">Enter your email address and we'll send you a link to reset your password.</p>
    
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <label>Account Type</label>
        <select name="user_type" required>
            <option value="user">User Account</option>
            <option value="admin">Admin Account</option>
        </select>
        
        <label>Email Address</label>
        <input type="email" name="email" placeholder="Enter your email" required>
        
        <button type="submit"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
    </form>
    
    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>

</body>
</html>
