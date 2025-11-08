document.addEventListener("DOMContentLoaded", () => {
    // --- Element Selectors ---
    const tableBody = document.getElementById("notReturnedTableBody");
    const searchBox = document.getElementById("searchBox");

    // --- OTP Modal Selectors ---
    const otpModal = document.getElementById("otpModal");
    const closeOtpModalBtn = document.getElementById("closeOtpModal");
    const otpForm = document.getElementById("otpForm");
    const otpIssuedIdInput = document.getElementById("otpIssuedId");
    const otpBookTitle = document.getElementById("otpBookTitle");
    const otpUserEmail = document.getElementById("otpUserEmail");
    const otpContainer = document.getElementById("otpContainer");
    const otpInputs = Array.from(otpContainer.querySelectorAll('.otp-input'));
    const otpCodeCombined = document.getElementById("otpCodeCombined");
    const resendOtpLink = document.getElementById("resendOtpLink");

    // --- Function to fetch not returned books ---
    function fetchNotReturnedBooks() {
        const searchTerm = searchBox.value;
        const cacheBust = new Date().getTime();
        
        // --- USING not_returned_api.php ---
        const apiUrl = `not_returned_api.php?action=getNotReturnedBooks&search=${encodeURIComponent(searchTerm)}&_=${cacheBust}`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                tableBody.innerHTML = "";
                
                if (data.error) {
                    console.error("API Error:", data.error);
                    tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Error loading data: ${data.error}</td></tr>`;
                    return;
                }

                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No overdue books found.</td></tr>';
                    return;
                }

                data.forEach(book => {
                    const row = tableBody.insertRow();
                    let actionButtonHTML = '';

                    // --- NEW: Render Logic ---
                    const otpExpires = book.otp_expires ? new Date(book.otp_expires.replace(' ', 'T') + 'Z') : null;
                    const now = new Date();
                    
                    if (otpExpires && otpExpires > now) {
                        actionButtonHTML = `<button class="btn-enter-code" data-id="${book.id}">Enter Code</button>`;
                    } else {
                        // Using .btn-return style from NotReturned.css
                        actionButtonHTML = `<button class="btn-return" data-id="${book.id}">Returned</button>`;
                    }
                    // --- End of new logic ---

                    row.innerHTML = `
                        <td>${book.user_id}</td>
                        <td>${book.name}</td>
                        <td>${book.email}</td>
                        <td>${book.transaction_number}</td>
                        <td>${book.book_title}</td>
                        <td>${new Date(book.issue_date).toLocaleDateString()}</td>
                        <td>${new Date(book.due_date).toLocaleDateString()}</td>
                        <td class="action-cell">${actionButtonHTML}</td>
                    `;
                });
            })
            .catch(error => {
                console.error("Failed to fetch not returned books:", error);
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Error loading data.</td></tr>';
            });
    }

    // --- Event Delegation for table buttons ---
    tableBody.addEventListener("click", (e) => {
        const target = e.target;
        const issuedId = target.dataset.id;
        
        // --- USING not_returned_api.php ---
        const apiFile = 'not_returned_api.php';

        // --- 'Returned' button (sends OTP) ---
        if (e.target.classList.contains("btn-return")) {
            if (confirm("This will send an OTP to the user's email to confirm the return. Proceed?")) {
                target.disabled = true;
                target.textContent = "Sending...";
                
                fetch(apiFile, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'sendReturnOTP', issuedId: issuedId })
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        fetchNotReturnedBooks(); // Re-fetch
                    } else {
                        target.disabled = false;
                        target.textContent = "Returned";
                    }
                });
            }
        }

        // --- 'Enter Code' button (opens modal) ---
        else if (target.classList.contains('btn-enter-code')) {
            const tableRow = target.closest('tr');
            const userEmail = tableRow.cells[2].textContent;
            const bookTitle = tableRow.cells[4].textContent;
            
            if(otpUserEmail) otpUserEmail.textContent = userEmail;
            if(otpBookTitle) otpBookTitle.textContent = bookTitle; 

            otpIssuedIdInput.value = issuedId;
            otpInputs.forEach(input => input.value = '');
            otpCodeCombined.value = '';
            otpModal.style.display = 'block';
            otpInputs[0].focus();
        }
    });

    // --- Event Listeners for OTP Modal ---
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
        
        const issuedId = otpIssuedIdInput.value;
        const otp = otpCodeCombined.value;
        const submitBtn = otpForm.querySelector('button[type="submit"]');
        
        // --- USING not_returned_api.php ---
        const apiFile = 'not_returned_api.php';

        if (otp.length !== 5) {
            alert("Please enter a 5-digit OTP.");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Verifying...";

        fetch(apiFile, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'verifyAndReturnBook', 
                issuedId: issuedId,
                otp: otp 
            })
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                otpModal.style.display = 'none';
                fetchNotReturnedBooks(); // Re-fetch
            }
            submitBtn.disabled = false;
            submitBtn.textContent = "Verify and Return Book";
        });
    });

    // Click listener for Resend Link
    resendOtpLink.addEventListener('click', (e) => {
        e.preventDefault();
        
        const issuedId = otpIssuedIdInput.value;
        if (!issuedId) {
            alert("An error occurred. Please close this modal and try again.");
            return;
        }
        
        // --- USING not_returned_api.php ---
        const apiFile = 'not_returned_api.php';

        e.target.textContent = "Sending...";
        e.target.style.pointerEvents = 'none';

        fetch(apiFile, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sendReturnOTP', issuedId: issuedId })
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

    // --- Logic for the 5 OTP boxes ---
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

    // --- Initial Load ---
    searchBox.addEventListener('input', fetchNotReturnedBooks);
    fetchNotReturnedBooks();
});