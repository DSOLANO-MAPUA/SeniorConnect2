// register.js
// Handles the registration form: client-side validation + fetch() call to
// the Flask API, without reloading the page.
// Registration uses Full Name + Phone Number + 4-digit PIN (with confirm),
// matching register.php's fields and /api/auth/register's payload.

const API_BASE = "http://127.0.0.1:5000";

const form = document.getElementById("register-form");
const nameInput = document.getElementById("reg-name");
const phoneInput = document.getElementById("reg-phone");
const pinInput = document.getElementById("reg-pin");
const pinConfirmInput = document.getElementById("reg-pin-confirm");

const generalError = document.getElementById("register-error");
const nameError = document.getElementById("reg-name-error");
const phoneError = document.getElementById("reg-phone-error");
const pinError = document.getElementById("reg-pin-error");
const pinConfirmError = document.getElementById("reg-pin-confirm-error");

const allErrorEls = [
  generalError,
  nameError,
  phoneError,
  pinError,
  pinConfirmError,
];

function showFieldError(el, message) {
  el.textContent = message;
  el.style.display = "block";
}

function clearErrors() {
  allErrorEls.forEach((el) => {
    el.textContent = "";
    el.style.display = "none";
  });
}

// Only allow digits to be typed into the PIN fields.
[pinInput, pinConfirmInput].forEach((input) => {
  input.addEventListener("input", () => {
    input.value = input.value.replace(/\D/g, "").slice(0, 4);
  });
});

function validate() {
  clearErrors();
  let valid = true;

  const name = nameInput.value.trim();
  const phone = phoneInput.value.trim();
  const pin = pinInput.value.trim();
  const pinConfirm = pinConfirmInput.value.trim();

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

  // Basic PH-style phone check: digits only, 7-15 characters
  const phonePattern = /^[0-9+()\-\s]{7,15}$/;
  if (!phone) {
    showFieldError(phoneError, "Phone number is required.");
    valid = false;
  } else if (!phonePattern.test(phone)) {
    showFieldError(phoneError, "Enter a valid phone number.");
    valid = false;
  }

  if (!pin) {
    showFieldError(pinError, "A 4-digit PIN is required.");
    valid = false;
  } else if (!/^\d{4}$/.test(pin)) {
    showFieldError(pinError, "PIN must be exactly 4 digits.");
    valid = false;
  }

  if (!pinConfirm) {
    showFieldError(pinConfirmError, "Please confirm your PIN.");
    valid = false;
  } else if (pin && pinConfirm !== pin) {
    showFieldError(pinConfirmError, "PINs do not match.");
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
  submitButton.textContent = "Creating account...";

  try {
    const response = await fetch(`${API_BASE}/api/auth/register`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
      // e.g. 400 missing/invalid fields, 409 phone already exists
      showFieldError(generalError, data.message || "Registration failed.");
      return;
    }

    // Success: send them to login to sign in with their new account
    window.location.href = "login.php";
  } catch (err) {
    showFieldError(
      generalError,
      "Could not reach the server. Please check your connection and try again."
    );
    console.error("Registration request failed:", err);
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = "Create Account";
  }
});
