// staff.js
// Staff portal: READ-ONLY list of admin and attendee accounts.
// Nothing on this page creates, edits, or deletes anything.

const API_BASE = "http://127.0.0.1:5000";

const token = localStorage.getItem("access_token");
const userJSON = localStorage.getItem("user");
const user = userJSON ? JSON.parse(userJSON) : null;

if (!token || !user) {
  window.location.href = "login.php";
} else if (user.role === "admin") {
  window.location.href = "host-dashboard.php";
} else if (user.role !== "staff") {
  window.location.href = "index.php";
}

document.getElementById("staff-name").textContent = user
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

const tbody = document.getElementById("users-tbody");
const messageEl = document.getElementById("users-message");
const searchForm = document.getElementById("search-form");
const searchInput = document.getElementById("search-users");
const roleFilter = document.getElementById("filter-role");
const statusFilter = document.getElementById("filter-status");
const clearButton = document.getElementById("clear-search");

function showMessage(text) {
  messageEl.textContent = text;
  messageEl.style.display = "block";
}

function clearMessage() {
  messageEl.textContent = "";
  messageEl.style.display = "none";
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text ?? "";
  return div.innerHTML;
}

function roleBadge(role) {
  const className =
    role === "admin" ? "role-admin" : role === "staff" ? "role-staff" : "role-attendee";
  return `<span class="role-badge ${className}">${escapeHtml(role)}</span>`;
}

function statusBadge(isOnline) {
  return isOnline
    ? `<span class="status-badge status-online">Online</span>`
    : `<span class="status-badge status-offline">Offline</span>`;
}

function pinBadge(hasPin) {
  // Only whether a PIN exists - never the PIN or its hash.
  return hasPin
    ? `<span class="pin-badge pin-set">PIN set</span>`
    : `<span class="pin-badge pin-not-set">No PIN</span>`;
}

function renderUsers(users) {
  tbody.innerHTML = "";

  if (!users.length) {
    tbody.innerHTML = `<tr><td colspan="6">No users found.</td></tr>`;
    return;
  }

  users.forEach((u) => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td data-label="Name">${escapeHtml(u.name)}</td>
      <td data-label="Phone">${escapeHtml(u.phone) || "\u2014"}</td>
      <td data-label="Role">${roleBadge(u.role)}</td>
      <td data-label="Status">${statusBadge(u.is_online)}</td>
      <td data-label="Last Login">${escapeHtml(u.last_login_at) || "Never"}</td>
      <td data-label="PIN">${pinBadge(u.has_pin)}</td>
    `;
    tbody.appendChild(row);
  });
}

async function loadUsers() {
  clearMessage();
  tbody.innerHTML = `<tr><td colspan="6">Loading users...</td></tr>`;

  const url = new URL(`${API_BASE}/api/users`);
  const search = searchInput.value.trim();
  if (search) url.searchParams.set("search", search);
  if (statusFilter.value === "all") url.searchParams.set("all", "1");

  try {
    const response = await fetch(url, {
      headers: { Authorization: `Bearer ${token}` },
    });

    if (response.status === 401 || response.status === 422) {
      localStorage.removeItem("access_token");
      localStorage.removeItem("user");
      window.location.href = "login.php";
      return;
    }

    if (response.status === 403) {
      showMessage("Access denied.");
      tbody.innerHTML = "";
      return;
    }

    if (!response.ok) throw new Error("Failed to load users");

    let users = await response.json();

    // Staff view lists admins and attendees; other staff accounts are hidden.
    users = users.filter((u) => u.role === "admin" || u.role === "attendee");
    if (roleFilter.value) {
      users = users.filter((u) => u.role === roleFilter.value);
    }

    renderUsers(users);
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="6">Could not load users. Is the server running?</td></tr>`;
    console.error("Failed to load users:", err);
  }
}

searchForm.addEventListener("submit", (event) => {
  event.preventDefault();
  loadUsers();
});

clearButton.addEventListener("click", () => {
  searchInput.value = "";
  roleFilter.value = "";
  statusFilter.value = "all";
  loadUsers();
});

roleFilter.addEventListener("change", loadUsers);
statusFilter.addEventListener("change", loadUsers);

let debounceTimer;
searchInput.addEventListener("input", () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(loadUsers, 300);
});

loadUsers();
