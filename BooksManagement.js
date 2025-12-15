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
    
    // --- NEW: Pagination Elements ---
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const pageInfo = document.getElementById('page-info');

    // --- Global Variables ---
    let currentPage = 1;
    const itemsPerPage = 20; // Number of books per page

    // --- Initial Data Fetch ---
    fetchBooks();
    populateCategories();

    // --- Main Function to Fetch and Display Books (Updated for Pagination) ---
    function fetchBooks(searchTerm = '', category = '', year = '', page = 1) {
        // Construct API URL with new 'page' and 'limit' parameters
        const apiUrl = `books_api.php?action=getAllBooks&search=${encodeURIComponent(searchTerm)}&category=${encodeURIComponent(category)}&year=${encodeURIComponent(year)}&page=${page}&limit=${itemsPerPage}`;
        
        fetch(apiUrl)
            .then(response => response.json())
            .then(response => {
                // The API now returns { data: [...], pagination: {...} }
                // We handle both cases (old API vs new API) just in case
                const books = response.data || response; 
                const pagination = response.pagination;

                booksTableBody.innerHTML = '';
                
                if (books.error) {
                    console.error(books.error);
                    return;
                }

                if (!books || books.length === 0) {
                    booksTableBody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px;">No books found.</td></tr>';
                    // Reset pagination if no results
                    if(pagination) updatePaginationControls(0, 0, 1);
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

                // Update the buttons using the data from the server
                if (pagination) {
                    updatePaginationControls(pagination.totalRecords, pagination.totalPages, pagination.currentPage);
                }
            })
            .catch(error => console.error('Error fetching books:', error));
    }

    // --- NEW: Pagination UI Logic ---
    function updatePaginationControls(totalRecords, totalPages, current) {
        currentPage = current; // Sync global state
        
        // Update text: "Page 1 of 5 (Total: 100)"
        if(pageInfo) {
            pageInfo.textContent = `Page ${current} of ${totalPages || 1} (Total: ${totalRecords})`;
        }

        if(prevBtn && nextBtn) {
            // Disable "Previous" if on page 1
            if (current <= 1) {
                prevBtn.disabled = true;
                prevBtn.style.background = '#ccc';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.background = '#2196f3';
                prevBtn.style.cursor = 'pointer';
            }

            // Disable "Next" if on last page
            if (current >= totalPages || totalPages === 0) {
                nextBtn.disabled = true;
                nextBtn.style.background = '#ccc';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.background = '#2196f3';
                nextBtn.style.cursor = 'pointer';
            }
        }
    }

    // --- NEW: Pagination Event Listeners ---
    if(prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                fetchBooks(searchBar.value, categoryFilter.value, yearFilter.value, currentPage - 1);
            }
        });
    }

    if(nextBtn) {
        nextBtn.addEventListener('click', () => {
            // We rely on the button state (disabled/enabled) to prevent over-clicking
            if (!nextBtn.disabled) {
                fetchBooks(searchBar.value, categoryFilter.value, yearFilter.value, currentPage + 1);
            }
        });
    }

    // --- MODIFIED: Filter logic ---
    function updateFilters() {
        currentPage = 1; // Always reset to Page 1 when searching/filtering
        fetchBooks(searchBar.value, categoryFilter.value, yearFilter.value, 1);
    }
    searchBar.addEventListener('input', updateFilters);
    categoryFilter.addEventListener('change', updateFilters);
    yearFilter.addEventListener('input', updateFilters);


    // ---------------------------------------------------------
    //  BELOW IS YOUR ORIGINAL CODE (UNCHANGED)
    // ---------------------------------------------------------

    // Function to populate categories
    function populateCategories() {
        return fetch('books_api.php?action=getAllCategories')
            .then(response => response.json())
            .then(categories => {
                categoryFilter.innerHTML = '<option value="">All Categories</option>';
                categorySelectInput.innerHTML = '<option value="">Select a Category</option>';

                if (categories.error) {
                    console.error(categories.error);
                    return;
                }
                categories.forEach(cat => {
                    const filterOption = document.createElement('option');
                    filterOption.value = cat.id;
                    filterOption.textContent = cat.name;
                    categoryFilter.appendChild(filterOption);

                    const formOption = document.createElement('option');
                    formOption.value = cat.id;
                    formOption.textContent = cat.name;
                    categorySelectInput.appendChild(formOption);
                });

                const separator = document.createElement('option');
                separator.disabled = true;
                separator.textContent = '──────────────────────────────────';
                categorySelectInput.appendChild(separator);

                const addNewOption = document.createElement('option');
                addNewOption.value = 'add_new_category';
                addNewOption.textContent = '＋ Add New Category...';
                categorySelectInput.appendChild(addNewOption);

                const removeOption = document.createElement('option');
                removeOption.value = 'remove_category';
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

    categorySelectInput.addEventListener('change', function() {
        if (this.value === 'add_new_category') {
            addCategoryModal.style.display = 'block';
            this.selectedIndex = 0;
        } else if (this.value === 'remove_category') {
            populateCategoriesForDeletion();
            removeCategoryModal.style.display = 'block';
            this.selectedIndex = 0;
        }
    });

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
                    populateCategories(); 
                }
            });
        }
    });

    closeRemoveCategoryModalBtn.addEventListener('click', () => {
        removeCategoryModal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target == bookModal) bookModal.style.display = 'none';
        if (event.target == addCategoryModal) addCategoryModal.style.display = 'none';
        if (event.target == removeCategoryModal) removeCategoryModal.style.display = 'none';
    });

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