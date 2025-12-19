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
$firstname = $_SESSION['firstname'] ?? "User";
$lastname = $_SESSION['lastname'] ?? "";
$_SESSION['last_login'] = date("Y-m-d H:i:s");

// DB connections
$conn = getDBConnection();
$pdo = getPDOConnection();

// Handle POST actions: submit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $permit_id = intval($_POST['permit_id'] ?? 0);

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM permits WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $permit_id, $user_id);
        if (!$stmt->execute()) {
            die("Delete Error: " . $stmt->error);
        }
        $stmt->close();
        
        // Log activity
        logActivity($pdo, 'permit_deleted', "User deleted permit ID: $permit_id", $user_id);
    } else { 
        // New permit submission
        $type = $_POST['permit_type'];
        $title = $_POST['permit_title'];
        $desc = $_POST['description'];

        $stmt = $conn->prepare("INSERT INTO permits (user_id, permit_type, permit_title, description, status, created_at)
                                VALUES (?, ?, ?, ?, 'Pending', NOW())");
        $stmt->bind_param("isss", $user_id, $type, $title, $desc);
        if (!$stmt->execute()) {
            die("Insert Error: " . $stmt->error);
        }
        $new_permit_id = $conn->insert_id;
        $stmt->close();
        
        // Log activity
        logActivity($pdo, 'permit_submitted', "User submitted new permit: $title (Type: $type)", $user_id);
        
        // Create notification for user
        createNotification($pdo, $user_id, 'Permit Submitted', "Your permit application '$title' has been submitted successfully and is pending review.", 'info');
    }

    header("Location: permit.php");
    exit;
}

// Fetch user's permits
$permits = $conn->query("SELECT * FROM permits WHERE user_id = $user_id ORDER BY created_at DESC");

// Get unread notification count
$unread_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Permit Applications | AGRO Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ===========================
   GLOBAL STYLES
=========================== */
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

/* ===========================
   SIDEBAR
=========================== */
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
    z-index: 2000;
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

.sidebar a i {
    margin-right: 12px;
    font-size: 18px;
}

.sidebar a:hover {
    background: #15345A;
    color: #fff;
}

.sidebar a.active {
    background: #1E4C7A;
    color: #fff;
}

/* ---- Sidebar Icon Colors ---- */
.sidebar a:nth-child(2) i { color: #5ac18e; }  /* Home */
.sidebar a:nth-child(3) i { color: #ffcc00; }  /* Compliance */
.sidebar a:nth-child(4) i { color: #8d99ae; }  /* Legal */
.sidebar a:nth-child(5) i { color: #f9844a; }  /* Permit */
.sidebar a:nth-child(6) i { color: #1E90FF; }  /* Documents */


/* ===========================
   MAIN CONTENT
=========================== */
.main-content {
    margin-left: 250px;
    padding: 30px 20px;
}

h2.section-title {
    font-size: 26px;
    background: linear-gradient(90deg, #3AA655, #E8C547);
    -webkit-background-clip: text;
    color: transparent;
    margin-bottom: 16px;
}

/* ===========================
   FORM CARD (NEW PERMIT)
=========================== */
.form-card {
    background: #11243b;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
    margin-bottom: 30px;
    width: 1000px;
    margin-left: 190px;
}

.form-card h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #E8C547;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: 1px solid #3AA655;
    background: #0A1A2F;
    color: #f5f6fa;
    font-size: 15px;
}

textarea {
    height: 120px;
    resize: none;
}

button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    background: #228b3cff;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    opacity: 0.9;
}

/* ===========================
   PERMIT LIST / CARDS
=========================== */
.permit-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 18px;
}

.permit-card {
    background: #11243b;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
    transition: 0.2s;
    position: relative;
}

.permit-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.5);
}

.permit-type {
    font-size: 16px;
    font-weight: 600;
    color: #3AA655;
}

.permit-title {
    font-size: 18px;
    margin-top: 5px;
    color: #E8C547;
}

.permit-date {
    font-size: 13px;
    color: #c7d8e0;
    margin-top: 5px;
}

/* ---- STATUS COLORS ---- */
.status {
    margin-top: 12px;
    padding: 6px 12px;
    display: inline-block;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
}

.status.Pending {
    background: #dcedff;
    color: #0f5099;
}

.status.Approved {
    background: #def7ed;
    color: #0d7a5f;
}

.status.Rejected {
    background: #ffe1e1;
    color: #ad0000;
}

/* ===========================
   PERMIT CARD BUTTONS
=========================== */
.permit-card form {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
    gap: 8px;
}

.permit-card form button {
    width: 100px;
}

/* ===========================
   RESPONSIVE
=========================== */
@media (max-width: 900px) {
    .main-content {
        margin-left: 0;
    }

    .form-card {
        width: 90%;
        margin-left: auto;
        margin-right: auto;
    }
}

</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">🌿 AGRI Com</div>
    <a href="home.php"><i class="fa fa-home"></i> Home</a>
    <a href="compliance.php"><i class="fas fa-tasks"></i> Compliance Tools</a>
    <a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a>
    <a href="permit.php" class="active"><i class="fa fa-id-card"></i> Permit</a>
    <a href="documents.php"><i class="fa fa-file"></i> Documents</a>
    <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <?php if($unread_count > 0): ?><span style="background:#ff4d4d;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;"><?= $unread_count ?></span><?php endif; ?></a>
    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
    <a href="login.php" style="margin-top:auto;color:#ff7675;"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">

    <h2 class="section-title">Apply for a Permit</h2>
    <div class="form-card">
        <h3><i class="fas fa-file-signature"></i> New Permit Application</h3>
        <form method="POST">
            <label>Permit Type</label>
            <select name="permit_type" required>
                <option value="">Select Type</option>
                <option>Pesticide Permit</option>
                <option>Organic Farming Certification</option>
                <option>Land Use Clearance</option>
                <option>Environmental Compliance</option>
            </select>

            <label>Permit Title</label>
            <input type="text" name="permit_title" placeholder="Ex: Organic Farm Certification 2025" required>

            <label>Description</label>
            <textarea name="description" placeholder="Describe your intention or purpose..." required></textarea>

            <button type="submit" name="action" value="submit">Submit Application</button>
        </form>
    </div>

    <h2 class="section-title">My Submitted Permits</h2>

    <div class="permit-list">
        <?php if ($permits->num_rows > 0): ?>
            <?php while($p = $permits->fetch_assoc()): ?>
                <?php $status = ucfirst(strtolower(trim($p['status']))); ?>
                <div class="permit-card">
                    <div class="permit-type"><?= htmlspecialchars($p['permit_type']) ?></div>
                    <div class="permit-title"><?= htmlspecialchars($p['permit_title']) ?></div>
                    <div class="permit-date">Submitted: <?= date("F d, Y", strtotime($p['created_at'])) ?></div>
                    <div class="status <?= $status ?>"><?= $status ?></div>

                    <form method="POST">
                        <input type="hidden" name="permit_id" value="<?= $p['id'] ?>">
                        <button type="submit" name="action" value="delete" style="background:#d9534f;">Delete</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No permits submitted yet.</p>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
