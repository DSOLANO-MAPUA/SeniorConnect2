// announcements.js
// Loads real announcements from the Flask API onto the public homepage,
// so anything a host posts/edits/deletes on manage-announcements.php
// shows up here automatically.

(() => {
// Wrapped in a function so its constants don't clash with events.js
// (both scripts load on index.php and both declare API_BASE).
const API_BASE = "http://127.0.0.1:5000";

const listEl = document.getElementById("announcements-list");

// Flask sends "2026-09-29 13:30:00"; some browsers (Safari) can't parse the
// space, so swap it for a "T" before creating the Date.
function parseServerDate(value) {
  return new Date(String(value).replace(" ", "T"));
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text || "";
  return div.innerHTML;
}

async function loadAnnouncements() {
  try {
    const response = await fetch(`${API_BASE}/api/announcements`);
    if (!response.ok) throw new Error("Failed to load announcements");

    const announcements = await response.json();
    const now = new Date();

    // Only show announcements that aren't scheduled for the future.
    const visible = announcements.filter((a) => {
      if (!a.schedule) return true;
      const when = parseServerDate(a.schedule);
      return isNaN(when) || when <= now;
    });

    visible.sort((a, b) => parseServerDate(b.time_created) - parseServerDate(a.time_created));

    if (!visible.length) {
      listEl.innerHTML = "<li>No announcements right now. Check back soon!</li>";
      return;
    }

    listEl.innerHTML = "";
    visible.forEach((a) => {
      const li = document.createElement("li");
      li.innerHTML = `
        <strong>${escapeHtml(a.title)}</strong><br>
        <p>${escapeHtml(a.content)}</p>
      `;
      listEl.appendChild(li);
    });
  } catch (err) {
    listEl.innerHTML = "<li>Could not load announcements. Is the server running?</li>";
    console.error("Failed to load announcements:", err);
  }
}

loadAnnouncements();
})();