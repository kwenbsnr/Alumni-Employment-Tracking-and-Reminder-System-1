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

  // Check if we're returning from a failed login attempt
  const hasPreviousAttempt = loginPassword.classList.contains('password-retry-field');
  
  // Validation functions
  function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  function showError(element, message) {
    element.textContent = message;
    element.classList.remove('hidden');
  }

  function hideError(element) {
    element.textContent = '';
    element.classList.add('hidden');
  }

  function markInputError(input, hasError) {
    if (hasError) {
      input.classList.add('input-error');
      if (input === loginPassword) {
        input.classList.add('password-error-highlight');
      }
    } else {
      input.classList.remove('input-error');
      input.classList.remove('password-error-highlight');
    }
  }

  // ---------- Email validation ----------
  loginEmail.addEventListener('blur', () => {
    const email = loginEmail.value.trim();
    
    if (email === '') {
      showError(emailError, 'Email is required');
      markInputError(loginEmail, true);
    } else if (!validateEmail(email)) {
      showError(emailError, 'Please enter a valid email address');
      markInputError(loginEmail, true);
    } else {
      hideError(emailError);
      markInputError(loginEmail, false);
    }
  });

  // ---------- Password validation (MODIFIED: Removed length check) ----------
  loginPassword.addEventListener('blur', () => {
    const password = loginPassword.value;
    
    if (password === '') {
      showError(passwordError, 'Password is required');
      markInputError(loginPassword, true);
    } else {
      // REMOVED: Password length validation
      hideError(passwordError);
      markInputError(loginPassword, false);
    }
  });

  // ---------- Enhanced password validation for retry attempts ----------
  loginPassword.addEventListener('input', () => {
    const password = loginPassword.value;
    
    if (hasPreviousAttempt && password.length > 0) {
      // Clear any previous error styling when user starts typing
      hideError(passwordError);
      markInputError(loginPassword, false);
      
      // Remove the retry field styling as user is correcting
      loginPassword.classList.remove('password-retry-field');
    }
    
    if (password === '') {
      showError(passwordError, 'Password is required');
      markInputError(loginPassword, true);
    } else {
      hideError(passwordError);
      markInputError(loginPassword, false);
    }
  });

  // ---------- Password visibility toggle ----------
  togglePassword.addEventListener("click", () => {
    // Check if password field is empty
    if (!loginPassword.value.trim()) {
      showCustomAlert("Please enter a password");
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
      
      // Focus password field if returning from failed attempt, otherwise email
      if (hasPreviousAttempt) {
        loginPassword.focus();
      } else {
        loginEmail.focus();
      }
    });
  });

  // Auto-select role if returning from failed attempt
  if (roleInput.value) {
    const selectedRole = document.querySelector(`[data-role="${roleInput.value}"]`);
    if (selectedRole) {
      selectedRole.click();
    }
  } else {
    // Auto-select Alumni on load (UX)
    const alumni = document.querySelector('[data-role="alumni"]');
    if (alumni) alumni.click();
  }

  // Focus password field if returning from failed attempt
  if (hasPreviousAttempt) {
    setTimeout(() => {
      loginPassword.focus();
      loginPassword.select(); // Select all text for easy re-entry
    }, 100);
  }

  // ---------- Form submit validation (MODIFIED: Removed password length check) ----------
  loginForm.addEventListener("submit", function(e) {
    console.log("Form submission triggered");
    
    let hasErrors = false;

    // Validate role
    if (!roleInput.value) {
      showCustomAlert("Please select a role before signing in.");
      hasErrors = true;
      e.preventDefault();
      return;
    }

    // Validate email
    const email = loginEmail.value.trim();
    if (email === '') {
      showError(emailError, 'Email is required');
      markInputError(loginEmail, true);
      hasErrors = true;
    } else if (!validateEmail(email)) {
      showError(emailError, 'Please enter a valid email address');
      markInputError(loginEmail, true);
      hasErrors = true;
    }

    // Validate password (MODIFIED: Only check if password is empty, not length)
    const password = loginPassword.value;
    if (password === '') {
      showError(passwordError, 'Password is required');
      markInputError(loginPassword, true);
      hasErrors = true;
    }

    if (hasErrors) {
      e.preventDefault();
      showCustomAlert("Please fix the errors above before submitting.");
      return;
    }

    // Show loading state for retry attempts
    if (hasPreviousAttempt) {
      const originalText = loginButton.innerHTML;
      loginButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Verifying...';
      loginButton.disabled = true;
      
      // Restore button text after 3 seconds if still on page
      setTimeout(() => {
        if (loginButton.innerHTML.includes('fa-spinner')) {
          loginButton.innerHTML = originalText;
          loginButton.disabled = false;
        }
      }, 3000);
    }

    console.log("Form validation passed, submitting to database...");
  });

  // ---------- Enter key on inputs ----------
  [loginEmail, loginPassword].forEach(input => {
    input.addEventListener("keydown", ev => {
      if (ev.key === "Enter") {
        ev.preventDefault();
        if (!loginButton.disabled) {
          loginForm.dispatchEvent(new Event('submit'));
        } else {
          showCustomAlert("Please select a role first.");
        }
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