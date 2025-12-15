document.addEventListener("DOMContentLoaded", () => {
    // --- Element Selectors ---
    const tableBody = document.getElementById("reservationsTableBody");
    const searchBox = document.getElementById("searchBox");
    const statusFilter = document.getElementById("statusFilter");

    // --- Pagination Selectors ---
    const prevBtn = document.getElementById("prev-btn");
    const nextBtn = document.getElementById("next-btn");
    const pageInfo = document.getElementById("page-info");

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
    const resendOtpLink = document.getElementById("resendOtpLink");

    // --- Global Pagination Variables ---
    let currentPage = 1;
    const itemsPerPage = 20;

    // --- Main Function to Fetch Reservations (With Pagination) ---
    function fetchReservations(page = 1) {
        const searchTerm = searchBox.value;
        const status = statusFilter.value;
        
        const cacheBust = new Date().getTime();
        // Updated API URL with page and limit
        const apiUrl = `reservations_api.php?action=getReservations&search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(status)}&page=${page}&limit=${itemsPerPage}&_=${cacheBust}`;

        fetch(apiUrl, { credentials: 'same-origin' })
            .then(response => response.json())
            .then(response => {
                // Handle new API response structure
                const data = response.data || []; 
                const pagination = response.pagination;

                tableBody.innerHTML = "";
                
                if (response.error) {
                    tableBody.innerHTML = `<tr><td colspan="9" style="text-align:center;">${response.error}</td></tr>`;
                    return;
                }
                if (!data || data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding: 20px;">No reservations found.</td></tr>';
                    if (pagination) updatePaginationControls(0, 0, 1);
                    return;
                }

                data.forEach(res => {
                    const row = tableBody.insertRow();
                    let actionButtonHTML = '';

                    // Logic to determine which buttons to show
                    if (res.status === 'Pending Pickup') {
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

                // Update Pagination Buttons
                if (pagination) {
                    updatePaginationControls(pagination.totalRecords, pagination.totalPages, pagination.currentPage);
                }
            })
            .catch(error => {
                console.error("Failed to fetch reservations:", error);
                tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">Error loading data.</td></tr>';
            });
    }

    // --- Pagination UI Logic ---
    function updatePaginationControls(totalRecords, totalPages, current) {
        currentPage = current;
        if (pageInfo) pageInfo.textContent = `Page ${current} of ${totalPages || 1} (Total: ${totalRecords})`;

        if (prevBtn && nextBtn) {
            // Previous Button
            if (current <= 1) {
                prevBtn.disabled = true;
                prevBtn.style.background = '#ccc';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.background = 'rgb(0, 153, 38)'; // Green to match theme
                prevBtn.style.cursor = 'pointer';
            }
            // Next Button
            if (current >= totalPages || totalPages === 0) {
                nextBtn.disabled = true;
                nextBtn.style.background = '#ccc';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.background = 'rgb(0, 153, 38)';
                nextBtn.style.cursor = 'pointer';
            }
        }
    }

    // --- Event Listeners for Filters & Pagination ---
    
    function updateFilters() {
        currentPage = 1; // Always reset to page 1 on new search
        fetchReservations(1);
    }
    
    if (searchBox) searchBox.addEventListener('input', updateFilters);
    if (statusFilter) statusFilter.addEventListener('change', updateFilters);

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) fetchReservations(currentPage - 1);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (!nextBtn.disabled) fetchReservations(currentPage + 1);
        });
    }

    // ============================================================
    //  EXISTING EVENT DELEGATION & MODAL LOGIC (UNCHANGED)
    // ============================================================

    // --- Event Delegation for table buttons ---
    tableBody.addEventListener('click', function(event) {
        const target = event.target;
        const reservationId = target.dataset.id;

        // 'Book Claimed' button (sends OTP)
        if (target.classList.contains('btn-claim')) {
            if (confirm("This will send an OTP to the user's email to confirm pickup. Proceed?")) {
                target.disabled = true;
                target.textContent = "Sending...";

                fetch('reservations_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'sendClaimOTP', reservationId: reservationId }),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        fetchReservations(currentPage); // Refresh current page
                    } else {
                        target.disabled = false;
                        target.textContent = "Book Claimed";
                    }
                });
            }
        }
        
        // 'Enter Code' button (opens modal)
        else if (target.classList.contains('btn-enter-code')) {
            const tableRow = target.closest('tr');
            const userEmail = tableRow.cells[2].textContent;
            const bookTitle = tableRow.cells[4].textContent; 
            
            if(otpUserEmail) otpUserEmail.textContent = userEmail;
            if(otpBookTitle) otpBookTitle.textContent = bookTitle; 

            otpReservationIdInput.value = reservationId;
            otpInputs.forEach(input => input.value = '');
            otpCodeCombined.value = '';
            otpModal.style.display = 'block';
            otpInputs[0].focus();
        }

        // 'Admin Cancel' button
        else if (target.classList.contains('btn-admin-cancel')) {
            if (confirm("Are you sure you want to cancel this user's reservation? The book will be returned to inventory.")) {
                fetch('reservations_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'adminCancelReservation', reservationId: reservationId }),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        fetchReservations(currentPage);
                    }
                });
            }
        }

        // 'Delete' button
        else if (target.classList.contains('btn-delete')) {
            if (confirm("Are you sure you want to permanently delete this cancelled reservation?")) {
                fetch('reservations_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'deleteCancelledReservation', reservationId: reservationId }),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        fetchReservations(currentPage);
                    }
                });
            }
        }
    });

    // --- Modal Logic ---
    closeOtpModalBtn.addEventListener('click', () => {
        otpModal.style.display = 'none';
    });

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
            }),
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                otpModal.style.display = 'none';
                fetchReservations(currentPage);
            }
            submitBtn.disabled = false;
            submitBtn.textContent = "Verify and Issue Book";
        });
    });

    // Resend OTP Link
    if (resendOtpLink) {
        resendOtpLink.addEventListener('click', (e) => {
            e.preventDefault();
            const reservationId = otpReservationIdInput.value;
            if (!reservationId) {
                alert("An error occurred. Please close this modal and try again.");
                return;
            }

            e.target.textContent = "Sending...";
            e.target.style.pointerEvents = 'none';

            fetch('reservations_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'sendClaimOTP', reservationId: reservationId }),
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(result => {
                alert(result.message);
                e.target.textContent = "Resend code";
                e.target.style.pointerEvents = 'auto';
                if (result.success) {
                    otpInputs[0].focus();
                }
            });
        });
    }

    // Logic for the 5 OTP boxes
    if (otpContainer) {
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

    // Initial Load
    fetchReservations(1);
});