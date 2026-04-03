<?php
// Initialize the session
session_start();

// Unset all of the session variables
$_SESSION = array();

// Completely destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the active session
session_destroy();

// Redirect the user back to the login page securely
header("Location: login.php");
exit();
?>
