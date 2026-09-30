// manage-announcements.js
// Host-only page (admin/staff): create, edit, and delete announcements.

const API_BASE = "http://127.0.0.1:5000";

const token = localStorage.getItem("access_token");
const userJSON = localStorage.getItem("user");
const user = userJSON ? JSON.parse(userJSON) : null;

if (!token || !user || (user.role !== "admin" && user.role !== "staff")) {
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

const authHeaders = {
  "Content-Type": "application/json",
  Authorization: `Bearer ${token}`,
};

const form = document.getElementById("announcement-form");
const formTitle = document.getElementById("form-title");
const formMessage = document.getElementById("form-message");
const listMessage = document.getElementById("list-message");
const idInput = document.getElementById("announcement-id");
const titleInput = document.getElementById("announcement-title");
const contentInput = document.getElementById("announcement-content");
const scheduleInput = document.getElementById("announcement-schedule");
const submitBtn = document.getElementById("announcement-submit-btn");
const cancelBtn = document.getElementById("announcement-cancel-btn");
const container = document.getElementById("announcements-container");

function showFormMessage(text) {
  formMessage.textContent = text;
  formMessage.style.display = "block";
}

function clearFormMessage() {
  formMessage.textContent = "";
  formMessage.style.display = "none";
}

function showListMessage(text) {
  listMessage.textContent = text;
  listMessage.style.display = "block";
}

function clearListMessage() {
  listMessage.textContent = "";
  listMessage.style.display = "none";
}

function resetForm() {
  idInput.value = "";
  form.reset();
  formTitle.textContent = "Post a New Announcement";
  submitBtn.textContent = "Post Announcement";
  cancelBtn.style.display = "none";
  clearFormMessage();
}

cancelBtn.addEventListener("click", resetForm);

function toDateTimeLocal(value) {
  if (!value || value === "None") return "";
  return value.replace(" ", "T").slice(0, 16);
}

async function loadAnnouncements() {
  clearListMessage();
  container.textContent = "Loading announcements...";

  try {
    const response = await fetch(`${API_BASE}/api/announcements`);
    if (!response.ok) throw new Error("Failed to load announcements");
    const announcements = await response.json();

    if (!announcements.length) {
      container.innerHTML = "<p>No announcements yet.</p>";
      return;
    }

    // Newest first
    announcements.sort((a, b) => new Date(b.time_created) - new Date(a.time_created));

    container.innerHTML = "";
    announcements.forEach((a) => {
      const card = document.createElement("div");
      card.className = "announcement-card";
      card.innerHTML = `
        <h3>${a.title}</h3>
        <div class="meta">Posted ${a.time_created}${a.schedule ? ` &middot; Scheduled for ${a.schedule}` : ""}</div>
        <p>${a.content ? a.content.replace(/</g, "&lt;") : ""}</p>
        <div class="form-actions">
          <button type="button" class="btn-secondary edit-btn" data-id="${a.announcement_id}">Edit</button>
          <button type="button" class="btn-danger delete-btn" data-id="${a.announcement_id}" data-title="${a.title}">Delete</button>
        </div>
      `;
      container.appendChild(card);
    });
  } catch (err) {
    container.innerHTML = "<p>Could not load announcements. Is the server running?</p>";
    console.error(err);
  }
}

async function startEdit(id) {
  clearFormMessage();
  try {
    const response = await fetch(`${API_BASE}/api/announcements/${id}`);
    if (!response.ok) throw new Error("Failed to load announcement");
    const a = await response.json();

    idInput.value = a.announcement_id;
    titleInput.value = a.title || "";
    contentInput.value = a.content || "";
    scheduleInput.value = toDateTimeLocal(a.schedule);

    formTitle.textContent = `Editing: ${a.title}`;
    submitBtn.textContent = "Save Changes";
    cancelBtn.style.display = "inline-block";

    document.getElementById("announcement-form-section").scrollIntoView({ behavior: "smooth" });
  } catch (err) {
    showFormMessage("Could not load that announcement for editing.");
    console.error(err);
  }
}

async function deleteAnnouncement(id, title) {
  if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;

  try {
    const response = await fetch(`${API_BASE}/api/announcements/${id}`, {
      method: "DELETE",
      headers: authHeaders,
    });
    const data = await response.json();

    if (!response.ok) {
      showListMessage(data.message || "Could not delete that announcement.");
      return;
    }

    loadAnnouncements();
  } catch (err) {
    showListMessage("Could not reach the server. Please try again.");
    console.error(err);
  }
}

container.addEventListener("click", (event) => {
  const id = event.target.dataset.id;
  if (event.target.classList.contains("edit-btn")) {
    startEdit(id);
  } else if (event.target.classList.contains("delete-btn")) {
    deleteAnnouncement(id, event.target.dataset.title);
  }
});

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  clearFormMessage();

  const payload = {
    title: titleInput.value.trim(),
    content: contentInput.value.trim(),
    schedule: scheduleInput.value ? scheduleInput.value.replace("T", " ") : null,
  };

  if (!payload.title) {
    showFormMessage("Title is required.");
    return;
  }

  const editingId = idInput.value;
  const url = editingId
    ? `${API_BASE}/api/announcements/${editingId}`
    : `${API_BASE}/api/announcements`;
  const method = editingId ? "PUT" : "POST";

  submitBtn.disabled = true;

  try {
    const response = await fetch(url, {
      method,
      headers: authHeaders,
      body: JSON.stringify(payload),
    });
    const data = await response.json();

    if (!response.ok) {
      showFormMessage(data.message || "Could not save that announcement.");
      return;
    }

    resetForm();
    loadAnnouncements();
  } catch (err) {
    showFormMessage("Could not reach the server. Please try again.");
    console.error(err);
  } finally {
    submitBtn.disabled = false;
  }
});

loadAnnouncements();