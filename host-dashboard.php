<?php
require_once "auth.php";
require_role(["admin"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeniorConnect - Host Dashboard</title>

    <style>
    /* =========================================================
       SENIORCONNECT
       CMYK-Inspired Theme (Yellow, Black, Magenta, White, Cyan)
       Shared tokens/layout match index.php / register.php
       ========================================================= */

    :root {
      --yellow: #FFD400;
      --yellow-soft: #FFF7C2;
      --black: #0F0F11;
      --black-soft: #1C1C1F;
      --magenta: #E6007E;
      --magenta-dark: #af1d64;
      --magenta-soft: #FDE6F0;
      --cyan: #00C2E0;
      --cyan-dark: #3296c9;
      --cyan-soft: #E0F6FB;
      --navy: #27215f;
      --white: #FFFFFF;

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

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }

    body {
      font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
      font-size: 18px;
      line-height: 1.7;
      background: var(--bg);
      color: var(--text);
    }

    header {
      position: relative;
      background: linear-gradient(135deg, var(--black), var(--black-soft));
      color: var(--white);
      text-align: center;
      padding: 3rem 1.5rem 2.6rem;
      border-bottom: 5px solid var(--yellow);
      overflow: hidden;
    }

    header h1 { font-size: clamp(2rem, 5vw, 2.8rem); font-weight: 750; }
    header h2 { font-size: 1.2rem; font-weight: 450; opacity: 0.95; margin-top: 0.3rem; }

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
      flex-wrap: wrap;
      box-shadow: 0 2px 10px rgba(15, 15, 17, 0.04);
    }

    nav h3 { font-size: 0.95rem; color: var(--text-soft); font-weight: 650; }
    nav ul { list-style: none; display: flex; gap: 0.55rem; flex-wrap: wrap; }

    nav a, nav button {
      display: inline-block;
      text-decoration: none;
      font: inherit;
      font-weight: 650;
      font-size: 0.98rem;
      color: var(--black);
      background: var(--surface-soft);
      padding: 0.6rem 1.15rem;
      border: 1px solid transparent;
      border-radius: 999px;
      cursor: pointer;
      transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
    }

    nav a.active { background: var(--black); color: var(--white); }
    nav a:hover, nav button:hover { background: var(--black); color: var(--white); transform: translateY(-1.5px); }

    hr { display: none; }

    main {
      width: min(100% - 2rem, 1080px);
      margin: 2.5rem auto;
      display: flex;
      flex-direction: column;
      gap: 1.9rem;
    }

    section {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2rem 2.2rem;
      box-shadow: var(--shadow-sm);
    }

    section h2 {
      color: var(--black);
      font-size: 1.5rem;
      margin-bottom: 1.2rem;
      font-weight: 750;
    }

    #greeting { color: var(--text-soft); margin-bottom: 0.5rem; }
    #greeting strong { color: var(--navy); }

    .stat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1.1rem;
    }

    .stat-card {
      background: var(--surface-soft);
      border: 1px solid var(--border);
      border-left: 5px solid var(--cyan);
      border-radius: var(--radius-small);
      padding: 1.3rem 1.4rem;
    }

    .stat-card .value {
      font-size: 2.1rem;
      font-weight: 800;
      color: var(--navy);
    }

    .stat-card .label {
      font-size: 0.95rem;
      color: var(--text-light);
      font-weight: 650;
    }

    .quick-links {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.1rem;
    }

    .quick-links a {
      display: block;
      text-decoration: none;
      background: var(--surface-soft);
      border: 1px solid var(--border);
      border-radius: var(--radius-small);
      padding: 1.4rem 1.5rem;
      color: var(--text);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .quick-links a:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .quick-links a strong { display: block; font-size: 1.15rem; color: var(--navy); margin-bottom: 0.3rem; }
    .quick-links a span { font-size: 0.95rem; color: var(--text-light); }

    footer {
      text-align: center;
      padding: 2.5rem 1.5rem;
      margin-top: 2rem;
      color: var(--text-light);
      font-size: 0.95rem;
      background: var(--white);
      border-top: 1px solid var(--border);
    }

    @media (max-width: 768px) {
      body { font-size: 17px; }
      nav { flex-direction: column; gap: 0.65rem; }
      nav ul { width: 100%; justify-content: center; }
      main { width: min(100% - 1.5rem, 560px); }
      section { padding: 1.6rem 1.5rem; }
    }
    </style>
</head>
<body>

    <header>
        <h1>SeniorConnect</h1>
        <h2>Host Dashboard</h2>
    </header>

    <nav>
        <h3>Host Menu</h3>
        <ul>
            <li><a href="host-dashboard.php" class="active">Dashboard</a></li>
            <li><a href="manage-events.php">Manage Events</a></li>
            <li><a href="manage-announcements.php">Announcements</a></li>
            <li><a href="view-users.php">Users</a></li>
            <li><a href="index.php">View Public Site</a></li>
            <li><button type="button" id="logout-btn">Log Out</button></li>
        </ul>
    </nav>

    <hr>

    <main>
        <section id="overview">
            <p id="greeting">Signed in as <strong id="host-name">...</strong></p>
            <h2>Overview</h2>
            <p id="dashboard-error" role="alert" style="color:#c0392b; display:none;"></p>
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="value" id="stat-users">—</div>
                    <div class="label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="value" id="stat-events">—</div>
                    <div class="label">Total Events</div>
                </div>
                <div class="stat-card">
                    <div class="value" id="stat-active">—</div>
                    <div class="label">Active Events</div>
                </div>
                <div class="stat-card">
                    <div class="value" id="stat-registrations">—</div>
                    <div class="label">Total Registrations</div>
                </div>
            </div>
        </section>

        <section id="quick-links">
            <h2>Quick Actions</h2>
            <div class="quick-links">
                <a href="manage-events.php">
                    <strong>Manage Events</strong>
                    <span>Create, edit, or remove activities and set categories/locations.</span>
                </a>
                <a href="manage-announcements.php">
                    <strong>Manage Announcements</strong>
                    <span>Post or update announcements shown on the home page.</span>
                </a>
                <a href="view-users.php">
                    <strong>View Users</strong>
                    <span>Browse registered accounts and see who's currently logged in.</span>
                </a>
            </div>
        </section>
    </main>

    <footer>
        <p>ITS122 Group 5 - SeniorConnect Project.</p>
    </footer>

    <script src="host-dashboard.js"></script>
</body>
</html>