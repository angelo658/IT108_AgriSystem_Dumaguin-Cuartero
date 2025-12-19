<?php
session_start();
$pdo = new PDO("mysql:host=localhost;dbname=portal;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

if($_SERVER['REQUEST_METHOD']=='POST'){
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM admin_accounts WHERE username=?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if($admin && password_verify($password, $admin['password'])){
        $_SESSION['admin_id'] = $admin['id'];
        header("Location: dashboard.php");
        exit;
    }else{
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ===== GENERAL ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:"Poppins",sans-serif;}
body{
    height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background: linear-gradient(135deg, #0a1a3c, #0f3460);
}

/* ===== LOGIN CARD ===== */
.login-card{
    background:#153B80;
    padding:30px 40px;
    border-radius:15px;
    box-shadow:0 6px 18px rgba(0,0,0,0.35);
    width:100%;
    max-width:400px;
    text-align:center;
    transition:0.3s;
}
.login-card:hover{
    transform:translateY(-3px);
    box-shadow:0 8px 22px rgba(0,0,0,0.4);
}
.login-card h2{
    margin-bottom:20px;
    color:#E8C547; /* dark blue accent */
}

/* ===== FORM ELEMENTS ===== */
input{
    width:100%;
    padding:12px;
    margin:10px 0 20px;
    border-radius:10px;
    border:1px solid #b0b0b0;
    font-size:15px;
    outline:none;
}
input:focus{
    border:1px solid #0f4c81;
    box-shadow:0 0 4px #0f4c81aa;
}

/* BUTTON */
button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    background:#ffcc00; /* yellow */
    color:#333;         /* dark text */
    font-weight:600;
    font-size:15px;
    cursor:pointer;
    transition:0.3s;
}
button:hover{
    background:#ffdf66; /* light yellow on hover */
}

/* ===== ERROR MESSAGE ===== */
.error{
    background:#ff4d4d33;
    color:#b71c1c;
    padding:10px 12px;
    border-radius:8px;
    margin-bottom:15px;
}

/* ===== REGISTER LINK ===== */
.register-link{
    display:block;
    margin-top:15px;
    color: white;
    text-decoration:none;
    font-size:14px;
}
.register-link:hover{
    text-decoration:underline;
}
</style>

</head>
<body>

<div class="login-card">
    <h2><i class="fas fa-user-shield"></i> Admin Login</h2>

    <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>

    <form method="post">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
    </form>

    <a href="admin_registration.php" class="register-link"><i class="fas fa-user-plus"></i> Register New Admin</a>
</div>

</body>
</html>
