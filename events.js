// events.js
// Loads activities from the Flask API, supports live search/filter via
// fetch() (no page reload), and lets a logged-in attendee register.

(() => {
// Wrapped in a function so its constants don't clash with announcements.js.
const API_BASE = "http://127.0.0.1:5000";

const tbody = document.getElementById("events-tbody");
const searchForm = document.getElementById("search-form");
const searchInput = document.getElementById("search-activity");
const clearButton = document.getElementById("clear-search");
const messageEl = document.getElementById("activities-message");

function showMessage(text, isError = true) {
  messageEl.textContent = text;
  messageEl.style.color = isError ? "#c0392b" : "#0f7a3d";
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

// Builds the Google Maps link for a location. Uses the saved map_link when it
// points somewhere specific; otherwise searches Google Maps for the address.
function mapsUrl(address, mapLink) {
  if (mapLink) {
    try {
      const u = new URL(mapLink);
      if (u.protocol === "https:" && (u.pathname.length > 1 || u.search)) {
        return u.href;
      }
    } catch (err) {
      // not a valid URL - fall back to searching the address
    }
  }
  return (
    "https://www.google.com/maps/search/?api=1&query=" +
    encodeURIComponent(address)
  );
}

function renderLocation(event) {
  if (!event.location) return "\u2014";
  const url = mapsUrl(event.location, event.map_link);
  return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer"
             style="font-weight:650;color:#0098B3;text-decoration:underline;"
             aria-label="Open ${escapeHtml(event.location)} in Google Maps">
            \uD83D\uDCCD ${escapeHtml(event.location)}</a>`;
}

function formatDateTime(value) {
  if (!value) return "TBA";
  return value; // API already returns a readable string; swap in your own formatting if needed
}

function renderEvents(events) {
  tbody.innerHTML = "";

  if (!events.length) {
    tbody.innerHTML = `<tr><td colspan="7">No activities found.</td></tr>`;
    return;
  }

  events.forEach((event) => {
    const row = document.createElement("tr");

    row.innerHTML = `
      <td>${escapeHtml(event.title)}</td>
      <td>${escapeHtml(event.category) || "—"}</td>
      <td>${renderLocation(event)}</td>
      <td>${formatDateTime(event.start_time)}</td>
      <td>${formatDateTime(event.end_time)}</td>
      <td>${event.capacity ?? "—"} total slots</td>
      <td><button type="button" class="register-btn" data-event-id="${event.event_id}">Register</button></td>
    `;

    tbody.appendChild(row);
  });
}

async function loadEvents(search = "") {
  clearMessage();
  tbody.innerHTML = `<tr><td colspan="7">Loading activities...</td></tr>`;

  const url = new URL(`${API_BASE}/api/events`);
  if (search) {
    url.searchParams.set("search", search);
  }

  try {
    const response = await fetch(url);

    if (!response.ok) {
      throw new Error("Failed to load activities");
    }

    const events = await response.json();
    renderEvents(events);
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7">Could not load activities. Is the server running?</td></tr>`;
    console.error("Failed to load events:", err);
  }
}

async function registerForEvent(eventId) {
  const token = localStorage.getItem("access_token");

  if (!token) {
    showMessage("Please log in as an attendee before registering.");
    return;
  }

  try {
    const response = await fetch(
      `${API_BASE}/api/events/${eventId}/register`,
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
        },
      }
    );

    const data = await response.json();

    if (!response.ok) {
      // e.g. 409 already registered, 400 event full, 403 wrong role
      showMessage(data.message || "Registration failed.");
      return;
    }

    showMessage(data.message || "Registered successfully!", false);
    // Reload so "My Registration Status" (rendered by PHP) shows the new row.
    setTimeout(() => window.location.reload(), 1200);
  } catch (err) {
    showMessage("Could not reach the server. Please try again.");
    console.error("Register request failed:", err);
  }
}

// Event delegation: catches clicks on any Register button, including ones
// added dynamically when the table is re-rendered.
tbody.addEventListener("click", (event) => {
  if (event.target.classList.contains("register-btn")) {
    const eventId = event.target.dataset.eventId;
    registerForEvent(eventId);
  }
});

searchForm.addEventListener("submit", (event) => {
  event.preventDefault();
  loadEvents(searchInput.value.trim());
});

clearButton.addEventListener("click", () => {
  searchInput.value = "";
  loadEvents();
});

// Optional: live filtering as the user types (debounced)
let debounceTimer;
searchInput.addEventListener("input", () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    loadEvents(searchInput.value.trim());
  }, 300);
});

// Initial load
loadEvents();
})();