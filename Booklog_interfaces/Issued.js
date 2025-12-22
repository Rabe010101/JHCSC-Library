document.addEventListener("DOMContentLoaded", () => {
    // --- Element Selectors ---
    const tableBody = document.getElementById("issuedBooksTableBody");
    const searchBox = document.getElementById("searchBox");

    // Pagination Selectors
    const prevBtn = document.getElementById("prev-btn");
    const nextBtn = document.getElementById("next-btn");
    const pageInfo = document.getElementById("page-info");

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

    // --- Global Pagination Variables ---
    let currentPage = 1;
    const itemsPerPage = 20;
    
    // NEW: Variable for the search timer
    let searchTimeout = null;

    // --- Function to fetch issued books ---
    function fetchIssuedBooks(page = 1) {
        const searchTerm = searchBox.value;
        const cacheBust = new Date().getTime();
        const apiUrl = `issued_books_api.php?action=getIssuedBooks&search=${encodeURIComponent(searchTerm)}&page=${page}&limit=${itemsPerPage}&_=${cacheBust}`;

        fetch(apiUrl, { credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                // Handle response structure
                const books = data.data || [];
                const pagination = data.pagination;

                tableBody.innerHTML = ""; 
                
                if (data.error) {
                    console.error("API Error:", data.error);
                    tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Error loading data: ${data.error}</td></tr>`;
                    return;
                }
                
                if (!books || books.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px;">No issued books found.</td></tr>';
                    if (pagination) updatePaginationControls(0, 0, 1);
                    return;
                }

                books.forEach(book => {
                    const row = tableBody.insertRow();
                    let actionButtonHTML = '';

                    const otpExpires = book.otp_expires ? new Date(book.otp_expires.replace(' ', 'T') + 'Z') : null;
                    const now = new Date();
                    
                    if (otpExpires && otpExpires > now) {
                        actionButtonHTML = `<button class="btn-enter-code" data-id="${book.id}">Enter Code</button>`;
                    } else {
                        actionButtonHTML = `<button class="btn-action" data-id="${book.id}">Returned</button>`;
                    }

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

                // Update Pagination Controls
                if (pagination) {
                    updatePaginationControls(pagination.totalRecords, pagination.totalPages, pagination.currentPage);
                }
            })
            .catch(error => {
                console.error("Failed to fetch issued books:", error);
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Error loading data.</td></tr>';
            });
    }

    // --- Pagination UI Logic ---
    function updatePaginationControls(totalRecords, totalPages, current) {
        currentPage = current;
        if (pageInfo) pageInfo.textContent = `Page ${current} of ${totalPages || 1} (Total: ${totalRecords})`;

        if (prevBtn && nextBtn) {
            if (current <= 1) {
                prevBtn.disabled = true;
                prevBtn.style.background = '#ccc';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.background = '#28a745'; 
                prevBtn.style.cursor = 'pointer';
            }

            if (current >= totalPages || totalPages === 0) {
                nextBtn.disabled = true;
                nextBtn.style.background = '#ccc';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.background = '#28a745';
                nextBtn.style.cursor = 'pointer';
            }
        }
    }

    // --- EVENT LISTENERS ---

    // 1. IMPROVED SEARCH (Debounced)
    if (searchBox) {
        searchBox.addEventListener('input', () => {
            // Clear the previous timer (cancels the previous search if you're still typing)
            clearTimeout(searchTimeout);
            
            // Set a new timer
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                fetchIssuedBooks(1);
            }, 300); // Wait 300ms after the last keystroke before searching
        });
    }

    // 2. Pagination Buttons
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) fetchIssuedBooks(currentPage - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (!nextBtn.disabled) fetchIssuedBooks(currentPage + 1);
        });
    }

    // ... (Keep Event Delegation and Modal Logic EXACTLY the same as before) ...
    
    // --- Event Delegation for table buttons ---
    tableBody.addEventListener("click", (e) => {
        const target = e.target;
        const issuedId = target.dataset.id;

        if (e.target.classList.contains("btn-action")) {
            if (confirm("This will send an OTP to the user's email to confirm the return. Proceed?")) {
                target.disabled = true;
                target.textContent = "Sending...";
                
                fetch('issued_books_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'sendReturnOTP', issuedId: issuedId }),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        fetchIssuedBooks(currentPage); // Keep current page
                    } else {
                        target.disabled = false;
                        target.textContent = "Returned";
                    }
                });
            }
        }
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

    closeOtpModalBtn.addEventListener('click', () => {
        otpModal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target == otpModal) {
            otpModal.style.display = 'none';
        }
    });

    otpForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const issuedId = otpIssuedIdInput.value;
        const otp = otpCodeCombined.value;
        const submitBtn = otpForm.querySelector('button[type="submit"]');

        if (otp.length !== 5) {
            alert("Please enter a 5-digit OTP.");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Verifying...";

        fetch('issued_books_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'verifyAndReturnBook', 
                issuedId: issuedId,
                otp: otp 
            }),
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                otpModal.style.display = 'none';
                fetchIssuedBooks(currentPage);
            }
            submitBtn.disabled = false;
            submitBtn.textContent = "Verify and Return Book";
        });
    });

    resendOtpLink.addEventListener('click', (e) => {
        e.preventDefault();
        const issuedId = otpIssuedIdInput.value;
        if (!issuedId) {
            alert("An error occurred. Please close this modal and try again.");
            return;
        }
        e.target.textContent = "Sending...";
        e.target.style.pointerEvents = 'none';

        fetch('issued_books_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sendReturnOTP', issuedId: issuedId }),
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

    if (otpContainer) {
        function combineInputs() {
            let code = '';
            otpInputs.forEach(input => { code += input.value; });
            otpCodeCombined.value = code;
        }
        otpContainer.addEventListener('input', (e) => {
            const target = e.target;
            const index = parseInt(target.dataset.index, 10);
            if (target.value.length > 1) target.value = target.value.slice(0, 1);
            if (!/^\d*$/.test(target.value)) { target.value = ''; return; }
            if (target.value !== '' && index < otpInputs.length - 1) otpInputs[index + 1].focus();
            combineInputs();
        });
        otpContainer.addEventListener('keydown', (e) => {
            const target = e.target;
            const index = parseInt(target.dataset.index, 10);
            if (e.key === 'Backspace' && target.value === '' && index > 0) otpInputs[index - 1].focus();
            combineInputs();
        });
        otpContainer.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').slice(0, 5);
            pasteData.split('').forEach((char, i) => {
                if (otpInputs[i] && /^\d$/.test(char)) otpInputs[i].value = char;
            });
            const lastFilledIndex = Math.min(pasteData.length, otpInputs.length) - 1;
            if (lastFilledIndex >= 0) otpInputs[lastFilledIndex].focus();
            combineInputs();
        });
    }

    fetchIssuedBooks(1);
});