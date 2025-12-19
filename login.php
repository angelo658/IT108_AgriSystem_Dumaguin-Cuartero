<?php
session_start();

// DATABASE CONNECTION
$host = "localhost";
$dbname = "portal";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// HANDLE LOGIN
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['firstname'] = $row['firstname']; // optional for welcome message
            $_SESSION['lastname'] = $row['lastname'];   // optional for welcome message
            
            // Redirect to new dashboard
            header("refresh:1; url=home.php");
            $message = "<div class='success'>Login successful! Redirecting...</div>";
        } else {
            $message = "<div class='error'>Incorrect password!</div>";
        }
    } else {
        $message = "<div class='error'>Username not found!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | Portal</title>
<style>
body {
    margin: 0;
    padding: 0;
    font-family: "Segoe UI", sans-serif;
    background: linear-gradient(135deg, #081525, #0D2A45);
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.login-box {
    width: 430px;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(12px);
    padding: 45px 38px;
    border-radius: 22px;
    border: 1px solid rgba(255,255,255,0.15);
    box-shadow: 0 15px 40px rgba(0,0,0,0.45);
    animation: fadeIn 0.6s ease-in-out;
    text-align: center;
}
.leaf-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    display: block;
    animation: float 3s infinite ease-in-out;
}
@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-6px); }
    100% { transform: translateY(0px); }
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
h2 {
    margin-bottom: 22px;
    color: #E8C67A;
    font-weight: 600;
    letter-spacing: 1px;
    font-size: 26px;
}
input[type="text"],
input[type="password"] {
    width: 93.2%;
    padding: 14px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.35);
    background: rgba(255,255,255,0.12);
    color: white;
    font-size: 15px;
    outline: none;
    transition: 0.25s;
    margin-bottom: 15px;
}
input::placeholder { color: rgba(230,230,230,0.7); }
input:focus {
    border-color: #E8C67A;
    box-shadow: 0 0 8px rgba(232,198,122,0.6);
}
input[type="submit"] {
    width: 100%;
    padding: 14px;
    background: #E8C67A;
    color: #0A1A2F;
    border: none;
    border-radius: 12px;
    font-size: 17px;
    margin-top: 10px;
    cursor: pointer;
    font-weight: 600;
    transition: 0.25s;
}
input[type="submit"]:hover { background: #C9A657; }
.bottom-text {
    margin-top: 18px;
    font-size: 14px;
    color: #E8C67A;
}
a { color: #FFDFA1; font-weight: bold; text-decoration: none; }
a:hover { text-decoration: underline; }
.error, .success {
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 10px;
    font-size: 14px;
    text-align: center;
}
.error { background: rgba(255, 77, 77, 0.25); color: #ffcccc; }
.success { background: rgba(130, 255, 130, 0.25); color: #d7ffd7; }
</style>
</head>
<body>

<div class="login-box">
    <!-- Leaf SVG icon -->
    <svg class="leaf-icon" viewBox="0 0 64 64" fill="#3AA655" xmlns="http://www.w3.org/2000/svg">
        <path d="M32 2C32 2 12 12 12 32C12 52 32 62 32 62C32 62 52 52 52 32C52 12 32 2 32 2ZM32 42C26 42 22 38 22 32C22 26 26 22 32 22C38 22 42 26 42 32C42 38 38 42 32 42Z"/>
    </svg>

    <h2>LOGIN</h2>

    <?php echo $message; ?>

    <form action="" method="post">
        <input type="text" name="username" placeholder="Enter username" required>
        <input type="password" name="password" placeholder="Enter password" required>
        <input type="submit" value="Login">
    </form>

    <p class="bottom-text">
        Don't have an account? <a href="registration.php">Register here</a><br>
        <a href="forgot_password.php">Forgot Password?</a>
    </p>
</div>

</body>
</html>
