// Returned.js

document.addEventListener("DOMContentLoaded", () => {
    const tableBody = document.getElementById("returnedBooksTableBody");
    const searchBox = document.getElementById("searchBox");

    function fetchReturnedBooks() {
        const searchTerm = searchBox.value;
        const apiUrl = `returned_books_api.php?action=getReturnedBooks&search=${encodeURIComponent(searchTerm)}`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                tableBody.innerHTML = ""; // Clear existing table
                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No returned books found.</td></tr>';
                    return;
                }

                data.forEach(book => {
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
            })
            .catch(error => {
                console.error("Failed to fetch returned books:", error);
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Error loading data.</td></tr>';
            });
    }

    // Event Delegation for "Delete" button
    tableBody.addEventListener("click", (e) => {
        if (e.target.classList.contains("delete-btn")) {
            if (confirm("Are you sure you want to permanently delete this record? This action cannot be undone.")) {
                const issuedId = e.target.dataset.id;
                
                fetch('returned_books_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'deleteReturnedRecord', issuedId: issuedId })
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        fetchReturnedBooks(); // Refresh the table
                    }
                })
                .catch(error => console.error('Error deleting record:', error));
            }
        }
    });

    // Event listener for the search box
    searchBox.addEventListener('input', fetchReturnedBooks);

    // Initial load of data
    fetchReturnedBooks();
});