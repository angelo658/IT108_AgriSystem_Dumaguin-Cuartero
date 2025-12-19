<?php
session_start();

// DB connection
$pdo = new PDO("mysql:host=localhost;dbname=portal;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$message = "";

// Handle registration
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm']);

    if ($password !== $confirm) {
        $message = "Passwords do not match!";
    } else {
        // Insert admin user
        $stmt = $pdo->prepare("
            INSERT INTO admin_accounts (fullname, email, username, password)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$fullname, $email, $username, password_hash($password, PASSWORD_DEFAULT)]);

        $message = "Admin account successfully registered!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Registration</title>
<style>
body {
    background:#0A1A2F;
    font-family: Poppins, sans-serif;
    color:white;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}
.form-box {
    background:#153B80;
    padding:25px;
    border-radius:12px;
    width:400px;
    box-shadow:0 0 15px rgba(0,0,0,0.4);
}
.form-box h2 {
    text-align:center;
    color:#E8C547;
    margin-bottom:15px;
}
input, button {
    width:95%;
    padding:10px;
    margin-top:8px;
    margin-bottom: 10px;
    border-radius:6px;
    border:none;
}
button {
    background:#E8C547;
    color:black;
    font-weight:600;
    width: 100%;
    cursor:pointer;
}
button:hover {
    opacity:0.85;
}
.message {
    text-align:center;
    margin-top:15px;
    color:#ffeb3b;
}

/* Login Link Style */
.login-link {
    text-align:center;
    margin-top:10px;
    font-size:14px;
}

.login-link a {
    color:#E8C547;
    text-decoration:none;
    font-weight:600;
}

.login-link a:hover {
    text-decoration:underline;
}
</style>
</head>
<body>

<div class="form-box">
    <h2>Admin Registration</h2>

    <?php if ($message): ?>
    <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Full Name</label>
        <input type="text" name="fullname" placeholder="Enter full name" required>

        <label>Email</label>
        <input type="email" name="email" placeholder="Enter email" required>

        <label>Username</label>
        <input type="text" name="username" placeholder="Enter username" required>

        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password" required>

        <label>Confirm Password</label>
        <input type="password" name="confirm" placeholder="Re-enter password" required>

        <button type="submit">Register</button>
    </form>

    <!-- NEW LOGIN LINK -->
    <div class="login-link">
        Already have an account? <a href="admin_login.php">Login here</a>
    </div>

</div>

</body>
</html>
