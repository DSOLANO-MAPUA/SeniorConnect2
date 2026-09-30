// manage-events.js
// Host-only page (admin/staff): create, edit, and delete events, plus
// quick-add categories and locations used by the event form's dropdowns.

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

// ===========================================================
// CACHED STATE
// ===========================================================
// The events list/detail endpoints return category NAME and location
// ADDRESS as plain text (not their ids), so to pre-select the right
// dropdown option when editing an event we match those text values
// against the categories/locations we already loaded. This is a
// best-effort match (assumes reasonably unique names/addresses).

let categories = [];
let locations = [];
let events = [];

// ===========================================================
// EVENT FORM ELEMENTS
// ===========================================================

const eventForm = document.getElementById("event-form");
const formTitle = document.getElementById("form-title");
const formMessage = document.getElementById("form-message");
const eventIdInput = document.getElementById("event-id");
const titleInput = document.getElementById("event-title");
const categorySelect = document.getElementById("event-category");
const locationSelect = document.getElementById("event-location");
const capacityInput = document.getElementById("event-capacity");
const startInput = document.getElementById("event-start");
const endInput = document.getElementById("event-end");
const submitBtn = document.getElementById("event-submit-btn");
const cancelBtn = document.getElementById("event-cancel-btn");

const taxonomyMessage = document.getElementById("taxonomy-message");
const listMessage = document.getElementById("list-message");
const eventsTbody = document.getElementById("events-tbody");

function showMessage(el, text) {
  el.textContent = text;
  el.style.display = "block";
}

function clearMessage(el) {
  el.textContent = "";
  el.style.display = "none";
}

function toDateTimeLocal(value) {
  if (!value || value === "None") return "";
  return value.replace(" ", "T").slice(0, 16);
}

function fromDateTimeLocal(value) {
  return value ? value.replace("T", " ") : null;
}

function resetEventForm() {
  eventIdInput.value = "";
  eventForm.reset();
  formTitle.textContent = "Create a New Event";
  submitBtn.textContent = "Create Event";
  cancelBtn.style.display = "none";
  clearMessage(formMessage);
}

cancelBtn.addEventListener("click", resetEventForm);

// ===========================================================
// CATEGORIES
// ===========================================================

async function loadCategories() {
  try {
    const response = await fetch(`${API_BASE}/api/categories`);
    if (!response.ok) throw new Error("Failed to load categories");
    categories = await response.json();

    categorySelect.innerHTML = '<option value="">— None —</option>';
    categories.forEach((c) => {
      const opt = document.createElement("option");
      opt.value = c.category_id;
      opt.textContent = c.name;
      categorySelect.appendChild(opt);
    });

    const listEl = document.getElementById("categories-list");
    if (!categories.length) {
      listEl.innerHTML = "<p>No categories yet.</p>";
      return;
    }
    listEl.innerHTML = "";
    categories.forEach((c) => {
      const chip = document.createElement("span");
      chip.className = "chip";
      chip.innerHTML = `${c.name} <button type="button" class="delete-category-btn" data-id="${c.category_id}" data-name="${c.name}">&times;</button>`;
      listEl.appendChild(chip);
    });
  } catch (err) {
    document.getElementById("categories-list").innerHTML =
      "<p>Could not load categories.</p>";
    console.error(err);
  }
}

document.getElementById("category-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  clearMessage(taxonomyMessage);

  const nameInput = document.getElementById("category-name");
  const name = nameInput.value.trim();
  if (!name) return;

  try {
    const response = await fetch(`${API_BASE}/api/categories`, {
      method: "POST",
      headers: authHeaders,
      body: JSON.stringify({ name }),
    });
    const data = await response.json();

    if (!response.ok) {
      showMessage(taxonomyMessage, data.message || "Could not add category.");
      return;
    }

    nameInput.value = "";
    loadCategories();
  } catch (err) {
    showMessage(taxonomyMessage, "Could not reach the server. Please try again.");
    console.error(err);
  }
});

document.getElementById("categories-list").addEventListener("click", async (event) => {
  if (!event.target.classList.contains("delete-category-btn")) return;

  const id = event.target.dataset.id;
  const name = event.target.dataset.name;
  if (!confirm(`Delete category "${name}"?`)) return;

  try {
    const response = await fetch(`${API_BASE}/api/categories/${id}`, {
      method: "DELETE",
      headers: authHeaders,
    });
    const data = await response.json();

    if (!response.ok) {
      showMessage(taxonomyMessage, data.message || "Could not delete that category.");
      return;
    }

    loadCategories();
  } catch (err) {
    showMessage(taxonomyMessage, "Could not reach the server. Please try again.");
    console.error(err);
  }
});

// ===========================================================
// LOCATIONS
// ===========================================================

async function loadLocations() {
  try {
    const response = await fetch(`${API_BASE}/api/locations`);
    if (!response.ok) throw new Error("Failed to load locations");
    locations = await response.json();

    locationSelect.innerHTML = '<option value="">— None —</option>';
    locations.forEach((l) => {
      const opt = document.createElement("option");
      opt.value = l.location_id;
      opt.textContent = l.address;
      locationSelect.appendChild(opt);
    });

    const listEl = document.getElementById("locations-list");
    if (!locations.length) {
      listEl.innerHTML = "<p>No locations yet.</p>";
      return;
    }
    listEl.innerHTML = "";
    locations.forEach((l) => {
      const chip = document.createElement("span");
      chip.className = "chip";
      chip.innerHTML = `${l.address} <button type="button" class="delete-location-btn" data-id="${l.location_id}" data-address="${l.address}">&times;</button>`;
      listEl.appendChild(chip);
    });
  } catch (err) {
    document.getElementById("locations-list").innerHTML =
      "<p>Could not load locations.</p>";
    console.error(err);
  }
}

document.getElementById("location-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  clearMessage(taxonomyMessage);

  const addressInput = document.getElementById("location-address");
  const address = addressInput.value.trim();
  if (!address) return;

  try {
    const response = await fetch(`${API_BASE}/api/locations`, {
      method: "POST",
      headers: authHeaders,
      body: JSON.stringify({ address }),
    });
    const data = await response.json();

    if (!response.ok) {
      showMessage(taxonomyMessage, data.message || "Could not add location.");
      return;
    }

    addressInput.value = "";
    loadLocations();
  } catch (err) {
    showMessage(taxonomyMessage, "Could not reach the server. Please try again.");
    console.error(err);
  }
});

document.getElementById("locations-list").addEventListener("click", async (event) => {
  if (!event.target.classList.contains("delete-location-btn")) return;

  const id = event.target.dataset.id;
  const address = event.target.dataset.address;
  if (!confirm(`Delete location "${address}"?`)) return;

  try {
    const response = await fetch(`${API_BASE}/api/locations/${id}`, {
      method: "DELETE",
      headers: authHeaders,
    });
    const data = await response.json();

    if (!response.ok) {
      showMessage(taxonomyMessage, data.message || "Could not delete that location.");
      return;
    }

    loadLocations();
  } catch (err) {
    showMessage(taxonomyMessage, "Could not reach the server. Please try again.");
    console.error(err);
  }
});

// ===========================================================
// EVENTS
// ===========================================================

function renderEvents() {
  eventsTbody.innerHTML = "";

  if (!events.length) {
    eventsTbody.innerHTML = `<tr><td colspan="7">No events yet.</td></tr>`;
    return;
  }

  events.forEach((e) => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td data-label="Title">${e.title}</td>
      <td data-label="Category">${e.category ?? "—"}</td>
      <td data-label="Location">${e.location ?? "—"}</td>
      <td data-label="Start">${e.start_time ?? "TBA"}</td>
      <td data-label="End">${e.end_time ?? "TBA"}</td>
      <td data-label="Capacity">${e.capacity ?? "—"}</td>
      <td data-label="Actions">
        <div class="table-actions">
          <button type="button" class="btn-secondary edit-event-btn" data-id="${e.event_id}">Edit</button>
          <button type="button" class="btn-danger delete-event-btn" data-id="${e.event_id}" data-title="${e.title}">Delete</button>
        </div>
      </td>
    `;
    eventsTbody.appendChild(row);
  });
}

async function loadEvents() {
  clearMessage(listMessage);
  eventsTbody.innerHTML = `<tr><td colspan="7">Loading events...</td></tr>`;

  try {
    const response = await fetch(`${API_BASE}/api/events`);
    if (!response.ok) throw new Error("Failed to load events");
    events = await response.json();
    renderEvents();
  } catch (err) {
    eventsTbody.innerHTML = `<tr><td colspan="7">Could not load events. Is the server running?</td></tr>`;
    console.error(err);
  }
}

function startEdit(id) {
  clearMessage(formMessage);

  // The list endpoint gives us title/start/end/capacity plus the
  // category NAME and location ADDRESS (not their ids) for this event.
  const cached = events.find((e) => String(e.event_id) === String(id));
  if (!cached) {
    showMessage(formMessage, "Could not find that event.");
    return;
  }

  eventIdInput.value = cached.event_id;
  titleInput.value = cached.title || "";
  capacityInput.value = cached.capacity ?? "";
  startInput.value = toDateTimeLocal(cached.start_time);
  endInput.value = toDateTimeLocal(cached.end_time);

  // Best-effort match: find the category/location id whose name/address
  // matches the text this event already shows.
  const matchedCategory = categories.find((c) => c.name === cached.category);
  categorySelect.value = matchedCategory ? matchedCategory.category_id : "";

  const matchedLocation = locations.find((l) => l.address === cached.location);
  locationSelect.value = matchedLocation ? matchedLocation.location_id : "";

  formTitle.textContent = `Editing: ${cached.title}`;
  submitBtn.textContent = "Save Changes";
  cancelBtn.style.display = "inline-block";

  document.getElementById("event-form-section").scrollIntoView({ behavior: "smooth" });
}

async function deleteEvent(id, title) {
  if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;

  try {
    const response = await fetch(`${API_BASE}/api/events/${id}`, {
      method: "DELETE",
      headers: authHeaders,
    });
    const data = await response.json();

    if (!response.ok) {
      showMessage(listMessage, data.message || "Could not delete that event.");
      return;
    }

    loadEvents();
  } catch (err) {
    showMessage(listMessage, "Could not reach the server. Please try again.");
    console.error(err);
  }
}

eventsTbody.addEventListener("click", (event) => {
  const id = event.target.dataset.id;
  if (event.target.classList.contains("edit-event-btn")) {
    startEdit(id);
  } else if (event.target.classList.contains("delete-event-btn")) {
    deleteEvent(id, event.target.dataset.title);
  }
});

eventForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  clearMessage(formMessage);

  const payload = {
    title: titleInput.value.trim(),
    category_id: categorySelect.value ? Number(categorySelect.value) : null,
    location_id: locationSelect.value ? Number(locationSelect.value) : null,
    capacity: capacityInput.value ? Number(capacityInput.value) : null,
    start_time: fromDateTimeLocal(startInput.value),
    end_time: fromDateTimeLocal(endInput.value),
  };

  if (!payload.title) {
    showMessage(formMessage, "Title is required.");
    return;
  }

  const editingId = eventIdInput.value;
  const url = editingId
    ? `${API_BASE}/api/events/${editingId}`
    : `${API_BASE}/api/events`;
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
      showMessage(formMessage, data.message || "Could not save that event.");
      return;
    }

    resetEventForm();
    loadEvents();
  } catch (err) {
    showMessage(formMessage, "Could not reach the server. Please try again.");
    console.error(err);
  } finally {
    submitBtn.disabled = false;
  }
});

// ===========================================================
// INITIAL LOAD
// ===========================================================

(async function init() {
  await Promise.all([loadCategories(), loadLocations()]);
  await loadEvents();
})();
