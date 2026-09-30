<?php
require_once "auth.php";

// POST = the login form from login.js. PHP forwards the credentials to the
// Flask API, and if Flask accepts them, remembers the user in the session.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");

    // Plain PHP HTTP call (no cURL extension needed).
    // ignore_errors makes PHP still return Flask's JSON body on 401/409/etc.
    $context = stream_context_create([
        "http" => [
            "method"        => "POST",
            "header"        => "Content-Type: application/json\r\n",
            "content"       => file_get_contents("php://input"),
            "timeout"       => 10,
            "ignore_errors" => true,
        ],
    ]);

    $body = @file_get_contents(FLASK_API . "/api/auth/login", false, $context);

    if ($body === false) {
        http_response_code(502);
        echo json_encode(["message" => "Could not reach the server. Please try again."]);
        exit;
    }

    $data = json_decode($body, true);

    // Status code from Flask. PHP 8.4+ has http_get_last_response_headers()
    // (the old $http_response_header variable is deprecated, so it isn't used).
    $status = null;
    if (function_exists("http_get_last_response_headers")) {
        $headers = http_get_last_response_headers();
        if (!empty($headers[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $headers[0], $m)) {
            $status = (int) $m[1];
        }
    }
    // Older PHP: work it out from Flask's JSON instead.
    if ($status === null) {
        $status = isset($data["user"]["role"]) ? 200 : (isset($data["message"]) ? 401 : 500);
    }

    if ($status === 200 && isset($data["user"]["role"])) {
        session_regenerate_id(true);
        $_SESSION["user_id"] = $data["user"]["user_id"];
        $_SESSION["name"]    = $data["user"]["name"];
        $_SESSION["role"]    = $data["user"]["role"];
        $_SESSION["token"]   = $data["access_token"] ?? null;
        $_SESSION["expires"] = time() + SESSION_LIFETIME;
    }

    http_response_code($status ?: 500);
    echo $body;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeniorConnect - Log In</title>

    <style>
    /* =========================================================
       SENIORCONNECT
       CMYK-Inspired Theme (Yellow, Black, Magenta, White, Cyan)
       ========================================================= */

    :root {
      /* Core palette */
      --yellow: #FFD400;
      --yellow-soft: #FFF7C2;

      --black: #0F0F11;
      --black-soft: #1C1C1F;

      --magenta: #E6007E;
      --magenta-soft: #FDE6F0;

      --cyan: #00C2E0;
      --cyan-soft: #E0F6FB;

      --white: #FFFFFF;

      /* Derived UI tokens */
      --bg: #FAFAFA;
      --surface: var(--white);
      --surface-soft: #F5F5F7;

      --text: var(--black);
      --text-soft: #3A3A3E;
      --text-light: #6B6B72;

      --border: #E2E2E6;

      --primary: var(--cyan);
      --primary-dark: #0098B3;
      --accent: var(--magenta);

      --shadow-sm: 0 2px 8px rgba(15, 15, 17, 0.06);
      --shadow-md: 0 6px 20px rgba(15, 15, 17, 0.09);

      --radius: 14px;
      --radius-small: 10px;
    }

    /* =========================================================
       RESET
       ========================================================= */

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
      font-size: 18px;
      line-height: 1.7;
      background: var(--bg);
      color: var(--text);
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* =========================================================
       HEADER
       ========================================================= */

    header {
      position: relative;
      background:
        linear-gradient(135deg, var(--black), var(--black-soft));
      color: var(--white);
      text-align: center;
      padding: 3.2rem 1.5rem 3rem;
      border-bottom: 5px solid var(--yellow);
      box-shadow: 0 6px 18px rgba(15, 15, 17, 0.12);
      overflow: hidden;
    }

    /* Decorative circles using cyan/magenta accents */
    header::before {
      content: "";
      position: absolute;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      background: rgba(0, 194, 224, 0.08); /* cyan tint */
      top: -90px;
      left: -60px;
    }

    header::after {
      content: "";
      position: absolute;
      width: 160px;
      height: 160px;
      border-radius: 50%;
      background: rgba(230, 0, 126, 0.08); /* magenta tint */
      bottom: -70px;
      right: -40px;
    }

    header h1 {
      position: relative;
      z-index: 1;
      font-size: clamp(2.2rem, 5vw, 3.2rem);
      font-weight: 750;
      letter-spacing: -0.4px;
      margin-bottom: 0.4rem;
    }

    header h2 {
      position: relative;
      z-index: 1;
      font-size: 1.3rem;
      font-weight: 450;
      opacity: 0.95;
      margin-bottom: 0.7rem;
    }

    header p {
      position: relative;
      z-index: 1;
      max-width: 720px;
      margin: auto;
      font-size: 1.05rem;
      opacity: 0.9;
    }

    /* =========================================================
       NAVIGATION
       ========================================================= */

    nav {
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(255, 255, 255, 0.97);
      padding: 0.85rem 1.5rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1.2rem;
      box-shadow: 0 2px 10px rgba(15, 15, 17, 0.04);
      backdrop-filter: blur(6px);
    }

    nav h3 {
      font-size: 0.95rem;
      color: var(--text-soft);
      font-weight: 650;
      letter-spacing: 0.2px;
    }

    nav ul {
      list-style: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.55rem;
      flex-wrap: wrap;
    }

    nav a {
      display: inline-block;
      text-decoration: none;
      font-weight: 650;
      font-size: 0.98rem;
      color: var(--black);
      background: var(--surface-soft);
      padding: 0.6rem 1.15rem;
      border-radius: 999px;
      border: 1px solid transparent;
      transition:
        background-color 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
    }

    nav a:hover {
      background: var(--black);
      color: var(--white);
      transform: translateY(-1.5px);
      box-shadow: 0 4px 10px rgba(15, 15, 17, 0.18);
    }

    nav a:focus-visible {
      outline: 3px solid var(--accent); /* magenta focus */
      outline-offset: 3px;
    }

    /* =========================================================
       HORIZONTAL RULE
       ========================================================= */

    hr {
      display: none;
    }

    /* =========================================================
       MAIN CONTENT
       ========================================================= */

    main {
      width: min(100% - 2rem, 1080px);
      margin: 2.5rem auto;
      display: flex;
      flex-direction: column;
      gap: 1.9rem;
    }

    /* =========================================================
       SECTIONS / CARDS
       ========================================================= */

    section {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2rem 2.2rem;
      box-shadow: var(--shadow-sm);
      transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
    }

    section:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    section h2 {
      color: var(--black);
      font-size: 1.7rem;
      line-height: 1.3;
      margin-bottom: 1.2rem;
      font-weight: 750;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* Accent line under section titles using cyan */
    section h2::after {
      content: "";
      flex: 1;
      height: 2px;
      background: linear-gradient(90deg, var(--cyan), transparent);
      margin-left: 0.5rem;
    }

    /* =========================================================
       ANNOUNCEMENTS
       ========================================================= */

    #announcements ul {
      list-style: none;
    }

    #announcements li {
      position: relative;
      background: linear-gradient(135deg, var(--surface-soft), #F9F9FB);
      border: 1px solid var(--border);
      border-left: 5px solid var(--cyan);
      padding: 1.2rem 1.35rem;
      border-radius: var(--radius-small);
      color: var(--text-soft);
    }

    #announcements strong {
      display: block;
      color: var(--text);
      font-size: 1.18rem;
      margin-bottom: 0.15rem;
    }

    /* Optional: small magenta dot for emphasis */
    #announcements li::before {
      content: "";
      position: absolute;
      top: 1.1rem;
      right: 1.1rem;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--accent);
      opacity: 0.7;
    }

    /* =========================================================
       SEARCH AREA
       ========================================================= */

    form {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      margin-bottom: 1.2rem;
      flex-wrap: wrap;
    }

    label[for="search-activity"] {
      font-size: 1.1rem;
      font-weight: 650;
      color: var(--text);
    }

    input[type="text"] {
      flex: 1;
      min-width: 260px;
      padding: 0.85rem 1.1rem;
      font-family: inherit;
      font-size: 1.05rem;
      color: var(--text);
      background: var(--white);
      border: 2px solid var(--border);
      border-radius: var(--radius-small);
      transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
    }

    input[type="text"]::placeholder {
      color: var(--text-light);
    }

    input[type="text"]:hover {
      border-color: #d0d0d6;
    }

    input[type="text"]:focus {
      outline: none;
      border-color: var(--primary); /* cyan */
      box-shadow: 0 0 0 4px rgba(0, 194, 224, 0.15);
    }

    /* =========================================================
       BUTTON
       ========================================================= */

    button[type="submit"] {
      border: none;
      background: var(--primary); /* cyan */
      color: var(--white);
      font-family: inherit;
      font-weight: 700;
      font-size: 1.05rem;
      padding: 0.85rem 1.6rem;
      border-radius: var(--radius-small);
      cursor: pointer;
      box-shadow: 0 3px 8px rgba(0, 194, 224, 0.2);
      transition:
        background-color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
    }

    button[type="submit"]:hover {
      background: var(--primary-dark);
      transform: translateY(-1.5px);
      box-shadow: 0 6px 14px rgba(0, 194, 224, 0.25);
    }

    button[type="submit"]:active {
      transform: translateY(0);
    }

    button[type="submit"]:focus-visible {
      outline: 3px solid var(--accent); /* magenta */
      outline-offset: 3px;
    }

    /* =========================================================
       TABLE
       ========================================================= */

    table {
      width: 100%;
      margin-top: 1rem;
      border-collapse: separate;
      border-spacing: 0;
      border: 1px solid var(--border) !important;
      border-radius: 14px;
      overflow: hidden;
      background: var(--white);
    }

    th {
      background: var(--surface-soft);
      color: var(--black);
      font-size: 1rem;
      font-weight: 750;
      text-align: left;
      padding: 1rem 1.1rem;
      border-bottom: 1px solid var(--border);
      position: relative;
    }

    /* Small cyan underline for table headers */
    th::after {
      content: "";
      position: absolute;
      left: 0;
      right: 0;
      bottom: 0;
      height: 2px;
      background: linear-gradient(90deg, var(--cyan), transparent);
      opacity: 0.7;
    }

    td {
      padding: 1rem 1.1rem;
      font-size: 1rem;
      color: var(--text-soft);
      border-bottom: 1px solid var(--border);
    }

    tr:last-child td {
      border-bottom: none;
    }

    tbody tr {
      transition: background-color 0.15s ease;
    }

    tbody tr:nth-child(even) {
      background: #FBFBFD;
    }

    tbody tr:hover {
      background: #F3F6F9;
    }

    /* Optional: highlight first column with subtle yellow tint */
    tbody tr td:first-child {
      background: linear-gradient(90deg, var(--yellow-soft), transparent);
    }

    /* =========================================================
       STATUS
       ========================================================= */

    #my-status td strong {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      background: var(--cyan-soft);
      color: var(--primary-dark);
      padding: 0.4rem 0.9rem;
      border-radius: 999px;
      font-size: 0.95rem;
      font-weight: 700;
    }

    #my-status td strong::before {
      content: "";
      width: 8px;
      height: 8px;
      background: var(--primary);
      border-radius: 50%;
    }

    /* =========================================================
       FOOTER
       ========================================================= */

    footer {
      text-align: center;
      padding: 2.5rem 1.5rem;
      margin-top: 4rem;
      color: var(--text-light);
      font-size: 0.95rem;
      background: var(--white);
      border-top: 1px solid var(--border);
    }

    /* Small accent bar at top of footer using magenta */
    footer::before {
      content: "";
      display: block;
      width: 60px;
      height: 3px;
      background: var(--accent);
      margin: 0 auto 1.2rem;
      border-radius: 2px;
    }

    /* =========================================================
       RESPONSIVE DESIGN
       ========================================================= */

    @media (max-width: 768px) {
      body {
        font-size: 17px;
      }

      header {
        padding: 2.6rem 1.2rem 2.4rem;
      }

      header h1 {
        font-size: 2.2rem;
      }

      header h2 {
        font-size: 1.15rem;
      }

      nav {
        position: relative;
        flex-direction: column;
        padding: 0.9rem 1rem;
        gap: 0.6rem;
      }

      nav ul {
        width: 100%;
      }

      nav a {
        padding: 0.55rem 0.95rem;
      }

      main {
        width: min(100% - 1.2rem, 1080px);
        margin: 1.5rem auto;
        gap: 1.3rem;
      }

      section {
        padding: 1.5rem;
      }

      section h2 {
        font-size: 1.5rem;
      }

      section h2::after {
        display: none;
      }

      form {
        align-items: stretch;
        flex-direction: column;
      }

      label[for="search-activity"] {
        width: 100%;
      }

      input[type="text"] {
        width: 100%;
        min-width: 0;
      }

      button[type="submit"] {
        width: 100%;
      }

      table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
      }
    }

    /* =========================================================
       SMALL PHONES
       ========================================================= */

    @media (max-width: 480px) {
      header h1 {
        font-size: 1.9rem;
      }

      header p {
        font-size: 0.95rem;
      }

      nav ul {
        flex-direction: column;
        width: 100%;
      }

      nav li,
      nav a {
        width: 100%;
        text-align: center;
      }

      section {
        border-radius: 12px;
        padding: 1.2rem;
      }

      th,
      td {
        padding: 0.75rem 0.85rem;
      }
    }

    /* =========================================================
       REDUCED MOTION
       ========================================================= */

    @media (prefers-reduced-motion: reduce) {
      html {
        scroll-behavior: auto;
      }

      *,
      *::before,
      *::after {
        transition: none !important;
      }
    }

    /* =========================================================
       SENIORCONNECT — LOGIN PAGE
       Maria's Place Inspired Palette
       White • Yellow • Cyan • Magenta • Black
       ========================================================= */

    :root {
      /* Brand colors */
      --white: #ffffff;
      --black: #181818;
      --navy: #27215f;

      --yellow: #f8dc4b;
      --yellow-soft: #fff7c7;
      --yellow-pale: #fffbe6;

      --magenta: #d92d82;
      --magenta-dark: #af1d64;
      --magenta-soft: #fde8f1;

      --cyan: #67b9e7;
      --cyan-dark: #3296c9;
      --cyan-soft: #eaf7fd;

      /* Interface colors */
      --text: var(--black);
      --text-muted: #5f5f68;
      --border: #e4e5ea;
      --input-border: #b9dff3;

      --shadow-sm: 0 4px 14px rgba(39, 33, 95, 0.08);
      --shadow-md: 0 12px 30px rgba(39, 33, 95, 0.14);

      --radius: 14px;
      --radius-small: 9px;
    }

    /* =========================================================
       RESET / BASE
       ========================================================= */

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      min-height: 100%;
      scroll-behavior: smooth;
    }

    body {
      position: relative;
      isolation: isolate;

      min-height: 100vh;

      font-family:
        "Segoe UI",
        Arial,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        sans-serif;
      font-size: 18px;
      line-height: 1.6;
      color: var(--text);

      background:
        radial-gradient(
          ellipse 78% 56% at 100% 100%,
          var(--yellow-soft) 0 52%,
          transparent 53%
        ),
        linear-gradient(
          180deg,
          var(--white) 0%,
          var(--white) 62%,
          var(--yellow-pale) 100%
        );

      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Decorative cyan and magenta bubbles */
    body::before,
    body::after {
      content: "";
      position: fixed;
      z-index: -1;
      border-radius: 50%;
      pointer-events: none;
    }

    body::before {
      width: 210px;
      height: 210px;
      top: -100px;
      left: -75px;
      background: rgba(103, 185, 231, 0.14);
    }

    body::after {
      width: 150px;
      height: 150px;
      right: -70px;
      top: 28%;
      background: rgba(217, 45, 130, 0.08);
    }

    /* =========================================================
       HEADER
       ========================================================= */

    header {
      position: relative;
      z-index: 1;

      padding: 2.7rem 1.5rem 2.3rem;

      text-align: center;

      background: rgba(255, 255, 255, 0.9);
      border-bottom: 5px solid var(--yellow);
    }

    header h1 {
      margin-bottom: 0.2rem;

      color: var(--navy);

      font-size: clamp(2.3rem, 5vw, 3.3rem);
      font-weight: 800;
      letter-spacing: -0.7px;
    }

    header h2 {
      color: var(--magenta);

      font-size: 1.2rem;
      font-weight: 700;
      letter-spacing: 0.2px;
    }

    /* =========================================================
       NAVIGATION
       ========================================================= */

    nav {
      position: relative;
      z-index: 5;

      display: flex;
      align-items: center;
      justify-content: center;
      flex-wrap: wrap;
      gap: 0.9rem;

      padding: 0.85rem 1.25rem;

      background: rgba(255, 255, 255, 0.95);
      border-bottom: 1px solid var(--border);
      box-shadow: 0 3px 10px rgba(39, 33, 95, 0.05);
    }

    nav h3 {
      color: var(--navy);
      font-size: 0.98rem;
      font-weight: 800;
    }

    nav ul {
      display: flex;
      align-items: center;
      justify-content: center;
      flex-wrap: wrap;
      gap: 0.65rem;

      list-style: none;
    }

    nav a {
      display: inline-flex;
      align-items: center;
      justify-content: center;

      min-height: 42px;
      padding: 0.52rem 1rem;

      color: var(--navy);
      background: var(--white);
      border: 2px solid var(--cyan);
      border-radius: 999px;

      font-size: 0.95rem;
      font-weight: 750;
      text-align: center;
      text-decoration: none;

      transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
    }

    nav a:hover {
      color: var(--white);
      background: var(--magenta);
      border-color: var(--magenta);
      transform: translateY(-2px);
      box-shadow: 0 5px 12px rgba(217, 45, 130, 0.22);
    }

    nav a:focus-visible {
      outline: 3px solid var(--yellow);
      outline-offset: 3px;
    }

    /* Hide structural separator from original HTML */
    hr {
      display: none;
    }

    /* =========================================================
       LOGIN LAYOUT
       ========================================================= */

    main {
      width: min(100% - 2rem, 510px);
      min-height: calc(100vh - 235px);

      display: flex;
      align-items: center;

      margin: 0 auto;
      padding: 3.5rem 0 5rem;
    }

    /* =========================================================
       LOGIN CARD / FIELDSET
       ========================================================= */

    fieldset {
      position: relative;
      width: 100%;

      padding: 2.2rem;

      background: rgba(255, 255, 255, 0.96);
      border: 2px solid var(--cyan);
      border-radius: var(--radius);
      box-shadow: var(--shadow-md);
    }

    fieldset::before {
      content: "";

      position: absolute;
      top: 0;
      left: 10%;
      right: 10%;

      height: 5px;

      background: linear-gradient(
        90deg,
        var(--yellow),
        var(--cyan),
        var(--magenta)
      );

      border-radius: 0 0 999px 999px;
    }

    /* The legend remains accessible and visually prominent */
    legend {
      padding: 0 0.7rem;

      color: var(--navy);
      background: var(--white);

      font-size: 1.65rem;
      font-weight: 800;
    }

    legend strong {
      font-weight: inherit;
    }

    /* =========================================================
       FORM
       ========================================================= */

    form {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;

      margin-top: 1.4rem;
    }

    form p {
      display: flex;
      flex-direction: column;
      gap: 0.45rem;
    }

    label {
      color: var(--navy);

      font-size: 1rem;
      font-weight: 750;
    }

    /* The <br> is retained in HTML but no longer creates excess spacing */
    label + br {
      display: none;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      min-height: 52px;

      padding: 0.8rem 1rem;

      color: var(--text);
      background: var(--white);
      border: 2px solid var(--input-border);
      border-radius: var(--radius-small);

      font: inherit;
      font-size: 1rem;

      transition:
        border-color 0.2s ease,
        background-color 0.2s ease,
        box-shadow 0.2s ease;
    }

    input[type="email"]:hover,
    input[type="password"]:hover {
      border-color: var(--cyan-dark);
    }

    input[type="email"]:focus,
    input[type="password"]:focus {
      outline: none;
      background: #fcfeff;
      border-color: var(--magenta);
      box-shadow: 0 0 0 4px rgba(217, 45, 130, 0.14);
    }

    input[type="email"]:valid,
    input[type="password"]:valid {
      border-color: var(--cyan);
    }

    /* =========================================================
       SUBMIT BUTTON
       ========================================================= */

    button[type="submit"] {
      width: 100%;
      min-height: 52px;

      margin-top: 0.35rem;
      padding: 0.8rem 1.25rem;

      color: var(--white);
      background: var(--magenta);
      border: 2px solid var(--magenta);
      border-radius: var(--radius-small);

      font: inherit;
      font-size: 1.08rem;
      font-weight: 800;
      letter-spacing: 0.1px;

      cursor: pointer;
      box-shadow: 0 5px 12px rgba(217, 45, 130, 0.22);

      transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
    }

    button[type="submit"]:hover {
      background: var(--magenta-dark);
      border-color: var(--magenta-dark);
      transform: translateY(-2px);
      box-shadow: 0 8px 18px rgba(217, 45, 130, 0.28);
    }

    button[type="submit"]:active {
      transform: translateY(0);
      box-shadow: 0 3px 8px rgba(217, 45, 130, 0.2);
    }

    button[type="submit"]:focus-visible {
      outline: 3px solid var(--yellow);
      outline-offset: 4px;
    }

    /* =========================================================
       TABLET / MOBILE
       ========================================================= */

    @media (max-width: 768px) {
      body {
        font-size: 17px;
      }

      header {
        padding: 2.3rem 1.2rem 2rem;
      }

      header h1 {
        font-size: 2.35rem;
      }

      nav {
        flex-direction: column;
        gap: 0.65rem;
        padding: 0.9rem 1rem;
      }

      nav ul {
        width: 100%;
      }

      main {
        width: min(100% - 1.5rem, 510px);
        min-height: auto;
        padding: 2.2rem 0 4rem;
      }

      fieldset {
        padding: 1.7rem;
      }
    }

    @media (max-width: 480px) {
      body {
        background:
          radial-gradient(
            ellipse 130% 35% at 100% 100%,
            var(--yellow-soft) 0 52%,
            transparent 53%
          ),
          var(--white);
      }

      header h1 {
        font-size: 2rem;
      }

      header h2 {
        font-size: 1.05rem;
      }

      nav ul {
        flex-direction: column;
      }

      nav li,
      nav a {
        width: 100%;
      }

      fieldset {
        padding: 1.35rem 1.15rem 1.5rem;
        border-radius: 12px;
      }

      legend {
        font-size: 1.45rem;
      }

      input[type="email"],
      input[type="password"],
      button[type="submit"] {
        min-height: 50px;
      }
    }

    /* =========================================================
       ACCESSIBILITY: REDUCED MOTION
       ========================================================= */

    @media (prefers-reduced-motion: reduce) {
      html {
        scroll-behavior: auto;
      }

      *,
      *::before,
      *::after {
        animation: none !important;
        transition: none !important;
      }
    }

    /* =========================================================
       SENIORCONNECT — REGISTRATION PAGE
       Maria's Place Inspired Theme
       White • Yellow • Cyan • Magenta • Black
       ========================================================= */

    :root {
      /* =======================================================
         BRAND PALETTE
         ======================================================= */

      --white: #ffffff;
      --black: #181818;
      --navy: #27215f;

      --yellow: #f8dc4b;
      --yellow-soft: #fff7c7;
      --yellow-pale: #fffbe6;

      --magenta: #d92d82;
      --magenta-dark: #af1d64;
      --magenta-soft: #fde8f1;

      --cyan: #67b9e7;
      --cyan-dark: #3296c9;
      --cyan-soft: #eaf7fd;

      /* =======================================================
         UI TOKENS
         ======================================================= */

      --text: var(--black);
      --text-muted: #5f5f68;

      --border: #e4e5ea;
      --input-border: #b9dff3;

      --shadow-sm: 0 4px 14px rgba(39, 33, 95, 0.08);
      --shadow-md: 0 12px 30px rgba(39, 33, 95, 0.14);

      --radius: 14px;
      --radius-small: 9px;
    }

    /* =========================================================
       RESET / BASE
       ========================================================= */

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      min-height: 100%;
      scroll-behavior: smooth;
    }

    body {
      position: relative;
      isolation: isolate;

      min-height: 100vh;

      color: var(--text);
      background:
        radial-gradient(
          ellipse 78% 50% at 100% 100%,
          var(--yellow-soft) 0 52%,
          transparent 53%
        ),
        linear-gradient(
          180deg,
          var(--white) 0%,
          var(--white) 62%,
          var(--yellow-pale) 100%
        );

      font-family:
        "Segoe UI",
        Arial,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        sans-serif;
      font-size: 18px;
      line-height: 1.6;

      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Decorative background elements */

    body::before,
    body::after {
      content: "";
      position: fixed;
      z-index: -1;

      border-radius: 50%;
      pointer-events: none;
    }

    body::before {
      width: 220px;
      height: 220px;

      top: -110px;
      left: -80px;

      background: rgba(103, 185, 231, 0.14);
    }

    body::after {
      width: 175px;
      height: 175px;

      top: 38%;
      right: -85px;

      background: rgba(217, 45, 130, 0.08);
    }

    /* =========================================================
       HEADER
       ========================================================= */

    header {
      position: relative;
      z-index: 1;

      padding: 2.7rem 1.5rem 2.3rem;

      text-align: center;

      background: rgba(255, 255, 255, 0.9);
      border-bottom: 5px solid var(--yellow);
    }

    header h1 {
      margin-bottom: 0.2rem;

      color: var(--navy);

      font-size: clamp(2.3rem, 5vw, 3.3rem);
      font-weight: 800;
      letter-spacing: -0.7px;
    }

    header h2 {
      color: var(--magenta);

      font-size: 1.2rem;
      font-weight: 700;
      letter-spacing: 0.15px;
    }

    /* =========================================================
       NAVIGATION
       ========================================================= */

    nav {
      position: relative;
      z-index: 5;

      display: flex;
      align-items: center;
      justify-content: center;
      flex-wrap: wrap;
      gap: 0.9rem;

      padding: 0.85rem 1.25rem;

      background: rgba(255, 255, 255, 0.95);
      border-bottom: 1px solid var(--border);
      box-shadow: 0 3px 10px rgba(39, 33, 95, 0.05);
    }

    nav h3 {
      color: var(--navy);

      font-size: 0.98rem;
      font-weight: 800;
    }

    nav ul {
      display: flex;
      align-items: center;
      justify-content: center;
      flex-wrap: wrap;
      gap: 0.65rem;

      list-style: none;
    }

    nav a {
      display: inline-flex;
      align-items: center;
      justify-content: center;

      min-height: 42px;
      padding: 0.52rem 1rem;

      color: var(--navy);
      background: var(--white);
      border: 2px solid var(--cyan);
      border-radius: 999px;

      font-size: 0.95rem;
      font-weight: 750;
      text-align: center;
      text-decoration: none;

      transition:
        background-color 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
    }

    nav a:hover {
      color: var(--white);
      background: var(--magenta);
      border-color: var(--magenta);
      transform: translateY(-2px);
      box-shadow: 0 5px 12px rgba(217, 45, 130, 0.22);
    }

    nav a:focus-visible {
      outline: 3px solid var(--yellow);
      outline-offset: 3px;
    }

    /* Hide the old structural divider */

    hr {
      display: none;
    }

    /* =========================================================
       REGISTRATION PAGE LAYOUT
       ========================================================= */

    main {
      width: min(100% - 2rem, 560px);

      display: flex;
      align-items: center;

      min-height: calc(100vh - 235px);
      margin: 0 auto;
      padding: 3.2rem 0 5rem;
    }

    /* =========================================================
       REGISTRATION CARD
       ========================================================= */

    fieldset {
      position: relative;

      width: 100%;
      padding: 2.2rem;

      background: rgba(255, 255, 255, 0.96);
      border: 2px solid var(--cyan);
      border-radius: var(--radius);
      box-shadow: var(--shadow-md);
    }

    fieldset::before {
      content: "";

      position: absolute;
      top: 0;
      left: 10%;
      right: 10%;

      height: 5px;

      background: linear-gradient(
        90deg,
        var(--yellow),
        var(--cyan),
        var(--magenta)
      );

      border-radius: 0 0 999px 999px;
    }

    legend {
      padding: 0 0.7rem;

      color: var(--navy);
      background: var(--white);

      font-size: 1.65rem;
      font-weight: 800;
    }

    legend strong {
      font-weight: inherit;
    }

    /* =========================================================
       FORM CONTROLS
       ========================================================= */

    form {
      display: flex;
      flex-direction: column;
      gap: 1.1rem;

      margin-top: 1.4rem;
    }

    form p {
      display: flex;
      flex-direction: column;
      gap: 0.45rem;
    }

    /* Hide the <br> tags after labels without changing HTML */

    label + br {
      display: none;
    }

    label {
      color: var(--navy);

      font-size: 1rem;
      font-weight: 750;
    }

    input[type="text"],
    input[type="tel"],
    input[type="email"],
    input[type="password"] {
      width: 100%;
      min-height: 52px;

      padding: 0.8rem 1rem;

      color: var(--text);
      background: var(--white);
      border: 2px solid var(--input-border);
      border-radius: var(--radius-small);

      font: inherit;
      font-size: 1rem;

      transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
    }

    input[type="text"]:hover,
    input[type="tel"]:hover,
    input[type="email"]:hover,
    input[type="password"]:hover {
      border-color: var(--cyan-dark);
    }

    input[type="text"]:focus,
    input[type="tel"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus {
      outline: none;

      background: #fcfeff;
      border-color: var(--magenta);
      box-shadow: 0 0 0 4px rgba(217, 45, 130, 0.14);
    }

    /*
       Uses the browser's built-in validation state.
       Required empty inputs keep their neutral cyan border.
    */

    input[type="text"]:valid,
    input[type="tel"]:valid,
    input[type="email"]:valid,
    input[type="password"]:valid {
      border-color: var(--cyan);
    }

    /* Prevent the hidden role input from affecting the layout */

    input[type="hidden"] {
      display: none;
    }

    /* =========================================================
       CREATE ACCOUNT BUTTON
       ========================================================= */

    button[type="submit"] {
      width: 100%;
      min-height: 54px;

      margin-top: 0.4rem;
      padding: 0.85rem 1.25rem;

      color: var(--white);
      background: var(--magenta);
      border: 2px solid var(--magenta);
      border-radius: var(--radius-small);

      font: inherit;
      font-size: 1.08rem;
      font-weight: 800;
      letter-spacing: 0.1px;

      cursor: pointer;
      box-shadow: 0 5px 12px rgba(217, 45, 130, 0.22);

      transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
    }

    button[type="submit"]:hover {
      background: var(--magenta-dark);
      border-color: var(--magenta-dark);
      transform: translateY(-2px);
      box-shadow: 0 8px 18px rgba(217, 45, 130, 0.28);
    }

    button[type="submit"]:active {
      transform: translateY(0);
      box-shadow: 0 3px 8px rgba(217, 45, 130, 0.2);
    }

    button[type="submit"]:focus-visible {
      outline: 3px solid var(--yellow);
      outline-offset: 4px;
    }

    /* =========================================================
       TABLET / MOBILE
       ========================================================= */

    @media (max-width: 768px) {
      body {
        font-size: 17px;
      }

      header {
        padding: 2.3rem 1.2rem 2rem;
      }

      header h1 {
        font-size: 2.35rem;
      }

      nav {
        flex-direction: column;
        gap: 0.65rem;
        padding: 0.9rem 1rem;
      }

      nav ul {
        width: 100%;
      }

      main {
        width: min(100% - 1.5rem, 560px);

        min-height: auto;
        padding: 2.2rem 0 4rem;
      }

      fieldset {
        padding: 1.7rem;
      }
    }

    @media (max-width: 480px) {
      body {
        background:
          radial-gradient(
            ellipse 130% 35% at 100% 100%,
            var(--yellow-soft) 0 52%,
            transparent 53%
          ),
          var(--white);
      }

      header h1 {
        font-size: 2rem;
      }

      header h2 {
        font-size: 1.05rem;
      }

      nav ul {
        flex-direction: column;
      }

      nav li,
      nav a {
        width: 100%;
      }

      fieldset {
        padding: 1.35rem 1.15rem 1.5rem;
        border-radius: 12px;
      }

      legend {
        font-size: 1.45rem;
      }

      input[type="text"],
      input[type="tel"],
      input[type="email"],
      input[type="password"],
      button[type="submit"] {
        min-height: 50px;
      }
    }

    /* =========================================================
       ACCESSIBILITY: REDUCED MOTION
       ========================================================= */

    @media (prefers-reduced-motion: reduce) {
      html {
        scroll-behavior: auto;
      }

      *,
      *::before,
      *::after {
        animation: none !important;
        transition: none !important;
      }
    }
    </style>
</head>
<body>

    <header>
        <h1>SeniorConnect</h1>
        <h2>Account Access</h2>
    </header>

    <nav>
        <h3>Menu</h3>
        <ul>
            <li><a href="index.php">Back to Home</a></li>
            <li><a href="register.php">Don't have an account? Register here</a></li>
        </ul>
    </nav>
    
    <hr>

    <main>
        <fieldset>
            <legend><strong>Log In</strong></legend>
            <form id="login-form" novalidate>
                <p id="login-error" role="alert" style="color:#c0392b; display:none;"></p>
                <p>
                    <label for="login-name">Full Name:</label><br>
                    <input type="text" id="login-name" name="name" autocomplete="name" required>
                    <span class="field-error" id="login-name-error" style="color:#c0392b; display:none;"></span>
                </p>
                <p>
                    <label for="login-phone">Phone Number:</label><br>
                    <input type="tel" id="login-phone" name="phone" autocomplete="tel" required>
                    <span class="field-error" id="login-phone-error" style="color:#c0392b; display:none;"></span>
                </p>
                <p>
                    <label for="login-pin">4-Digit PIN:</label><br>
                    <input type="password" id="login-pin" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" required>
                    <span class="field-error" id="login-pin-error" style="color:#c0392b; display:none;"></span>
                </p>
                <button type="submit">Log In</button>
            </form>
        </fieldset>
    </main>

    <script src="login.js"></script>
</body>
</html>