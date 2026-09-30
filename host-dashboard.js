// host-dashboard.js
// Auth-guards the host dashboard (admin only) and loads stats from
// the Flask API.

const API_BASE = "http://127.0.0.1:5000";

const token = localStorage.getItem("access_token");
const userJSON = localStorage.getItem("user");
const user = userJSON ? JSON.parse(userJSON) : null;

if (!token || !user || (user.role !== "admin" && user.role !== "staff")) {
  window.location.href = "login.php";
}

document.getElementById("host-name").textContent = user
  ? `${user.name} (${user.role})`
  : "";

document.getElementById("logout-btn").addEventListener("click", async () => {
  try {
    await fetch(`${API_BASE}/api/auth/logout`, {
      method: "POST",
      headers: { Authorization: `Bearer ${token}` },
    });
  } catch (err) {
    console.error("Logout request failed:", err);
  } finally {
    try {
      await fetch("logout.php", { method: "POST" });
    } catch (err) {
      console.error("Session logout failed:", err);
    }
    localStorage.removeItem("access_token");
    localStorage.removeItem("user");
    window.location.href = "login.php";
  }
});

const errorEl = document.getElementById("dashboard-error");

function showError(message) {
  errorEl.textContent = message;
  errorEl.style.display = "block";
}

async function loadDashboard() {
  try {
    const response = await fetch(`${API_BASE}/api/dashboard`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });

    if (response.status === 401 || response.status === 422) {
      // token missing/expired
      localStorage.removeItem("access_token");
      localStorage.removeItem("user");
      window.location.href = "login.php";
      return;
    }

    if (!response.ok) {
      throw new Error("Failed to load dashboard stats");
    }

    const data = await response.json();

    document.getElementById("stat-users").textContent = data.total_users;
    document.getElementById("stat-events").textContent = data.total_events;
    document.getElementById("stat-active").textContent = data.active_events;
    document.getElementById("stat-registrations").textContent =
      data.total_registrations;
  } catch (err) {
    showError("Could not load dashboard stats. Is the server running?");
    console.error("Dashboard load failed:", err);
  }
}

loadDashboard();