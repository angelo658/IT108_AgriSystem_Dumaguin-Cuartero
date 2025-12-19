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
$last_login = $_SESSION['last_login'] ?? "First time login";
$_SESSION['last_login'] = date("Y-m-d H:i:s");

// DB
$conn = getDBConnection();
$pdo = getPDOConnection();

// Handle file upload, cancel, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_FILES['doc_file'])) {
        // Upload new document
        $doc_title = $_POST['doc_title'];
        $doc_type = $_POST['doc_type'];

        $file_name = $_FILES['doc_file']['name'];
        $tmp = $_FILES['doc_file']['tmp_name'];

        $destination = "uploads/" . time() . "_" . $file_name;
        move_uploaded_file($tmp, $destination);

        $stmt = $conn->prepare("INSERT INTO documents (user_id, doc_title, doc_type, file_path, status, uploaded_at)
                                VALUES (?, ?, ?, ?, 'Pending', NOW())");
        $stmt->bind_param("isss", $user_id, $doc_title, $doc_type, $destination);
        $stmt->execute();
        $new_doc_id = $conn->insert_id;
        $stmt->close();
        
        // Log activity
        logActivity($pdo, 'document_uploaded', "User uploaded document: $doc_title (Type: $doc_type)", $user_id);
        
        // Create notification
        createNotification($pdo, $user_id, 'Document Uploaded', "Your document '$doc_title' has been uploaded successfully and is pending review.", 'info');
        
        header("Location: documents.php?uploaded=1");
        exit;

    } elseif (isset($_POST['action']) && isset($_POST['doc_id'])) {
        $doc_id = intval($_POST['doc_id']);
        $action = $_POST['action'];

        if ($action === 'cancel') {
            // Get document title for notification
            $doc_result = $conn->query("SELECT doc_title FROM documents WHERE id=$doc_id AND user_id=$user_id");
            $doc_data = $doc_result->fetch_assoc();
            
            $stmt = $conn->prepare("UPDATE documents SET status='Cancelled' WHERE id=? AND user_id=?");
            $stmt->bind_param("ii", $doc_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Log activity
            logActivity($pdo, 'document_cancelled', "User cancelled document ID: $doc_id - {$doc_data['doc_title']}", $user_id);
            
            // Create notification
            if ($doc_data) {
                createNotification($pdo, $user_id, 'Document Cancelled', "You have cancelled the document '{$doc_data['doc_title']}'.", 'info');
            }
        } elseif ($action === 'delete') {
            // Delete file physically
            $file = $conn->query("SELECT file_path FROM documents WHERE id=$doc_id AND user_id=$user_id")->fetch_assoc();
            if ($file && file_exists($file['file_path'])) unlink($file['file_path']);

            $stmt = $conn->prepare("DELETE FROM documents WHERE id=? AND user_id=?");
            $stmt->bind_param("ii", $doc_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Log activity
            logActivity($pdo, 'document_deleted', "User deleted document ID: $doc_id", $user_id);
        }
        header("Location: documents.php");
        exit;
    }
}

// Fetch user documents
$docs = $conn->query("SELECT * FROM documents WHERE user_id = $user_id ORDER BY uploaded_at DESC");

// Get unread notification count
$unread_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Documents | Agri Legal & Compliance Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* =========================
   GENERAL
   ========================= */
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

/* =========================
   SIDEBAR
   ========================= */
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
    box-shadow: 2px 0 8px rgba(0,0,0,0.3);
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

/* Sidebar icon colors */
.sidebar a:nth-child(2) i { color: #5ac18e; }
.sidebar a:nth-child(3) i { color: #ffcc00; }
.sidebar a:nth-child(4) i { color: #8d99ae; }
.sidebar a:nth-child(5) i { color: #f9844a; }
.sidebar a:nth-child(6) i { color: #1E90FF; }

/* =========================
   MAIN CONTENT
   ========================= */
.main-content {
    margin-left: 250px;
    padding: 30px 20px;
}

h2.section-title {
    font-size: 26px;
    background: linear-gradient(90deg,#3AA655,#E8C547);
    -webkit-background-clip: text;
    color: transparent;
    margin-bottom: 16px;
}

/* =========================
   UPLOAD CARD
   ========================= */
.upload-card {
    background: #11243b;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    margin-bottom: 30px;
    width: 1000px;
    margin-left: 190px;
}

.upload-card h3 {
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

/* =========================
   DOCUMENT LIST
   ========================= */
.doc-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 18px;
}

.doc-card {
    background: #11243b;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
    transition: 0.2s;
}

.doc-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(0,0,0,0.5);
}

.doc-title {
    font-size: 18px;
    font-weight: 600;
    color: #3AA655;
}

.doc-type {
    margin-top: 3px;
    font-weight: 600;
    color: #E8C547;
}

.doc-date {
    font-size: 13px;
    color: #c7d8e0;
    margin-top: 5px;
}

/* Status chips */
.status {
    margin-top: 12px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
}

.status.Pending { background: #dcedff; color: #0f5099; }
.status.Approved { background: #def7ed; color: #0d7a5f; }
.status.Rejected { background: #ffe1e1; color: #ad0000; }
.status.Cancelled { background: #f0e68c; color: #8a6d1c; }

/* Cancel & Delete buttons inside doc-card */
.doc-card form {
    display: flex;
    justify-content: space-between;
    margin-top: 12px;
    gap: 8px;
}

.doc-card form button {
    width: 48%;
}

/* View button */
.view-btn {
    margin-top: 15px;
    display: inline-block;
    background: #228b3cff;
    color: white;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    text-decoration: none;
    transition: 0.3s;
}

.view-btn:hover {
    opacity: 0.9;
}

/* =========================
   RESPONSIVE
   ========================= */
@media (max-width: 900px) {
    .main-content { margin-left: 0; }
    .upload-card {
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
  <a href="permit.php"><i class="fa fa-id-card"></i> Permit</a>
  <a href="documents.php" class="active"><i class="fa fa-file"></i> Documents</a>
  <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <?php if($unread_count > 0): ?><span style="background:#ff4d4d;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;"><?= $unread_count ?></span><?php endif; ?></a>
  <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
  <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
  <a href="login.php" style="margin-top:auto;color:#ff7675;"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">

<h2 class="section-title">Upload a Document</h2>

<div class="upload-card">
    <h3><i class="fas fa-upload"></i> Submit New Document</h3>
    <form method="POST" enctype="multipart/form-data">
        <label>Document Title</label>
        <input type="text" name="doc_title" placeholder="Ex: Land Ownership Certificate" required>

        <label>Document Type</label>
        <select name="doc_type" required>
            <option value="">Select Type</option>
            <option>Identification Document</option>
            <option>Land Ownership File</option>
            <option>Business Permit</option>
            <option>Proof of Address</option>
            <option>Other Document</option>
        </select>

        <label>Attach File</label>
        <input type="file" name="doc_file" required>

        <button type="submit">Upload Document</button>
    </form>
</div>

<h2 class="section-title">My Uploaded Documents</h2>

<div class="doc-list">
<?php if($docs->num_rows > 0): ?>
    <?php while($d = $docs->fetch_assoc()): ?>
        <div class="doc-card">
            <div class="doc-title"><?= htmlspecialchars($d['doc_title']) ?></div>
            <div class="doc-type"><?= htmlspecialchars($d['doc_type']) ?></div>
            <div class="doc-date">Uploaded: <?= date("F d, Y", strtotime($d['uploaded_at'])) ?></div>
            <div class="status <?= $d['status'] ?>"><?= $d['status'] ?></div>
            <a class="view-btn" href="<?= $d['file_path'] ?>" target="_blank"><i class="fa fa-eye"></i> View File</a>

            <!-- Cancel & Delete buttons -->
            <form method="POST" style="display: <?= ($d['status'] === 'Pending' || $d['status'] === 'Approved' || $d['status'] === 'Rejected') ? 'flex' : 'none' ?>;">
                <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                <?php if($d['status'] === 'Pending'): ?>
                    <button type="submit" name="action" value="cancel" style="background:#f0ad4e;" onclick="return confirm('Cancel this document submission?')"><i class="fas fa-ban"></i> Cancel</button>
                <?php endif; ?>
                <?php if($d['status'] === 'Cancelled' || $d['status'] === 'Rejected'): ?>
                    <button type="submit" name="action" value="delete" style="background:#d9534f;width:100%;" onclick="return confirm('Delete this document permanently?')"><i class="fas fa-trash"></i> Delete</button>
                <?php else: ?>
                    <button type="submit" name="action" value="delete" style="background:#d9534f;" onclick="return confirm('Delete this document permanently?')"><i class="fas fa-trash"></i> Delete</button>
                <?php endif; ?>
            </form>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>No documents uploaded yet.</p>
<?php endif; ?>
</div>

</div>
</body>
</html>
