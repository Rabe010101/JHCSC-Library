// Returned.js

document.addEventListener("DOMContentLoaded", () => {
    // --- Element Selectors ---
    const tableBody = document.getElementById("returnedBooksTableBody");
    const searchBox = document.getElementById("searchBox");

    // Pagination Selectors
    const prevBtn = document.getElementById("prev-btn");
    const nextBtn = document.getElementById("next-btn");
    const pageInfo = document.getElementById("page-info");

    // --- Global Variables ---
    let currentPage = 1;
    const itemsPerPage = 20;
    let searchTimeout = null; // For Debouncing

    // --- Function to fetch returned books ---
    function fetchReturnedBooks(page = 1) {
        const searchTerm = searchBox.value;
        const cacheBust = new Date().getTime(); // Prevent browser caching
        
        // Updated URL with pagination parameters
        const apiUrl = `returned_books_api.php?action=getReturnedBooks&search=${encodeURIComponent(searchTerm)}&page=${page}&limit=${itemsPerPage}&_=${cacheBust}`;

        fetch(apiUrl, { credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                // Handle new response structure { data: [...], pagination: {...} }
                const books = data.data || [];
                const pagination = data.pagination;

                tableBody.innerHTML = ""; 
                
                if (books.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px;">No returned books found.</td></tr>';
                    if (pagination) updatePaginationControls(0, 0, 1);
                    return;
                }

                books.forEach(book => {
                    const row = tableBody.insertRow();
                    row.innerHTML = `
                        <td>${book.user_id}</td>
                        <td>${book.name}</td>
                        <td>${book.email}</td>
                        <td>${book.transaction_number}</td>
                        <td>${book.book_title}</td>
                        <td>${new Date(book.issue_date).toLocaleDateString()}</td>
                        <td>${new Date(book.return_date).toLocaleDateString()}</td>
                        <td><button class="delete-btn" data-id="${book.id}">Delete</button></td>
                    `;
                });

                // Update Pagination Controls
                if (pagination) {
                    updatePaginationControls(pagination.totalRecords, pagination.totalPages, pagination.currentPage);
                }
            })
            .catch(error => {
                console.error("Failed to fetch returned books:", error);
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Error loading data.</td></tr>';
            });
    }

    // --- Pagination UI Logic ---
    function updatePaginationControls(totalRecords, totalPages, current) {
        currentPage = current;
        if (pageInfo) pageInfo.textContent = `Page ${current} of ${totalPages || 1} (Total: ${totalRecords})`;

        if (prevBtn && nextBtn) {
            // Define styles for enabled/disabled states (using your existing CSS classes)
            // Note: We manually toggle the style because we are reusing the .delete-btn class 
            // but we need it to look gray when disabled.
            const btnStyle = "background: #ff4d4d; color: #fff; cursor: pointer;"; 
            const disabledStyle = "background: #ccc; cursor: not-allowed;";

            // Previous Button
            if (current <= 1) {
                prevBtn.disabled = true;
                prevBtn.style.cssText = disabledStyle;
            } else {
                prevBtn.disabled = false;
                prevBtn.style.cssText = btnStyle;
            }

            // Next Button
            if (current >= totalPages || totalPages === 0) {
                nextBtn.disabled = true;
                nextBtn.style.cssText = disabledStyle;
            } else {
                nextBtn.disabled = false;
                nextBtn.style.cssText = btnStyle;
            }
        }
    }

    // --- Event Listeners ---

    // 1. Debounced Search (Smoother typing experience)
    searchBox.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1; // Reset to page 1 on new search
            fetchReturnedBooks(1);
        }, 300);
    });

    // 2. Pagination Buttons
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) fetchReturnedBooks(currentPage - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (!nextBtn.disabled) fetchReturnedBooks(currentPage + 1);
        });
    }

    // 3. Event Delegation for "Delete" button
    tableBody.addEventListener("click", (e) => {
        if (e.target.classList.contains("delete-btn")) {
            if (confirm("Are you sure you want to permanently delete this record? This action cannot be undone.")) {
                const issuedId = e.target.dataset.id;
                
                fetch('returned_books_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'deleteReturnedRecord', issuedId: issuedId }),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        fetchReturnedBooks(currentPage); // Refresh current page to keep context
                    }
                })
                .catch(error => console.error('Error deleting record:', error));
            }
        }
    });

    // Initial load of data
    fetchReturnedBooks(1);
});