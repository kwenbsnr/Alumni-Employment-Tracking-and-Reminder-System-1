document.addEventListener("DOMContentLoaded", () => {
  console.log("JS Loaded");

  const roleSelectors = document.querySelectorAll(".role-selector");
  const roleInput     = document.getElementById("selectedRole");
  const loginButton   = document.getElementById("loginButton");
  const loginForm     = document.getElementById("loginForm");
  const loginEmail    = document.getElementById("loginEmail");
  const loginPassword = document.getElementById("loginPassword");
  const togglePassword = document.getElementById("togglePassword");
  const emailError    = document.getElementById("emailError");
  const passwordError = document.getElementById("passwordError");
  const body          = document.body;

  if (!roleSelectors.length || !roleInput || !loginButton || !loginForm || !loginEmail || !loginPassword || !togglePassword) {
    console.error("Missing required DOM elements");
    return;
  }

  // Validation functions
  const validateEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  const validatePassword = (password) => {
    return password.length >= 6; // Minimum 6 characters
  };

  const showFieldError = (field, errorElement, message) => {
    field.classList.add('error');
    errorElement.textContent = message;
    errorElement.classList.remove('hidden');
  };

  const clearFieldError = (field, errorElement) => {
    field.classList.remove('error');
    errorElement.classList.add('hidden');
  };

  // Email validation
  loginEmail.addEventListener('blur', () => {
    const email = loginEmail.value.trim();
    
    if (email === '') {
      showFieldError(loginEmail, emailError, 'Email is required');
    } else if (!validateEmail(email)) {
      showFieldError(loginEmail, emailError, 'Please enter a valid email address');
    } else {
      clearFieldError(loginEmail, emailError);
    }
  });

  // Password validation
  loginPassword.addEventListener('blur', () => {
    const password = loginPassword.value;
    
    if (password === '') {
      showFieldError(loginPassword, passwordError, 'Password is required');
    } else if (!validatePassword(password)) {
      showFieldError(loginPassword, passwordError, 'Password must be at least 6 characters');
    } else {
      clearFieldError(loginPassword, passwordError);
    }
  });

  // Real-time validation for better UX
  loginEmail.addEventListener('input', () => {
    const email = loginEmail.value.trim();
    if (email && validateEmail(email)) {
      clearFieldError(loginEmail, emailError);
    }
  });

  loginPassword.addEventListener('input', () => {
    const password = loginPassword.value;
    if (password && validatePassword(password)) {
      clearFieldError(loginPassword, passwordError);
    }
  });

  // ---------- Password visibility toggle ----------
  togglePassword.addEventListener("click", () => {
    // Check if password field is empty
    if (!loginPassword.value.trim()) {
      showCustomAlert("Please enter a valid password");
      return;
    }
    
    // Toggle password visibility
    const type = loginPassword.getAttribute("type") === "password" ? "text" : "password";
    loginPassword.setAttribute("type", type);
    
    // Toggle eye icon
    const icon = togglePassword.querySelector("i");
    if (type === "text") {
      icon.classList.remove("fa-eye");
      icon.classList.add("fa-eye-slash");
    } else {
      icon.classList.remove("fa-eye-slash");
      icon.classList.add("fa-eye");
    }
  });

  // ---------- Role selection ----------
  roleSelectors.forEach(selector => {
    selector.addEventListener("click", () => {
      // Remove all selections and role classes from body
      roleSelectors.forEach(s => s.classList.remove("selected"));
      body.classList.remove("alumni-selected", "admin-selected");
      
      // Add new selection
      selector.classList.add("selected");
      roleInput.value = selector.dataset.role;
      console.log("Role selected:", roleInput.value);

      // Add role class to body for CSS theming
      if (roleInput.value === "admin") {
        body.classList.add("admin-selected");
      } else {
        body.classList.add("alumni-selected");
      }

      // Enable button and apply appropriate styles
      loginButton.disabled = false;
      
      // Remove all existing button classes
      loginButton.classList.remove(
        "bg-gray-300", "text-gray-500", "cursor-not-allowed",
        "bg-green-600", "hover:bg-green-700"
      );
      
      // Add base enabled styles
      loginButton.classList.add("cursor-pointer", "text-white");
      
      // Focus email field
      loginEmail.focus();
    });
  });

  // Auto-select Alumni on load (UX)
  const alumni = document.querySelector('[data-role="alumni"]');
  if (alumni && !roleInput.value) alumni.click();

  // ---------- Form submit validation ----------
  loginForm.addEventListener("submit", e => {
    // Clear previous errors
    clearFieldError(loginEmail, emailError);
    clearFieldError(loginPassword, passwordError);

    let isValid = true;
    
    // Check role
    if (!roleInput.value) {
      e.preventDefault();
      showCustomAlert("Please select a role before signing in.");
      isValid = false;
    }
    
    // Validate email
    const email = loginEmail.value.trim();
    if (!email) {
      showFieldError(loginEmail, emailError, 'Email is required');
      isValid = false;
    } else if (!validateEmail(email)) {
      showFieldError(loginEmail, emailError, 'Please enter a valid email address');
      isValid = false;
    }
    
    // Validate password
    const password = loginPassword.value;
    if (!password) {
      showFieldError(loginPassword, passwordError, 'Password is required');
      isValid = false;
    } else if (!validatePassword(password)) {
      showFieldError(loginPassword, passwordError, 'Password must be at least 6 characters');
      isValid = false;
    }
    
    if (!isValid) {
      e.preventDefault();
      // Focus on first error field
      if (!email || !validateEmail(email)) {
        loginEmail.focus();
      } else if (!password || !validatePassword(password)) {
        loginPassword.focus();
      }
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
  function showCustomAlert(message, type = 'error') {
    // Remove existing alert if any
    const existingAlert = document.querySelector('.custom-alert');
    if (existingAlert) {
      existingAlert.remove();
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `custom-alert ${type}`;
    alertDiv.innerHTML = `
      <div class="alert-content">
        <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} alert-icon"></i>
        <span class="alert-message">${message}</span>
        <button class="alert-close" onclick="this.parentElement.parentElement.remove()">
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