document.addEventListener("DOMContentLoaded", () => {
  console.log("JS Loaded Successfully");
  const roleSelectors = document.querySelectorAll(".role-selector");
  const roleInput = document.getElementById("selectedRole");
  const loginButton = document.getElementById("loginButton");
  const loginForm = document.getElementById("loginForm");

  if (!roleSelectors.length || !roleInput || !loginButton || !loginForm) {
    console.error("Missing DOM elements for login");
    return;
  }

  // Role selection handler
  roleSelectors.forEach(selector => {
    selector.addEventListener("click", () => {
      // Remove "selected" from others
      roleSelectors.forEach(s => s.classList.remove("selected"));

      // Add "selected" to clicked role
      selector.classList.add("selected");

      // Store selected role in hidden input
      roleInput.value = selector.dataset.role;
      console.log("Selected Role:", roleInput.value); // for testing

      // Enable button if role selected
      loginButton.disabled = false;
      loginButton.classList.remove("disabled:opacity-50", "disabled:cursor-not-allowed");
    });
  });

  // Default: Select Alumni for better UX
  const alumniSelector = document.querySelector('[data-role="alumni"]');
  if (alumniSelector) {
    alumniSelector.click();
  }

  // Form submission handler
  loginForm.addEventListener("submit", (e) => {
    if (!roleInput.value) {
      e.preventDefault();
      alert("Please select a role before signing in.");
      return false;
    }
    console.log("Form submitting with role:", roleInput.value);
  });
});