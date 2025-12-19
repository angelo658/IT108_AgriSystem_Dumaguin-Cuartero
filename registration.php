<?php
// DATABASE CONNECTION
$host = "localhost";
$dbname = "portal";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// HANDLE FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $firstname = $conn->real_escape_string($_POST['firstname']);
    $middlename = $conn->real_escape_string($_POST['middlename']);
    $lastname = $conn->real_escape_string($_POST['lastname']);

    $street = $conn->real_escape_string($_POST['street']);
    $barangay = $conn->real_escape_string($_POST['barangay']);
    $city = $conn->real_escape_string($_POST['city']);
    $province = $conn->real_escape_string($_POST['province']);
    $region = $conn->real_escape_string($_POST['region']);

    $email = $conn->real_escape_string($_POST['email']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $hashed_pass = password_hash($password, PASSWORD_DEFAULT);

    $check_sql = "SELECT * FROM users WHERE email='$email' OR username='$username'";
    $result = $conn->query($check_sql);

    if ($result->num_rows > 0) {
        $message = "<div class='error'>Email or Username already exists!</div>";
    } else {
        $sql = "
            INSERT INTO users 
            (firstname, middlename, lastname, street, barangay, city, province, region, email, username, password)
            VALUES 
            ('$firstname', '$middlename', '$lastname', '$street', '$barangay', '$city', '$province', '$region', '$email', '$username', '$hashed_pass')
        ";

        if ($conn->query($sql) === TRUE) {
            $message = "<div class='success'>Registration successful! Redirecting...</div>";
            header("refresh:2; url=login.php");
        } else {
            $message = "<div class='error'>Error: " . $conn->error . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register | Agri-Portal</title>
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
    color: #ffffff;
}

.container {
    width: 1050px;
    max-height: 520px;
    overflow-y: auto;
    background: rgba(255,255,255,0.10);
    backdrop-filter: blur(12px);
    padding: 25px 35px;
    border-radius: 25px;
    border: 1px solid rgba(255,255,255,0.18);
    box-shadow: 0 15px 35px rgba(0,0,0,0.45);
    animation: fadeIn 0.5s ease-in-out;
    text-align: center;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.leaf-icon {
    width: 110px;
    height: 110px;
    margin: 0 auto 15px;
    display: block;
    animation: float 3s infinite ease-in-out;
}

.leaf-icon svg {
    width: 100%;
    height: 100%;
}

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-6px); }
    100% { transform: translateY(0px); }
}

h2 {
    text-align: center;
    color: #E8C67A;
    font-size: 28px;
    margin-bottom: 20px;
}

h3 {
    margin-top: 20px;
    margin-bottom: 10px;
    color: #E8C67A;
    font-size: 20px;
    border-left: 4px solid #E8C67A;
    padding-left: 10px;
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px 40px;
}

.input-group input {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.35);
    background: rgba(255,255,255,0.12);
    color: #fff;
    outline: none;
    font-size: 15px;
    transition: 0.25s;
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
    cursor: pointer;
    margin-top: 15px;
    font-weight: 600;
    transition: 0.25s;
}

input[type="submit"]:hover { background: #C9A657; }

.bottom-text {
    text-align: center;
    margin-top: 18px;
}

a { color: #FFDFA1; font-weight: bold; text-decoration: none; }
a:hover { text-decoration: underline; }

.error, .success {
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 15px;
    text-align: center;
}

.error { background: rgba(255,77,77,0.25); color: #ffcccc; }
.success { background: rgba(130,255,130,0.25); color: #d7ffd7; }
</style>
</head>
<body>

<div class="container">

    <!-- Leaf SVG icon -->
    <div class="leaf-icon">
        <svg viewBox="0 0 64 64" fill="#3AA655" xmlns="http://www.w3.org/2000/svg">
            <path d="M32 2C32 2 12 12 12 32C12 52 32 62 32 62C32 62 52 52 52 32C52 12 32 2 32 2ZM32 42C26 42 22 38 22 32C22 26 26 22 32 22C38 22 42 26 42 32C42 38 38 42 32 42Z"/>
        </svg>
    </div>

    <h2>REGISTRATION</h2>

    <?php echo $message; ?>

    <form action="" method="post">

        <h3>Personal Information</h3>

        <div class="grid-2">
            <div class="input-group"><input type="text" name="firstname" placeholder="Firstname" required></div>
            <div class="input-group"><input type="text" name="middlename" placeholder="Middlename"></div>
            <div class="input-group"><input type="text" name="lastname" placeholder="Lastname" required></div>
        </div>

        <h3>Address</h3>

        <div class="grid-2">
            <div class="input-group"><input type="text" name="street" placeholder="Street / Purok" required></div>
            <div class="input-group"><input type="text" name="barangay" placeholder="Barangay" required></div>
            <div class="input-group"><input type="text" name="city" placeholder="City / Municipality" required></div>
            <div class="input-group"><input type="text" name="province" placeholder="Province" required></div>
            <div class="input-group"><input type="text" name="region" placeholder="Region" required></div>
        </div>

        <h3>Account Information</h3>

        <div class="grid-2">
            <div class="input-group"><input type="email" name="email" placeholder="Email Address" required></div>
            <div class="input-group"><input type="text" name="username" placeholder="Username" required></div>
            <div class="input-group"><input type="password" name="password" placeholder="Password" required></div>
        </div>

        <input type="submit" value="Register">

        <p class="bottom-text">
            Already have an account?
            <a href="login.php">Login here</a>
        </p>

    </form>
</div>

</body>
</html>
