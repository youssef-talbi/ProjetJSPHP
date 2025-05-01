// Main JavaScript file for improved freelance platform

// Mobile Navigation Toggle
function setupMobileNav() {
    const burger = document.querySelector(".burger");
    const nav = document.querySelector(".nav-links");
    const navLinks = document.querySelectorAll(".nav-links li");

    if (burger && nav) {
        burger.addEventListener("click", () => {
            // Toggle Nav
            nav.classList.toggle("nav-active");

            // Animate Links
            navLinks.forEach((link, index) => {
                if (link.style.animation) {
                    link.style.animation = "";
                } else {
                    link.style.animation = `navLinkFade 0.5s ease forwards ${index / 7 + 0.3}s`;
                }
            });

            // Burger Animation
            burger.classList.toggle("toggle");
        });
    }
}

// Handle window resize for responsive menu
function handleResponsiveMenu() {
    const windowWidth = window.innerWidth;
    const nav = document.querySelector(".nav-links");
    const burger = document.querySelector(".burger");

    if (nav && burger && windowWidth > 768 && nav.classList.contains("nav-active")) {
        nav.classList.remove("nav-active");
        burger.classList.remove("toggle");

        const navLinks = document.querySelectorAll(".nav-links li");
        navLinks.forEach(link => {
            link.style.animation = "";
        });
    }
}

// Form Validation (Basic Example - can be expanded)
function setupFormValidation() {
    const forms = document.querySelectorAll("form.validate-form"); // Add class "validate-form" to forms needing validation

    forms.forEach(form => {
        form.addEventListener("submit", function(event) {
            let isValid = true;
            const requiredFields = form.querySelectorAll("[required]");

            requiredFields.forEach(field => {
                const errorElement = field.parentNode.querySelector(".error-message");
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add("input-error");
                    if (!errorElement) {
                        const errorMsg = document.createElement("div");
                        errorMsg.className = "error-message text-danger mt-1";
                        errorMsg.textContent = "This field is required.";
                        field.parentNode.appendChild(errorMsg);
                    } else {
                        errorElement.textContent = "This field is required.";
                        errorElement.style.display = "block";
                    }
                } else {
                    field.classList.remove("input-error");
                    if (errorElement) {
                        errorElement.style.display = "none";
                    }
                }
            });

            // Add more specific validation rules here (email, password match, etc.)

            if (!isValid) {
                event.preventDefault();
            }
        });
    });
}

// Alert Auto-dismiss
function setupAlertDismiss() {
    const alerts = document.querySelectorAll(".alert[data-dismissible]");
    alerts.forEach(alert => {
        const closeBtn = document.createElement("button");
        closeBtn.className = "alert-close-btn";
        closeBtn.innerHTML = "&times;";
        closeBtn.setAttribute("aria-label", "Close");
        closeBtn.style.cssText = "background:none; border:none; font-size:1.5rem; position:absolute; top:5px; right:10px; cursor:pointer; color:inherit;";
        closeBtn.addEventListener("click", () => {
            alert.style.opacity = "0";
            setTimeout(() => alert.remove(), 300);
        });
        alert.style.position = "relative"; // Ensure button positioning works
        alert.appendChild(closeBtn);

        // Auto-dismiss after 5 seconds if specified
        if (alert.hasAttribute("data-auto-dismiss")) {
            setTimeout(() => {
                 closeBtn.click(); // Trigger the close button click
            }, 5000);
        }
    });
}


// Initialize all functions when DOM is loaded
document.addEventListener("DOMContentLoaded", function() {
    setupMobileNav();
    setupFormValidation();
    setupAlertDismiss();

    // Handle window resize
    window.addEventListener("resize", handleResponsiveMenu);

    // Add any other initializations here
});
