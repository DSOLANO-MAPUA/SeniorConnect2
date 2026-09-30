<?php
require_once "auth.php";
require_role(["staff"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeniorConnect - Staff</title>

    <style>
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

    section h2 { color: var(--black); font-size: 1.5rem; margin-bottom: 1.2rem; font-weight: 750; }

    form#search-form {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      margin-bottom: 1.2rem;
      flex-wrap: wrap;
    }

    label[for="search-users"] { font-size: 1.05rem; font-weight: 650; color: var(--navy); }

    input[type="text"] {
      flex: 1;
      min-width: 220px;
      padding: 0.75rem 1rem;
      font: inherit;
      font-size: 1rem;
      color: var(--text);
      background: var(--white);
      border: 2px solid var(--border);
      border-radius: var(--radius-small);
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    input[type="text"]:focus {
      outline: none;
      border-color: var(--magenta);
      box-shadow: 0 0 0 4px rgba(217, 45, 130, 0.14);
    }

    button {
      font: inherit;
      cursor: pointer;
      border-radius: var(--radius-small);
      padding: 0.75rem 1.3rem;
      font-weight: 750;
      border: 2px solid transparent;
      transition: background-color 0.2s ease, transform 0.2s ease;
    }

    button:hover { transform: translateY(-1.5px); }

    .btn-primary { background: var(--primary); color: var(--white); border-color: var(--primary); }
    .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
    .btn-secondary { background: var(--surface-soft); color: var(--text); border-color: var(--border); }

    .role-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.35rem 0.85rem;
      border-radius: 999px;
      font-size: 0.88rem;
      font-weight: 700;
    }

    .role-admin { background: var(--magenta-soft); color: var(--magenta-dark); }
    .role-staff { background: var(--yellow-soft); color: #8a6d00; }
    .role-attendee { background: var(--cyan-soft); color: var(--primary-dark); }

    .view-only-note {
      background: var(--cyan-soft);
      border-left: 5px solid var(--cyan);
      color: var(--text-soft);
      padding: 0.8rem 1.1rem;
      border-radius: var(--radius-small);
      margin-bottom: 1.1rem;
      font-size: 0.95rem;
    }

    select {
      padding: 0.75rem 1rem;
      font: inherit;
      font-size: 1rem;
      color: var(--text);
      background: var(--white);
      border: 2px solid var(--border);
      border-radius: var(--radius-small);
    }

    select:focus { outline: none; border-color: var(--magenta); box-shadow: 0 0 0 4px rgba(217, 45, 130, 0.14); }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.35rem 0.85rem;
      border-radius: 999px;
      font-size: 0.88rem;
      font-weight: 700;
    }

    .status-online { background: #E3F7EA; color: #0f7a3d; }
    .status-offline { background: var(--surface-soft); color: var(--text-light); }

    .pin-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.35rem 0.85rem;
      border-radius: 999px;
      font-size: 0.88rem;
      font-weight: 700;
    }

    .pin-set { background: var(--cyan-soft); color: var(--primary-dark); }
    .pin-not-set { background: var(--surface-soft); color: var(--text-light); }

    table {
      width: 100%;
      margin-top: 1rem;
      border-collapse: separate;
      border-spacing: 0;
      border: 1px solid var(--border);
      border-radius: 14px;
      overflow: hidden;
      background: var(--white);
    }

    th {
      background: var(--surface-soft);
      color: var(--black);
      font-size: 0.98rem;
      font-weight: 750;
      text-align: left;
      padding: 0.9rem 1rem;
      border-bottom: 1px solid var(--border);
    }

    td { padding: 0.9rem 1rem; font-size: 0.98rem; color: var(--text-soft); border-bottom: 1px solid var(--border); }
    tr:last-child td { border-bottom: none; }
    tbody tr:nth-child(even) { background: #FBFBFD; }
    tbody tr:hover { background: #F3F6F9; }

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
      table, thead, tbody, th, td, tr { display: block; }
      thead { display: none; }
      tbody tr { border: 1px solid var(--border); border-radius: var(--radius-small); margin-bottom: 0.9rem; }
      td { border-bottom: 1px solid var(--border); }
      td::before { content: attr(data-label); display: block; font-weight: 700; color: var(--navy); font-size: 0.82rem; margin-bottom: 0.2rem; }
    }
    </style>
</head>
<body>

    <header>
        <h1>SeniorConnect</h1>
        <h2>Staff Portal</h2>
    </header>

    <nav>
        <h3>Staff Menu</h3>
        <ul>
            <li><a href="staff.php" class="active">User Accounts</a></li>
            <li><a href="index.php">View Public Site</a></li>
            <li><button type="button" id="logout-btn">Log Out</button></li>
        </ul>
    </nav>

    <hr>

    <main>
        <section id="staff-section">
            <p id="greeting" style="color:var(--text-soft); margin-bottom:0.5rem;">Signed in as <strong id="staff-name" style="color:var(--navy);">...</strong></p>
            <h2>Registered Accounts</h2>
            <p class="view-only-note">
                <strong>View only:</strong> you can see admin and attendee accounts and their login status, but you cannot change them.
            </p>
            <p id="users-message" role="alert" style="color:#c0392b; display:none;"></p>

            <form id="search-form">
                <label for="search-users">Search:</label>
                <input type="text" id="search-users" placeholder="Search by name or phone...">
                <label for="filter-role">Role:</label>
                <select id="filter-role">
                    <option value="">All roles</option>
                    <option value="admin">Admin</option>
                    <option value="attendee">Attendee</option>
                </select>
                <label for="filter-status">Show:</label>
                <select id="filter-status">
                    <option value="all">All accounts</option>
                    <option value="online">Logged in only</option>
                </select>
                <button type="submit" class="btn-primary">Search</button>
                <button type="button" class="btn-secondary" id="clear-search">Clear</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>PIN</th>
                    </tr>
                </thead>
                <tbody id="users-tbody">
                    <tr><td colspan="6">Loading users...</td></tr>
                </tbody>
            </table>
        </section>
    </main>

    <footer>
        <p>ITS122 Group 5 - SeniorConnect Project.</p>
    </footer>

    <script src="staff.js"></script>
</body>
</html>
