document.addEventListener("DOMContentLoaded", () => {
    // --- Element Selectors ---
    const tableBody = document.getElementById("reservationsTableBody");
    const searchBox = document.getElementById("searchBox");
    const statusFilter = document.getElementById("statusFilter");

    // --- OTP Modal Selectors ---
    const otpModal = document.getElementById("otpModal");
    const closeOtpModalBtn = document.getElementById("closeOtpModal");
    const otpForm = document.getElementById("otpForm");
    const otpReservationIdInput = document.getElementById("otpReservationId");
    
    // --- Selectors for 5-box inputs ---
    const otpContainer = document.getElementById("otpContainer");
    const otpInputs = Array.from(otpContainer.querySelectorAll('.otp-input'));
    const otpCodeCombined = document.getElementById("otpCodeCombined");
    const otpUserEmail = document.getElementById("otpUserEmail");
    
    const otpBookTitle = document.getElementById("otpBookTitle");
    
    // Selector for Resend Link
    const resendOtpLink = document.getElementById("resendOtpLink");

    // --- Function to fetch reservations ---
    function fetchReservations() {
        const searchTerm = searchBox.value;
        const status = statusFilter.value;
        
        const cacheBust = new Date().getTime();
        const apiUrl = `reservations_api.php?action=getReservations&search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(status)}&_=${cacheBust}`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                tableBody.innerHTML = "";
                if (data.error) {
                    tableBody.innerHTML = `<tr><td colspan="9" style="text-align:center;">${data.error}</td></tr>`;
                    return;
                }
                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No reservations found.</td></tr>';
                    return;
                }
                data.forEach(res => {
                    const row = tableBody.insertRow();
                    let actionButtonHTML = '';

                    // (Unchanged) Render logic for buttons
                    if (res.status === 'Pending Pickup') {
                        // This logic is correct
                        const otpExpires = res.otp_expires ? new Date(res.otp_expires.replace(' ', 'T') + 'Z') : null;
                        const now = new Date();
                        
                        if (otpExpires && otpExpires > now) {
                            actionButtonHTML = `<button class="btn-enter-code" data-id="${res.id}">Enter Code</button>`;
                        } else {
                            actionButtonHTML = `
                                <button class="btn-claim" data-id="${res.id}">Book Claimed</button>
                                <button class="btn-admin-cancel" data-id="${res.id}">Cancel Reservation</button>
                            `;
                        }
                    } else if (res.status === 'Cancelled') {
                        actionButtonHTML = `<button class="btn-delete" data-id="${res.id}">Delete</button>`;
                    }

                    row.innerHTML = `
                        <td>${res.user_id}</td>
                        <td>${res.name}</td>
                        <td>${res.email}</td>
                        <td>${res.transaction_number}</td>
                        <td>${res.book_title}</td>
                        <td>${new Date(res.reservation_date).toLocaleDateString()}</td>
                        <td>${new Date(res.due_date).toLocaleDateString()}</td>
                        <td><span class="status ${res.status.replace(' ', '-').toLowerCase()}">${res.status}</span></td>
                        <td class="action-cell">${actionButtonHTML}</td> `;
                });
            })
            .catch(error => {
                console.error("Failed to fetch reservations:", error);
                tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">Error loading data.</td></tr>';
            });
    }

    // --- Event Delegation for table buttons ---
    tableBody.addEventListener('click', function(event) {
        const target = event.target;
        const reservationId = target.dataset.id;

        // --- 'Book Claimed' button (sends OTP) ---
        if (target.classList.contains('btn-claim')) {
            if (confirm("This will send an OTP to the user's email to confirm pickup. Proceed?")) {
                target.disabled = true;
                target.textContent = "Sending...";

                fetch('reservations_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'sendClaimOTP', reservationId: reservationId })
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        // Re-fetch to update button state
                        fetchReservations();
                    } else {
                        target.disabled = false;
                        target.textContent = "Book Claimed";
                    }
                });
            }
        }
        
        // --- 'Enter Code' button (opens modal) ---
        else if (target.classList.contains('btn-enter-code')) {
            // Get data from the table row
            const tableRow = target.closest('tr');
            const userEmail = tableRow.cells[2].textContent;
            const bookTitle = tableRow.cells[4].textContent; 
            
            // Set the text in the modal
            if(otpUserEmail) otpUserEmail.textContent = userEmail;
            if(otpBookTitle) otpBookTitle.textContent = bookTitle; 

            // Show the modal
            otpReservationIdInput.value = reservationId;
            otpInputs.forEach(input => input.value = '');
            otpCodeCombined.value = '';
            otpModal.style.display = 'block';
            otpInputs[0].focus();
        }

        // --- 'Admin Cancel' button (unchanged) ---
        else if (target.classList.contains('btn-admin-cancel')) {
            if (confirm("Are you sure you want to cancel this user's reservation? The book will be returned to inventory.")) {
                fetch('reservations_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'adminCancelReservation', reservationId: reservationId })
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        fetchReservations();
                    }
                });
            }
        }

        // --- 'Delete' button (unchanged) ---
        else if (target.classList.contains('btn-delete')) {
            if (confirm("Are you sure you want to permanently delete this cancelled reservation?")) {
                fetch('reservations_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'deleteCancelledReservation', reservationId: reservationId })
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        fetchReservations();
                    }
                });
            }
        }
    });

    // --- Event Listeners for OTP Modal ---
    
    // Close button
    closeOtpModalBtn.addEventListener('click', () => {
        otpModal.style.display = 'none';
    });

    // Clicking outside the modal
    window.addEventListener('click', (event) => {
        if (event.target == otpModal) {
            otpModal.style.display = 'none';
        }
    });

    // OTP Form submission
    otpForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const reservationId = otpReservationIdInput.value;
        const otp = otpCodeCombined.value;
        const submitBtn = otpForm.querySelector('button[type="submit"]');

        if (otp.length !== 5) {
            alert("Please enter a 5-digit OTP.");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Verifying...";

        fetch('reservations_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'verifyAndClaimReservation', 
                reservationId: reservationId,
                otp: otp 
            })
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                otpModal.style.display = 'none';
                fetchReservations();
            }
            submitBtn.disabled = false;
            submitBtn.textContent = "Verify and Issue Book";
        });
    });

    // --- MODIFIED: Click listener for Resend Link ---
    resendOtpLink.addEventListener('click', (e) => {
        e.preventDefault();
        
        const reservationId = otpReservationIdInput.value;
        if (!reservationId) {
            alert("An error occurred. Please close this modal and try again.");
            return;
        }

        e.target.textContent = "Sending..."; // Only change the link's text
        e.target.style.pointerEvents = 'none'; // Disable link

        fetch('reservations_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sendClaimOTP', reservationId: reservationId })
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message); // "OTP sent" or "Error"
            
            // Re-enable the link
            e.target.textContent = "Resend code"; // Change text back
            e.target.style.pointerEvents = 'auto';
            
            if (result.success) {
                // Focus the first input box
                otpInputs[0].focus();
            }
        });
    });


    // --- Logic for the 5 OTP boxes ---
    if (otpContainer) {
        // (This code is unchanged)
        function combineInputs() {
            let code = '';
            otpInputs.forEach(input => {
                code += input.value;
            });
            otpCodeCombined.value = code;
        }

        otpContainer.addEventListener('input', (e) => {
            const target = e.target;
            const index = parseInt(target.dataset.index, 10);
            
            if (target.value.length > 1) {
                target.value = target.value.slice(0, 1);
            }
            if (!/^\d*$/.test(target.value)) {
                target.value = '';
                return;
            }
            if (target.value !== '' && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
            combineInputs();
        });

        otpContainer.addEventListener('keydown', (e) => {
            const target = e.target;
            const index = parseInt(target.dataset.index, 10);
            if (e.key === 'Backspace' && target.value === '' && index > 0) {
                otpInputs[index - 1].focus();
            }
            combineInputs();
        });
        
        otpContainer.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').slice(0, 5);
            
            pasteData.split('').forEach((char, i) => {
                if (otpInputs[i] && /^\d$/.test(char)) {
                    otpInputs[i].value = char;
                }
            });
            const lastFilledIndex = Math.min(pasteData.length, otpInputs.length) - 1;
            if (lastFilledIndex >= 0) {
                otpInputs[lastFilledIndex].focus();
            }
            combineInputs();
        });
    }

    // --- Initial Load ---
    searchBox.addEventListener('input', fetchReservations);
    statusFilter.addEventListener('change', fetchReservations);

    fetchReservations();
});