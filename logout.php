<?php
// logout.php
// Ends the PHP session. The JS logout buttons call this after telling Flask
// to mark the user offline.
require_once "auth.php";

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", time() - 3600, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}

session_destroy();

header("Content-Type: application/json");
echo json_encode(["message" => "Logged out"]);
