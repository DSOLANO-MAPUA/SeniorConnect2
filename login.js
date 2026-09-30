// login.js
// Handles the login form: client-side validation + fetch() call to the
// Flask API, without reloading the page.
// Login now uses Full Name + Phone Number + 4-digit PIN instead of
// email/password.

const API_BASE = "http://127.0.0.1:5000";

const form = document.getElementById("login-form");
const nameInput = document.getElementById("login-name");
const phoneInput = document.getElementById("login-phone");
const pinInput = document.getElementById("login-pin");

const generalError = document.getElementById("login-error");
const nameError = document.getElementById("login-name-error");
const phoneError = document.getElementById("login-phone-error");
const pinError = document.getElementById("login-pin-error");

function showFieldError(el, message) {
  el.textContent = message;
  el.style.display = "block";
}

function clearErrors() {
  [generalError, nameError, phoneError, pinError].forEach((el) => {
    el.textContent = "";
    el.style.display = "none";
  });
}

// Only allow digits to be typed into the PIN field.
pinInput.addEventListener("input", () => {
  pinInput.value = pinInput.value.replace(/\D/g, "").slice(0, 4);
});

function validate() {
  clearErrors();
  let valid = true;

  const name = nameInput.value.trim();
  const phone = phoneInput.value.trim();
  const pin = pinInput.value.trim();

  // NAME VALIDATION
  if (!name) {
    showFieldError(nameError, "Full name is required.");
    valid = false;
  } else if (!/^[A-Za-zÀ-ÿ\s'-]+$/.test(name)) {
    showFieldError(
      nameError,
      "Full name must contain letters only."
    );
    valid = false;
  }

  const phonePattern = /^[0-9+()\-\s]{7,15}$/;
  if (!phone) {
    showFieldError(phoneError, "Phone number is required.");
    valid = false;
  } else if (!phonePattern.test(phone)) {
    showFieldError(phoneError, "Enter a valid phone number.");
    valid = false;
  }

  if (!pin) {
    showFieldError(pinError, "Your 4-digit PIN is required.");
    valid = false;
  } else if (!/^\d{4}$/.test(pin)) {
    showFieldError(pinError, "PIN must be exactly 4 digits.");
    valid = false;
  }

  return valid;
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();

  if (!validate()) {
    return;
  }

  const payload = {
    name: nameInput.value.trim(),
    phone: phoneInput.value.trim(),
    code: pinInput.value.trim(),
  };

  const submitButton = form.querySelector("button[type='submit']");
  submitButton.disabled = true;
  submitButton.textContent = "Logging in...";

  try {
    // Goes through login.php, which checks with Flask and starts the PHP session.
    const response = await fetch("login.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
      // e.g. 401 invalid name/phone/PIN
      showFieldError(generalError, data.message || "Login failed.");
      return;
    }

    // Success: store the token/user info and redirect
    localStorage.setItem("access_token", data.access_token);
    localStorage.setItem("user", JSON.stringify(data.user));

    // Determine where to send them based on role: admins/staff go to the
    // host dashboard, attendees go to the regular homepage.
    const role = data.user && data.user.role;

    if (role === "admin") {
      window.location.href = "host-dashboard.php";
    } else if (role === "staff") {
      window.location.href = "staff.php";
    } else {
      window.location.href = "index.php";
    }
  } catch (err) {
    showFieldError(
      generalError,
      "Could not reach the server. Please check your connection and try again."
    );
    console.error("Login request failed:", err);
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = "Log In";
  }
});