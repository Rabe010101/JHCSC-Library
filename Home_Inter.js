// --- NEW: Check for Google Login Error on Page Load ---
document.addEventListener("DOMContentLoaded", () => {
    if (window.location.hash === '#error=google_no_account') {
        // Open the modal first
        openModal();
        // Use a timeout to let the modal render before alerting
        setTimeout(() => {
            alert('No account is associated with this Google email. Please use "Create account" to register first.');
        }, 100); // 100ms delay
        
        window.location.hash = ''; // Clean the URL
    }
    // --- MODIFIED ERROR HANDLER ---
    else if (window.location.hash === '#error=google_not_verified') {
        // Open the modal first
        openModal();
        // Use a timeout to let the modal render before alerting
        setTimeout(() => {
            alert('This Google account is registered but not verified. Please log in with your Email and Password to resend the verification code.');
        }, 100); // 100ms delay
        
        window.location.hash = ''; // Clean the URL
    }
});
// --- End of new block ---


// --- Element Selectors ---
const modal = document.getElementById("loginModal");
const loginBtn = document.getElementById("loginBtn");
const getStartedBtn = document.getElementById("getStartedBtn");
const closeBtn = document.querySelector(".close");
const loginForm = document.getElementById("loginForm");
const loginFormElement = document.getElementById("loginFormElement");

// --- Selectors for the new Resend Modal ---
const resendModal = document.getElementById("resendModal");
const closeResendModalBtn = document.getElementById("closeResendModal");
const resendOkBtn = document.getElementById("resendOkBtn");
const resendModalBtn = document.getElementById("resendModalBtn");

// --- Modal Control Functions ---
function openModal() {
    loginForm.style.display = "block";
    modal.style.display = "block";
    document.querySelector(".modal-content").classList.remove("signup-active");
}

function closeModal() {
    modal.style.display = "none";
}

// --- Functions to control the new Resend Modal ---
function openResendModal() {
    if (resendModal) resendModal.style.display = "block";
}
function closeResendModal() {
    if (resendModal) resendModal.style.display = "none";
}

// --- Event Listeners for Modals ---
loginBtn.onclick = openModal;
getStartedBtn.onclick = openModal;
closeBtn.onclick = closeModal;

// MODIFIED: window.onclick to handle both modals
window.onclick = (e) => { 
    if (e.target === modal) closeModal(); 
    if (e.target === resendModal) closeResendModal();
};

// --- Event Listeners for Resend Modal Buttons ---
if (closeResendModalBtn) closeResendModalBtn.onclick = closeResendModal;
if (resendOkBtn) resendOkBtn.onclick = closeResendModal;

if (resendModalBtn) {
    resendModalBtn.onclick = () => {
        const email = document.getElementById("loginEmail").value;
        
        if (!email) {
            alert("Please enter your email in the login form first.");
            return;
        }
        
        closeResendModal();
        alert("Sending new code... Please wait.");
        
        fetch('resend_verification_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email }),
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message); 
            if (result.success) {
                window.location.href = 'signup_verify.php';
            }
        })
        .catch(error => {
            console.error('Resend Error:', error);
            alert('A network error occurred. Please try again.');
        });
    };
}

// --- MODIFIED: Login Logic (Connects to API) ---
loginFormElement.onsubmit = function(e) {
    e.preventDefault();
  
    const loginData = {
        action: 'login',
        email: document.getElementById("loginEmail").value.trim(),
        password: document.getElementById("loginPassword").value.trim()
    };

    fetch('auth_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(loginData),
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            if (result.role === 'admin') {
                window.location.href = "Dashboard.php";
            } else {
                window.location.href = "Final/index.php";
            }
        } else {
            if (result.message && result.message.includes("not verified")) {
                openResendModal();
            } else {
                alert(result.message);
            }
        }
    })
    .catch(error => {
        console.error('Login Error:', error);
        alert('A network error occurred. Please try again.');
    });
};

// --- Show/Hide Password Logic ---
function setupPasswordToggle(inputId, toggleId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = document.getElementById(toggleId);

    if (passwordInput && toggleIcon) {
        toggleIcon.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
}

// Initialize the toggle for all three password fields
setupPasswordToggle('loginPassword', 'toggleLoginPassword');
setupPasswordToggle('newpassword', 'toggleNewPassword');
setupPasswordToggle('confirmpassword', 'toggleConfirmPassword');