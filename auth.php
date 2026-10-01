<?php
// auth.php
// Shared server-side session + role helpers for SeniorConnect.
// Flask still owns the accounts and the API; PHP remembers WHO is logged in
// (in $_SESSION) so protected pages can be blocked before any HTML is sent.

const FLASK_API = "http://127.0.0.1:5000";
const SESSION_LIFETIME = 7200; // 2 hours, same as the Flask JWT

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        "lifetime" => 0,
        "path"     => "/",
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_start();
}

function current_role(): ?string
{
    if (empty($_SESSION["role"]) || empty($_SESSION["expires"])) {
        return null;
    }
    if (time() > $_SESSION["expires"]) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    return $_SESSION["role"];
}

function home_for_role(?string $role): string
{
    switch ($role) {
        case "admin": return "host-dashboard.php";
        case "staff": return "staff.php";
        case "attendee": return "index.php";
        default: return "login.php";
    }
}

// Call at the very top of a protected page, before any output.
function require_role(array $allowed): void
{
    $role = current_role();

    if ($role === null) {
        header("Location: login.php");
        exit;
    }

    if (!in_array($role, $allowed, true)) {
        header("Location: " . home_for_role($role));
        exit;
    }
}

// GET a JSON endpoint on the Flask API from PHP (no cURL extension needed).
// Returns the decoded JSON, or null if Flask can't be reached.
function flask_get(string $path, ?string $token = null): ?array
{
    $headers = "Accept: application/json\r\n";
    if ($token) {
        $headers .= "Authorization: Bearer " . $token . "\r\n";
    }

    $context = stream_context_create([
        "http" => [
            "method"        => "GET",
            "header"        => $headers,
            "timeout"       => 5,
            "ignore_errors" => true,
        ],
    ]);

    $body = @file_get_contents(FLASK_API . $path, false, $context);
    if ($body === false) {
        return null;
    }

    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}
