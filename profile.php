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

// Database
$conn = getDBConnection();
$pdo = getPDOConnection();

/* ===============================
   SAVE UPDATES (NO OTHER FILE)
================================*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fname = $_POST['firstname'];
    $mname = $_POST['middlename'];
    $lname = $_POST['lastname'];
    $street = $_POST['street'];
    $barangay = $_POST['barangay'];
    $city = $_POST['city'];
    $province = $_POST['province'];
    $region = $_POST['region'];

    $stmt = $conn->prepare("
        UPDATE users SET firstname=?, middlename=?, lastname=?, 
        street=?, barangay=?, city=?, province=?, region=? 
        WHERE id=?
    ");
    $stmt->bind_param("ssssssssi",
        $fname, $mname, $lname,
        $street, $barangay, $city, $province, $region, $user_id
    );
    $stmt->execute();
    $stmt->close();
    
    // Log activity
    logActivity($pdo, 'profile_updated', "User updated their profile information", $user_id);

    header("Location: profile.php?saved=1");
    exit;
}

/* ==========================
   FETCH USER DATA
===========================*/
$stmt = $conn->prepare("
    SELECT firstname, middlename, lastname, street, barangay, city, province, region,
           email, username, created_at
    FROM users WHERE id=?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result(
    $firstname, $middlename, $lastname, $street, $barangay, $city, $province, $region,
    $email, $username, $created_at
);
$stmt->fetch();
$stmt->close();

// Get unread notification count
$unread_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Profile | AGRO Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ===== Global Styles ===== */
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

/* ===== Sidebar ===== */
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
    transition: 0.2s;
}

.sidebar a i {
    margin-right: 12px;
    font-size: 18px;
}

/* Hover & Active */
.sidebar a:hover {
    background: #15345A;
    color: #fff;
}

.sidebar a.active {
    background: #1E4C7A;
    color: #fff;
}

/* Sidebar Icon Colors */
.sidebar a:nth-child(2) i { color: #5ac18e; }
.sidebar a:nth-child(3) i { color: #ffcc00; }
.sidebar a:nth-child(4) i { color: #8d99ae; }
.sidebar a:nth-child(5) i { color: #f9844a; }
.sidebar a:nth-child(6) i { color: #1E90FF; }

/* ===== Main Content ===== */
.content {
    margin-left: 250px;
    padding: 35px;
    position: relative; /* for absolute positioning of button */
}

/* Page Title */
.page-title {
    font-size: 26px;
    background: linear-gradient(90deg, #3AA655, #E8C547);
    -webkit-background-clip: text;
    color: transparent;
    margin-bottom: 25px;
}

/* ===== Profile Container ===== */
.profile-container {
    background: #11243b;
    padding: 25px;
    border-radius: 15px;
    width: 100%;
    max-width: 1000px;
    margin: auto;
    display: flex;
    gap: 30px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
    flex-wrap: wrap; 
}

/* Columns */
.column {
    flex: 1;
    min-width: 200px;
}

/* Info Box */
.info-box {
    margin-bottom: 15px;
}

.info-box label {
    font-size: 14px;
    color: #9db4d1;
}

.info-box input {
    width: 100%;
    padding: 10px;
    background: #0A1A2F;
    border: 1px solid #3AA655;
    border-radius: 8px;
    color: white;
    margin-top: 5px;
}

/* Save Button Top Right */
.save-btn {
    position: absolute;
    top: 35px; /* align with page padding */
    right: 35px;
    background: #3AA655;
    color: white;
    padding: 10px 25px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
}

.save-btn:hover {
    background: #2c8f48;
}

/* Success Message */
.success {
    background: #2ecc71;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 20px;
    font-size: 15px;
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
  <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <?php if($unread_count > 0): ?><span style="background:#ff4d4d;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;"><?= $unread_count ?></span><?php endif; ?></a>
  <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
  <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
  <a href="login.php" style="margin-top:auto;color:#ff7675;"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<div class="content">

<?php if (isset($_GET['saved'])): ?>
    <div class="success">Profile updated successfully ✔</div>
<?php endif; ?>

<h2 class="page-title">User Profile</h2>

<!-- Save button top right -->
<button class="save-btn" form="profile-form">Save Changes</button>

<form method="POST" id="profile-form">
<div class="profile-container">

    <!-- COLUMN 1 -->
    <div class="column">
        <div class="info-box">
            <label>First Name</label>
            <input type="text" name="firstname" value="<?= $firstname ?>">
        </div>

        <div class="info-box">
            <label>Middle Name</label>
            <input type="text" name="middlename" value="<?= $middlename ?>">
        </div>

        <div class="info-box">
            <label>Last Name</label>
            <input type="text" name="lastname" value="<?= $lastname ?>">
        </div>

        <div class="info-box">
            <label>Street</label>
            <input type="text" name="street" value="<?= $street ?>">
        </div>
    </div>

    <!-- COLUMN 2 -->
    <div class="column">
        <div class="info-box">
            <label>Barangay</label>
            <input type="text" name="barangay" value="<?= $barangay ?>">
        </div>

        <div class="info-box">
            <label>City</label>
            <input type="text" name="city" value="<?= $city ?>">
        </div>

        <div class="info-box">
            <label>Province</label>
            <input type="text" name="province" value="<?= $province ?>">
        </div>

        <div class="info-box">
            <label>Region</label>
            <input type="text" name="region" value="<?= $region ?>">
        </div>
    </div>

</div>
</form>

</div>

</body>
</html>
