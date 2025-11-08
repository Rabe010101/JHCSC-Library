document.addEventListener("DOMContentLoaded", () => {
    // --- Element Selectors ---
    const editModal = document.getElementById("editModal");
    const editAccountBtn = document.getElementById("editAccountBtn");
    const closeBtn = editModal.querySelector(".close");
    const editForm = document.getElementById("editForm");

    // --- Display Elements ---
    const displayFirstname = document.getElementById("displayFirstname");
    const displaySurname = document.getElementById("displaySurname");
    const displayEmail = document.getElementById("displayEmail");

    // --- Form Input Elements ---
    const editFirstname = document.getElementById("editFirstname");
    const editSurname = document.getElementById("editSurname");
    const editPassword = document.getElementById("editPassword");

    // --- Function to fetch and display admin data ---
    function fetchAdminData() {
        fetch('admin_api.php?action=getAdminData')
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    displayFirstname.textContent = result.data.firstname;
                    displaySurname.textContent = result.data.surname;
                    displayEmail.textContent = result.data.email;
                } else {
                    alert(result.message || 'Could not load admin data.');
                }
            })
            .catch(error => console.error('Fetch error:', error));
    }

    // --- Modal Control ---
    editAccountBtn.onclick = () => {
        // Populate the form with the current data before showing
        editFirstname.value = displayFirstname.textContent;
        editSurname.value = displaySurname.textContent;
        editPassword.value = ''; // Always clear password field for security
        editModal.style.display = "block";
    };

    closeBtn.onclick = () => editModal.style.display = "none";
    window.onclick = (e) => { if (e.target === editModal) editModal.style.display = "none"; };

    // --- Form Submission Logic ---
    editForm.onsubmit = (e) => {
        e.preventDefault();
        
        const updatedData = {
            firstname: editFirstname.value,
            surname: editSurname.value,
            password: editPassword.value // This will be empty if not changed
        };

        fetch('admin_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'updateAdminData', ...updatedData })
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                editModal.style.display = "none";
                fetchAdminData(); // Refresh the displayed data
            }
        })
        .catch(error => console.error('Update error:', error));
    };

    // --- Logout Button ---
    document.getElementById("logoutBtn").onclick = () => {
        if (confirm("Are you sure you want to log out?")) {
            window.location.href = "logout.php";
        }
    };

    // --- Initial Load ---
    // Fetch the admin's data as soon as the page loads
    fetchAdminData();
});

// this is a comment made by bilat na baho