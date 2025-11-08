document.addEventListener('DOMContentLoaded', function () {
    // --- Element Selectors ---
    const booksTableBody = document.getElementById('booksTableBody');
    const bookModal = document.getElementById('bookModal');
    const bookForm = document.getElementById('bookForm');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    const bookIdInput = document.getElementById('bookId');
    const searchBar = document.getElementById('searchBar');
    const categoryFilter = document.getElementById('categoryFilter');
    const yearFilter = document.getElementById('yearFilter');
    const categorySelectInput = document.getElementById('category');
    const addCategoryModal = document.getElementById('addCategoryModal');
    const addCategoryForm = document.getElementById('addCategoryForm');
    const closeCategoryModalBtn = document.getElementById('closeCategoryModal');
    // --- REMOVED: const addCategoryBtn is no longer needed ---

    // --- Initial Data Fetch ---
    fetchBooks();
    populateCategories();

    // --- Main Function to Fetch and Display Books ---
    function fetchBooks(searchTerm = '', category = '', year = '') {
        const apiUrl = `books_api.php?action=getAllBooks&search=${encodeURIComponent(searchTerm)}&category=${encodeURIComponent(category)}&year=${encodeURIComponent(year)}`;
        fetch(apiUrl)
            .then(response => response.json())
            .then(books => {
                booksTableBody.innerHTML = '';
                if (books.error) {
                    console.error(books.error);
                    return;
                }
                books.forEach(book => {
                    const row = booksTableBody.insertRow();
                    row.dataset.categoryId = book.category_id;
                    row.innerHTML = `
                        <td><img src="${book.cover}" class="book-cover" onerror="this.src='https://placehold.co/60x80?text=No+Image'"></td>
                        <td>${book.title}</td>
                        <td>${book.author}</td>
                        <td>${book.publisher}</td>
                        <td>${book.category}</td>
                        <td>${book.year}</td>
                        <td>${book.copies}</td>
                        <td>
                            <button class="btn-edit" data-id="${book.id}">Edit</button>
                            <button class="btn-delete" data-id="${book.id}">Delete</button>
                        </td>
                    `;
                });
            })
            .catch(error => console.error('Error fetching books:', error));
    }

    // --- MODIFIED: This function now adds the "Add New Category" option at the bottom ---
    function populateCategories() {
        return fetch('books_api.php?action=getAllCategories')
            .then(response => response.json())
            .then(categories => {
                // Clear dropdowns
                categoryFilter.innerHTML = '<option value="">All Categories</option>';
                categorySelectInput.innerHTML = '<option value="">Select a Category</option>';

                if (categories.error) {
                    console.error(categories.error);
                    return;
                }
                categories.forEach(cat => {
                    // Populate filter dropdown
                    const filterOption = document.createElement('option');
                    filterOption.value = cat.id;
                    filterOption.textContent = cat.name;
                    categoryFilter.appendChild(filterOption);

                    // Populate form dropdown
                    const formOption = document.createElement('option');
                    formOption.value = cat.id;
                    formOption.textContent = cat.name;
                    categorySelectInput.appendChild(formOption);
                });

                // --- ADDED: The separator and the special "Add New" option ---
                const separator = document.createElement('option');
                separator.disabled = true;
                separator.textContent = '──────────────────────────────────';
                categorySelectInput.appendChild(separator);

                const addNewOption = document.createElement('option');
                addNewOption.value = 'add_new_category'; // Special value to identify this action
                addNewOption.textContent = '＋ Add New Category...';
                categorySelectInput.appendChild(addNewOption);

                // --- ADDED: The "Remove a Category" option ---
                const removeOption = document.createElement('option');
                removeOption.value = 'remove_category'; // A special value for this action
                removeOption.textContent = '－ Remove a Category...';
                categorySelectInput.appendChild(removeOption);
            })
            .catch(error => console.error('Error fetching categories:', error));
    }

// --- Logic for Remove Category Modal ---
const removeCategoryModal = document.getElementById('removeCategoryModal');
const closeRemoveCategoryModalBtn = document.getElementById('closeRemoveCategoryModal');
const removeCategoryForm = document.getElementById('removeCategoryForm');
const categoryToDeleteSelect = document.getElementById('categoryToDelete');

// Function to populate the dropdown inside the "Remove Category" modal
function populateCategoriesForDeletion() {
    fetch('books_api.php?action=getAllCategories')
        .then(response => response.json())
        .then(categories => {
            categoryToDeleteSelect.innerHTML = '<option value="">Select a category...</option>';
            categories.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.id;
                option.textContent = cat.name;
                categoryToDeleteSelect.appendChild(option);
            });
        });
}

// Modify the main dropdown's change listener to handle the new option
categorySelectInput.addEventListener('change', function() {
    if (this.value === 'add_new_category') {
        addCategoryModal.style.display = 'block';
        this.selectedIndex = 0;
    } else if (this.value === 'remove_category') {
        // When "Remove" is clicked, populate its modal and show it
        populateCategoriesForDeletion();
        removeCategoryModal.style.display = 'block';
        this.selectedIndex = 0;
    }
});

// Handle form submission for deleting a category
removeCategoryForm.addEventListener('submit', function(event) {
    event.preventDefault();
    const categoryId = categoryToDeleteSelect.value;
    if (!categoryId) {
        alert('Please select a category to delete.');
        return;
    }

    if (confirm('Are you sure you want to permanently delete this category? This cannot be undone.')) {
        fetch('books_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'deleteCategory', categoryId: categoryId })
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                removeCategoryModal.style.display = 'none';
                populateCategories(); // Refresh the main dropdowns
            }
        });
    }
});

// Close the remove modal
closeRemoveCategoryModalBtn.addEventListener('click', () => {
    removeCategoryModal.style.display = 'none';
});

// Also add its closing logic to the window click listener
window.addEventListener('click', (event) => {
    if (event.target == bookModal) bookModal.style.display = 'none';
    if (event.target == addCategoryModal) addCategoryModal.style.display = 'none';
    if (event.target == removeCategoryModal) removeCategoryModal.style.display = 'none'; // Add this line
});

// --- Filter logic ---
    function updateFilters() {
        fetchBooks(searchBar.value, categoryFilter.value, yearFilter.value);
    }
    searchBar.addEventListener('input', updateFilters);
    categoryFilter.addEventListener('change', updateFilters);
    yearFilter.addEventListener('input', updateFilters);

    // --- Form submission logic ---
    bookForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(bookForm);
        const bookData = Object.fromEntries(formData.entries());
        const action = bookData.bookId ? 'updateBook' : 'addBook';
        bookData.action = action;
        
        fetch('books_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(bookData)
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                bookModal.style.display = 'none';
                updateFilters();
            }
        })
        .catch(error => console.error('Form submission error:', error));
    });

    // --- Event Delegation for Edit/Delete Buttons ---
    booksTableBody.addEventListener('click', function (event) {
        const target = event.target;
        const bookId = target.dataset.id;
        if (target.classList.contains('btn-edit')) {
            const row = target.closest('tr');
            modalTitle.textContent = 'Edit Book';
            submitBtn.textContent = 'Save Changes';
            bookIdInput.value = bookId;
            document.getElementById('coverUrl').value = row.cells[0].querySelector('img').src;
            document.getElementById('title').value = row.cells[1].textContent;
            document.getElementById('author').value = row.cells[2].textContent;
            document.getElementById('publisher').value = row.cells[3].textContent;
            categorySelectInput.value = row.dataset.categoryId;
            document.getElementById('year').value = row.cells[5].textContent;
            document.getElementById('copies').value = row.cells[6].textContent;
            bookModal.style.display = 'block';
        }
        if (target.classList.contains('btn-delete')) {
            if (confirm('Are you sure you want to delete this book?')) {
                fetch('books_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'deleteBook', bookId: bookId })
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) {
                        updateFilters();
                    }
                });
            }
        }
    });

    // --- Main Modal control logic ---
    const openModalBtn = document.getElementById('openModal');
    const closeModalBtn = document.getElementById('closeModal');
    openModalBtn.addEventListener('click', () => {
        bookForm.reset();
        bookIdInput.value = '';
        modalTitle.textContent = 'Add New Book';
        submitBtn.textContent = 'Add Book';
        bookModal.style.display = 'block';
    });
    closeModalBtn.addEventListener('click', () => bookModal.style.display = 'none');
    window.addEventListener('click', (event) => {
        if (event.target == bookModal) bookModal.style.display = 'none';
        if (event.target == addCategoryModal) addCategoryModal.style.display = 'none';
    });

    // --- ADDED: New Event Listener for the dropdown itself ---
    categorySelectInput.addEventListener('change', function() {
        if (this.value === 'add_new_category') {
            addCategoryModal.style.display = 'block';
            this.selectedIndex = 0; // Reset dropdown to "Select a Category"
        }
    });

    // --- REMOVED: The old click listener for the separate button is gone ---

    // --- "Add Category" Modal logic ---
    closeCategoryModalBtn.addEventListener('click', () => {
        addCategoryModal.style.display = 'none';
    });

    addCategoryForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const newCategoryName = document.getElementById('newCategoryName').value;
        fetch('books_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'addCategory', name: newCategoryName })
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                addCategoryModal.style.display = 'none';
                addCategoryForm.reset();
                populateCategories().then(() => {
                    categorySelectInput.value = result.newCategory.id;
                });
            }
        });
    });
});