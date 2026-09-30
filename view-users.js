// view-users.js
// Admin-only page: lists all registered users, with search by name/email.

const API_BASE = "http://127.0.0.1:5000";

const token = localStorage.getItem("access_token");
const userJSON = localStorage.getItem("user");
const user = userJSON ? JSON.parse(userJSON) : null;

if (!token || !user || user.role !== "admin") {
  window.location.href = "login.php";
}

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
const clearButton = document.getElementById("clear-search");

function showMessage(text) {
  messageEl.textContent = text;
  messageEl.style.display = "block";
}

function clearMessage() {
  messageEl.textContent = "";
  messageEl.style.display = "none";
}

function roleBadge(role) {
  const className =
    role === "admin" ? "role-admin" : role === "staff" ? "role-staff" : "role-attendee";
  return `<span class="role-badge ${className}">${role}</span>`;
}

function pinBadge(hasPin) {
  // We never display the actual PIN (or its bcrypt hash) here - just
  // whether the account has one set.
  const className = hasPin ? "pin-set" : "pin-not-set";
  const label = hasPin ? "PIN set" : "No PIN";
  return `<span class="pin-badge ${className}">${label}</span>`;
}

function renderUsers(users) {
  tbody.innerHTML = "";

  if (!users.length) {
    tbody.innerHTML = `<tr><td colspan="4">No users found.</td></tr>`;
    return;
  }

  function renderUsers(users) {
    tbody.innerHTML = "";

    if (!users.length) {
        const row = document.createElement("tr");
        const cell = document.createElement("td");

        cell.colSpan = 4;
        cell.textContent = "No users found.";

        row.appendChild(cell);
        tbody.appendChild(row);
        return;
    }

    users.forEach((u) => {
        const row = document.createElement("tr");

        const nameCell = document.createElement("td");
        nameCell.dataset.label = "Name";
        nameCell.textContent = u.name;

        const pinCell = document.createElement("td");
        pinCell.dataset.label = "PIN Code";
        pinCell.innerHTML = pinBadge(u.has_pin);

        const phoneCell = document.createElement("td");
        phoneCell.dataset.label = "Phone";
        phoneCell.textContent = u.phone ?? "—";

        const roleCell = document.createElement("td");
        roleCell.dataset.label = "Role";
        roleCell.innerHTML = roleBadge(u.role);

        row.appendChild(nameCell);
        row.appendChild(pinCell);
        row.appendChild(phoneCell);
        row.appendChild(roleCell);

        tbody.appendChild(row);
    });
}
}

async function loadUsers(search = "") {
  clearMessage();
  tbody.innerHTML = `<tr><td colspan="4">Loading users...</td></tr>`;

  const url = new URL(`${API_BASE}/api/users`);
  if (search) {
    url.searchParams.set("search", search);
  }

  try {
    const response = await fetch(url, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
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

    if (!response.ok) {
      throw new Error("Failed to load users");
    }

    const users = await response.json();
    renderUsers(users);
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="4">Could not load users. Is the server running?</td></tr>`;
    console.error("Failed to load users:", err);
  }
}

searchForm.addEventListener("submit", (event) => {
  event.preventDefault();
  loadUsers(searchInput.value.trim());
});

clearButton.addEventListener("click", () => {
  searchInput.value = "";
  loadUsers();
});

let debounceTimer;
searchInput.addEventListener("input", () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    loadUsers(searchInput.value.trim());
  }, 300);
});

loadUsers();