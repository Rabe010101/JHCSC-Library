// --- New Account Page Functions ---

// This function fetches the current user's data from the server
function fetchAccountData() {
    fetch('api.php?action=getUserData')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Combine firstname and surname for the display
                const fullName = `${data.user.firstname} ${data.user.surname}`;
                
                // Populate the display fields
                document.getElementById('account-name').textContent = fullName;
                document.getElementById('account-course').textContent = data.user.course;
                document.getElementById('account-yearLevel').textContent = data.user.year;
                document.getElementById('account-email').textContent = data.user.email;
                document.getElementById('account-password').textContent = '********'; // Mask password
            } else {
                // Handle cases where user data couldn't be fetched
                console.error('Could not fetch user data.');
            }
        });
}

// This function shows the edit form and populates it with current data
function showEditForm() {
    // Populate the form with the current values from the display
    document.getElementById('edit-name').value = document.getElementById('account-name').textContent;
    document.getElementById('edit-course').value = document.getElementById('account-course').textContent;
    document.getElementById('edit-yearLevel').value = document.getElementById('account-yearLevel').textContent;
    document.getElementById('edit-email').value = document.getElementById('account-email').textContent;
    document.getElementById('edit-password').value = ''; // Keep password field empty for security

    // Swap visibility
    document.getElementById('account-info-display').classList.add('hidden');
    document.getElementById('account-edit-form').classList.remove('hidden');
}

// This function hides the form and shows the info display
function cancelEdit() {
    document.getElementById('account-info-display').classList.remove('hidden');
    document.getElementById('account-edit-form').classList.add('hidden');
}

// This function saves the updated data to the database
function saveEdit() {
    // Get the new values from the form fields
    const updatedData = {
        name: document.getElementById('edit-name').value,
        course: document.getElementById('edit-course').value,
        yearLevel: document.getElementById('edit-yearLevel').value,
        email: document.getElementById('edit-email').value,
        password: document.getElementById('edit-password').value // Will be empty if not changed
    };

    // Send the data to the server via a POST request
    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'updateUser', data: updatedData })
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            showModal('Success', 'Your account information has been updated.');
            // Refresh the displayed data and hide the form
            fetchAccountData();
            cancelEdit();
        } else {
            showModal('Error', result.message || 'An error occurred. Please try again.');
        }
    });
}

// This function toggles password visibility in the form
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('edit-password');
    const icon = document.getElementById('password-toggle-icon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
