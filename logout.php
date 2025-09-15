<?php
session_start();
require 'config.php';

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Clear remember token in database
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
}

// Clear session
session_unset();
session_destroy();

// Clear remember_token cookie
setcookie('remember_token', '', time() - 3600, '/');

session_start();

$_SESSION['STATUS'] = 'LOGOUT_SUCCESFUL';

// Redirect to login with logout flag
header("Location: login.php?logout=1");
exit();
?>