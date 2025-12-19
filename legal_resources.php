<?php
session_start();
require_once 'config.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

checkSessionTimeout();

$user_id = $_SESSION['user_id'];

// DATABASE CONFIG
$conn = getDBConnection();
$pdo = getPDOConnection();

// Hardcoded Categories
$CATEGORIES = [
    ['id'=>1,'name'=>'Permits & Licenses','icon'=>'fa-file','color'=>'#e74c3c'],
    ['id'=>2,'name'=>'Environmental','icon'=>'fa-leaf','color'=>'#27ae60'],
    ['id'=>3,'name'=>'Safety & Health','icon'=>'fa-shield-alt','color'=>'#2980b9'],
    ['id'=>4,'name'=>'Organic','icon'=>'fa-seedling','color'=>'#16a085'],
    ['id'=>5,'name'=>'Labor','icon'=>'fa-users','color'=>'#8e44ad'],
    ['id'=>6,'name'=>'Land Use','icon'=>'fa-tree','color'=>'#f39c12'],
    ['id'=>7,'name'=>'Trade & Export','icon'=>'fa-truck','color'=>'#d35400'],
    ['id'=>8,'name'=>'Finance','icon'=>'fa-money-bill','color'=>'#2c3e50']
];

// FILTERS
$selected_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'desc';
if(!in_array($sort, ['asc','desc','alpha'])) $sort='desc';

// Fetch Resources
$resources = [];
$sql = "SELECT * FROM legal_resources";
$where = [];
$params = [];

if($selected_category > 0){
    $where[] = "category_id=?";
    $params[] = $selected_category;
}
if($search !== ''){
    $where[] = "(title LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}
if($where) $sql .= " WHERE " . implode(" AND ", $where);

if($sort === 'asc' || $sort === 'desc'){
    $sql .= " ORDER BY created_at $sort";
} else {
    $sql .= " ORDER BY title ASC";
}

$stmt_res = $pdo->prepare($sql);
if($params){
    $stmt_res->execute($params);
} else {
    $stmt_res->execute();
}

while($row = $stmt_res->fetch(PDO::FETCH_ASSOC)){
    foreach($CATEGORIES as $cat){
        if($cat['id']==$row['category_id']){
            $row['category_name']=$cat['name'];
            $row['category_icon']=$cat['icon'];
            $row['category_color']=$cat['color'];
            break;
        }
    }
    $resources[] = $row;
}

// Get unread notification count
$unread_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Legal Resources | Agri Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ==============================
   BODY
============================== */
body {
    margin: 0;
    font-family: "Poppins", sans-serif;
    background: #0A1A2F;
    color: #f5f6fa;
}

/* ==============================
   HOMEPAGE SIDEBAR
============================== */
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

.main-content {
    margin-left: 300px;
    padding: 30px;
}

/* ==============================
   SEARCH / CATEGORIES / RESOURCES
============================== */
h2.section-title {
    font-size: 26px;
    background: linear-gradient(90deg, #3AA655, #E8C547);
    -webkit-background-clip: text;
    color: transparent;
    margin-bottom: 18px;
}

.search-sort {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-sort input,
.search-sort select {
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #3AA655;
    background: #11243b;
    color: #f5f6fa;
}

.search-sort button {
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    background: #228b3cff;
    color: white;
    cursor: pointer;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 18px;
    margin-bottom: 22px;
}

.category-card {
    background: #11243b;
    padding: 22px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
}

.category-card i {
    font-size: 34px;
    margin-bottom: 12px;
}

.resources-box {
    background: #11243b;
    padding: 20px;
    border-radius: 12px;
}

.resource {
    padding: 18px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    cursor: pointer;
}

.resource:hover {
    background: #15345A;
}

.resource-title {
    font-size: 18px;
    font-weight: 600;
    color: #E8C547;
}

.resource-desc {
    font-size: 14px;
    color: #c7d8e0;
}

/* MODAL */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: #11243b;
    width: 90%;
    max-width: 600px;
    padding: 25px;
    border-radius: 14px;
    border-top: 6px solid #3AA655;
}

.modal-close {
    position: absolute;
    top: 10px;
    right: 10px;
    color: #E8C547;
    font-size: 22px;
    cursor: pointer;
}

/* SIDEBAR ICON COLORS */
.sidebar a:nth-child(2) i { color: #5ac18e; }   /* Home */
.sidebar a:nth-child(3) i { color: #ffcc00; }   /* Compliance */
.sidebar a:nth-child(4) i { color: #8d99ae; }   /* Legal */
.sidebar a:nth-child(5) i { color: #f9844a; }   /* Permit */
.sidebar a:nth-child(6) i { color: #1E90FF; }   /* Documents */
</style>
</head>

<body>

<!-- ==============================
     SIDEBAR
============================== -->
<div class="sidebar">
    <div class="logo">🌿 AGRI Com</div>
    <a href="home.php"><i class="fa fa-home"></i> Home</a>
    <a href="compliance.php"><i class="fas fa-tasks"></i> Compliance Tools</a>
    <a href="legal_resources.php" class="active"><i class="fas fa-book"></i> Legal Resources</a>
    <a href="permit.php"><i class="fa fa-id-card"></i> Permit</a>
    <a href="documents.php"><i class="fa fa-file"></i> Documents</a>
    <a href="notifications.php"><i class="fas fa-bell"></i> Notifications <?php if($unread_count > 0): ?><span style="background:#ff4d4d;color:white;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px;"><?= $unread_count ?></span><?php endif; ?></a>
    <a href="profile.php" ><i class="fas fa-user"></i> Profile</a>
    <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
    <a href="login.php" style="margin-top:auto;color:#ff7675;"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>

<!-- ==============================
     MAIN CONTENT
============================== -->
<div class="main-content">

<h2 class="section-title">Legal Resources</h2>

<form method="get" class="search-sort">
    <select name="category">
        <option value="0">All Categories</option>
        <?php foreach($CATEGORIES as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $selected_category==$cat['id']?'selected':'' ?>>
                <?= htmlspecialchars($cat['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="text" name="search" placeholder="Search resources..." value="<?= htmlspecialchars($search) ?>">

    <select name="sort">
        <option value="desc" <?= $sort=='desc'?'selected':'' ?>>Newest</option>
        <option value="asc" <?= $sort=='asc'?'selected':'' ?>>Oldest</option>
        <option value="alpha" <?= $sort=='alpha'?'selected':'' ?>>A-Z</option>
    </select>

    <button type="submit">Apply</button>
</form>

<div class="category-grid">
    <?php foreach($CATEGORIES as $cat): ?>
        <a href="legal_resources.php?category=<?= $cat['id'] ?>" style="text-decoration:none; color:white;">
            <div class="category-card">
                <i class="fas <?= $cat['icon'] ?>" style="color:<?= $cat['color'] ?>;"></i>
                <h3><?= htmlspecialchars($cat['name']) ?></h3>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="resources-box">
    <?php if(count($resources)>0): ?>
        <?php foreach($resources as $res): ?>
            <div class="resource"
                 data-title="<?= htmlspecialchars($res['title']) ?>"
                 data-desc="<?= htmlspecialchars($res['description']) ?>"
                 data-category="<?= htmlspecialchars($res['category_name']) ?>"
                 data-link="<?= htmlspecialchars($res['file_link']) ?>">
                <div class="resource-title"><?= htmlspecialchars($res['title']) ?></div>
                <div class="resource-desc"><?= htmlspecialchars(substr($res['description'],0,120)) ?>...</div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No resources found.</p>
    <?php endif; ?>
</div>

</div>

<!-- ==============================
     MODAL
============================== -->
<div class="modal" id="modal">
    <div class="modal-content">
        <button class="modal-close" id="modal-close">&times;</button>
        <h2 id="modal-title"></h2>
        <p id="modal-category" style="color:#3AA655;font-weight:600;"></p>
        <p id="modal-desc"></p>
        <a id="modal-link" href="#" target="_blank">Open File</a>
    </div>
</div>

<script>
const modal = document.getElementById('modal');

document.querySelectorAll('.resource').forEach(res => {
    res.addEventListener('click', () => {
        document.getElementById('modal-title').textContent = res.dataset.title;
        document.getElementById('modal-category').textContent = "Category: " + res.dataset.category;
        document.getElementById('modal-desc').textContent = res.dataset.desc;

        if(res.dataset.link){
            document.getElementById('modal-link').href = res.dataset.link;
            document.getElementById('modal-link').style.display = 'inline-block';
        } else {
            document.getElementById('modal-link').style.display = 'none';
        }

        modal.style.display = 'flex';
    });
});

document.getElementById('modal-close').onclick = () => modal.style.display = 'none';
window.onclick = e => { if(e.target === modal) modal.style.display = 'none'; };
</script>

</body>
</html>
