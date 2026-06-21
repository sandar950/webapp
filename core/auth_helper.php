<?php
// This file should be included in admin pages.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Displays a styled access denied page and terminates the script.
 * @param string $message The error message to display.
 */
function show_access_denied_page($message) {
    // Determine the correct relative path for assets based on script depth
    $path_prefix = str_repeat('../', substr_count(rtrim($_SERVER['REQUEST_URI'], '/'), '/') - 1);
    $lang_attr = $_SESSION['lang'] ?? 'en';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="{$lang_attr}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white max-w-md w-full rounded-2xl shadow-xl p-8 text-center border-t-4 border-red-600">
        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
            <i class="fas fa-user-lock text-3xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Access Denied</h2>
        <p class="text-sm text-gray-600 mb-6">{$message}</p>
        <a href="{$path_prefix}login.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition duration-200 shadow-md">Go to Login</a>
    </div>
</body>
</html>
HTML;
    die($html);
}

require_once dirname(__DIR__) . '/lang/language.php';

/**
 * Checks if the current logged-in admin has a specific permission.
 *
 * @param string $permission_key The key of the permission to check (e.g., 'can_manage_users').
 * @return bool True if the user has permission, false otherwise.
 */
function check_permission($permission_key) {
    // Main admin always has all permissions.
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }

    // Regular users have no admin permissions.
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sub_admin') {
        return false;
    }
    
    // For Sub-Admins, check their specific permissions from the session.
    if (!isset($_SESSION['permissions'])) {
        global $conn;
        if (!$conn) { return false; } 

        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM sub_admin_permissions WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $_SESSION['permissions'] = $result->fetch_assoc() ?: [];
        $stmt->close();
    }
    
    return isset($_SESSION['permissions'][$permission_key]) && $_SESSION['permissions'][$permission_key] == 1;
}

function require_permission($permission_key) {
    if (!check_permission($permission_key)) {
        show_access_denied_page(__('admin_access_denied_perm'));
    }
}

function require_admin_login() {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'sub_admin'])) {
        show_access_denied_page(__('admin_access_denied_role'));
    }
}

function require_main_admin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        show_access_denied_page(__('admin_access_denied_main'));
    }
}

/**
 * Logs an admin's activity into the database.
 *
 * @param int $admin_id The ID of the admin performing the action.
 * @param string $action A short, uppercase key for the action (e.g., 'UPDATE_SETTINGS').
 * @param string $description A detailed description of the action.
 */
function log_activity($admin_id, $action, $description = '') {
    global $conn;
    if (!$conn || !$admin_id) { return; }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $admin_id, $action, $description, $ip_address);
    $stmt->execute();
    $stmt->close();
}
?>