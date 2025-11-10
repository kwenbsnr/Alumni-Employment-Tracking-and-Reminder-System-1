// login.js
document.addEventListener("DOMContentLoaded", () => {
  console.log("JS Loaded");

  const roleSelectors = document.querySelectorAll(".role-selector");
  const roleInput     = document.getElementById("selectedRole");
  const loginButton   = document.getElementById("loginButton");
  const loginForm     = document.getElementById("loginForm");
  const loginEmail    = document.getElementById("loginEmail");
  const loginPassword = document.getElementById("loginPassword");

  if (!roleSelectors.length || !roleInput || !loginButton || !loginForm || !loginEmail || !loginPassword) {
    console.error("Missing required DOM elements");
    return;
  }

  // ---------- Role selection ----------
  roleSelectors.forEach(selector => {
    selector.addEventListener("click", () => {
      roleSelectors.forEach(s => s.classList.remove("selected"));
      selector.classList.add("selected");
      roleInput.value = selector.dataset.role;
      console.log("Role selected:", roleInput.value);

      // Enable button
      loginButton.disabled = false;
      loginButton.classList.remove("bg-gray-300", "text-gray-500", "cursor-not-allowed");
      loginButton.classList.add("bg-green-600", "hover:bg-green-700", "text-white", "cursor-pointer");
      loginEmail.focus();
    });
  });

  // Auto-select Alumni on load (UX)
  const alumni = document.querySelector('[data-role="alumni"]');
  if (alumni && !roleInput.value) alumni.click();

  // ---------- Form submit validation ----------
  loginForm.addEventListener("submit", e => {
    if (!roleInput.value) {
      e.preventDefault();
      showCustomAlert("Please select a role before signing in.");
    }
  });

  // ---------- Enter key on inputs ----------
  [loginEmail, loginPassword].forEach(input => {
    input.addEventListener("keydown", ev => {
      if (ev.key === "Enter") {
        ev.preventDefault();
        if (!loginButton.disabled) loginButton.click();
        else showCustomAlert("Please select a role first.");
      }
    });
  });

  // ---------- Custom alert function ----------
  function showCustomAlert(message) {
    // Remove existing alert if any
    const existingAlert = document.querySelector('.custom-alert');
    if (existingAlert) {
      existingAlert.remove();
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = 'custom-alert fixed top-20 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg z-50 max-w-sm';
    alertDiv.innerHTML = `
      <div class="flex items-center">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <span class="font-medium">${message}</span>
        <button class="ml-auto text-red-500 hover:text-red-700" onclick="this.parentElement.parentElement.remove()">
          <i class="fas fa-times"></i>
        </button>
      </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
      if (alertDiv.parentElement) {
        alertDiv.remove();
      }
    }, 5000);
  }

  // Make function globally available
  window.showCustomAlert = showCustomAlert;
});