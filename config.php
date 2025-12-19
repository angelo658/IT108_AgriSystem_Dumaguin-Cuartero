<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'portal');

// Create database connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log($e->getMessage());
        die("Database connection failed. Please try again later.");
    }
}

// Create PDO connection
function getPDOConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        die("Database connection failed. Please try again later.");
    }
}

// Security Settings
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// File Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
define('UPLOAD_DIR', '../uploads/');

// Site Settings
define('SITE_NAME', 'AGRI Compliance Portal');
define('ADMIN_EMAIL', 'admin@portal.local');

// Timezone
date_default_timezone_set('Asia/Manila');

// Session Configuration (MUST be set before session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    session_start();
}

// Check session timeout
function checkSessionTimeout() {
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

// Log activity
function logActivity($conn, $action_type, $action_details, $user_id = null, $admin_id = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        if ($conn instanceof PDO) {
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, admin_id, action_type, action_details, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $admin_id, $action_type, $action_details, $ip_address]);
        } else {
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, admin_id, action_type, action_details, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $user_id, $admin_id, $action_type, $action_details, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Create notification
function createNotification($conn, $user_id, $title, $message, $type = 'info', $related_type = null, $related_id = null) {
    try {
        if ($conn instanceof PDO) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $message, $type, $related_type, $related_id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssi", $user_id, $title, $message, $type, $related_type, $related_id);
            $stmt->execute();
            $stmt->close();
        }
        return true;
    } catch (Exception $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate file upload
function validateFileUpload($file) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error.";
        return $errors;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "File size exceeds maximum allowed size (5MB).";
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        $errors[] = "File type not allowed. Allowed types: " . implode(', ', ALLOWED_FILE_TYPES);
    }
    
    return $errors;
}

// Generate unique token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Format date
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

// Format datetime
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    return date($format, strtotime($datetime));
}
?>
