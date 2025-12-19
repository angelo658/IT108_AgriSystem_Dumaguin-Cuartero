<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = getPDOConnection();
$export_type = $_GET['type'] ?? 'csv';

// Fetch all users
$stmt = $conn->query("SELECT id, firstname, middlename, lastname, email, contact, barangay, city, province, region, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

if ($export_type === 'csv') {
    // CSV Export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, ['ID', 'First Name', 'Middle Name', 'Last Name', 'Email', 'Contact', 'Barangay', 'City', 'Province', 'Region', 'Registered Date']);
    
    // CSV Data
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['firstname'],
            $user['middlename'],
            $user['lastname'],
            $user['email'],
            $user['contact'] ?? 'N/A',
            $user['barangay'] ?? 'N/A',
            $user['city'] ?? 'N/A',
            $user['province'] ?? 'N/A',
            $user['region'] ?? 'N/A',
            $user['created_at']
        ]);
    }
    
    fclose($output);
    logActivity($conn, 'export_users', 'Exported users data to CSV', null, $_SESSION['admin_id']);
    exit;
}
?>
