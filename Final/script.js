// --- Global Variables & Element Selectors ---
const navLinks = document.querySelectorAll('.nav-link');
const pageContents = document.querySelectorAll('.page-content');
const searchResults = document.getElementById('search-results');
const searchInput = document.getElementById('search-input');
const searchButton = document.getElementById('search-button');
const messageModal = document.getElementById('message-modal');
const modalTitle = document.getElementById('modal-title');
const modalMessage = document.getElementById('modal-message');
const myBooksList = document.getElementById('my-books-list');
const reservationsList = document.getElementById('reservations-list');
const userLogoutButton = document.getElementById('logout-button');
// --- NEW ELEMENT SELECTORS FOR RESERVE PAGE ---
const categoryFiltersContainer = document.getElementById('category-filters');
const reserveBookGrid = document.getElementById('reserve-book-grid');


// --- Data Arrays ---
let allBooks = [];
let myFavoriteBooks = [];
let previousPageId = 'home';

// --- Page Navigation & UI Functions ---
function navigateTo(pageId, bookId = null) {
    if (pageId !== 'reserve-book') {
        previousPageId = pageId;
    }
    window.location.hash = pageId;
    pageContents.forEach(page => page.classList.add('hidden'));
    const targetPage = document.getElementById(pageId + '-page');
    if (targetPage) {
        targetPage.classList.remove('hidden');
        if (pageId === 'my-books') renderMyBooks();
        else if (pageId === 'search-library') renderBooks(allBooks, searchResults); // Pass container
        // --- NEW: Handle rendering for the new reserve page ---
        else if (pageId === 'reserve') renderReservePage();
        else if (pageId === 'reserve-book' && bookId !== null) renderReserveBook(bookId);
        else if (pageId === 'reservations') renderReservations();
        else if (pageId === 'account') fetchAccountData();
    }
}

function cancelReservationFlow() {
    navigateTo(previousPageId);
}

function showModal(title, message) {
    if (modalTitle) modalTitle.textContent = title;
    if (modalMessage) modalMessage.innerHTML = message;
    if (messageModal) {
        messageModal.classList.remove('hidden');
        messageModal.classList.add('flex');
    }
}

function hideModal() {
    if (messageModal) {
        messageModal.classList.add('hidden');
        messageModal.classList.remove('flex');
    }
}

// --- Rendering Functions ---
// Modified renderBooks to accept a target container to make it reusable
function renderBooks(booksToRender, container) {
    if (!container) return;
    container.innerHTML = '';
    if (booksToRender.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-500 col-span-full">No books found.</p>';
        return;
    }
    booksToRender.forEach(book => {
        const isFavorited = myFavoriteBooks.some(fav => fav.id == book.id);
        const favBtnClass = isFavorited ? 'bg-gray-400 cursor-not-allowed' : 'bg-yellow-500 hover:bg-yellow-600';
        const favBtnText = isFavorited ? 'In Favorites' : 'Add to Favorites';
        container.innerHTML += `
            <div class="bg-white p-4 rounded-lg shadow-md text-center cursor-pointer group" onclick="navigateTo('reserve-book', ${book.id})">
                <img src="${book.cover}" alt="${book.title}" class="rounded-md mx-auto mb-2 book-cover group-hover:scale-105" onerror="this.src='https://placehold.co/120x150?text=No+Image'">
                <h3 class="font-bold text-sm">${book.title}</h3>
                <p class="text-xs text-gray-600">Author: ${book.author}</p>
                <div class="mt-2">
                    <button class="text-white text-xs px-3 py-1 rounded-md w-full ${favBtnClass}" onclick="event.stopPropagation(); addToFavorites(${book.id})" ${isFavorited ? 'disabled' : ''}>
                        ${favBtnText}
                    </button>
                </div>
            </div>`;
    });
}

// --- NEW: Function to render the category filters ---
function renderCategoryFilters(categories) {
    if (!categoryFiltersContainer) return;
    categoryFiltersContainer.innerHTML = '<button class="category-filter-btn active" data-category-id="all">All</button>';
    categories.forEach(category => {
        categoryFiltersContainer.innerHTML += `<button class="category-filter-btn" data-category-id="${category.id}">${category.name}</button>`;
    });
}

// --- NEW: Function to render the entire "Reserve a Book" page ---
function renderReservePage() {
    fetch('api.php?action=getCategories')
        .then(res => res.json())
        .then(categories => {
            renderCategoryFilters(categories);
            renderBooks(allBooks, reserveBookGrid); // Render all books initially
        });
}

function renderMyBooks() {
    if (!myBooksList) return;
    myBooksList.innerHTML = '';
    if (myFavoriteBooks.length === 0) {
        myBooksList.innerHTML = '<p class="text-center text-gray-500">You have no favorited books.</p>';
        return;
    }
    myFavoriteBooks.forEach(book => {
        const isAvailable = book.copies > 0;
        const statusColor = isAvailable ? 'text-green-600' : 'text-red-600';
        const reserveButtonClass = isAvailable ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-400 cursor-not-allowed';

        myBooksList.innerHTML += `
            <div class="bg-white p-4 rounded-lg shadow-md flex items-start space-x-4">
                <img src="${book.cover}" alt="${book.title}" class="rounded-md book-cover" onerror="this.src='https://placehold.co/120x150?text=No+Image'">
                <div class="flex-grow">
                    <h3 class="font-bold">${book.title}</h3>
                    <p class="text-sm text-gray-600">Author: ${book.author}</p>
                    <p class="text-sm font-medium mt-1">Status: <span class="${statusColor}">${isAvailable ? 'Available' : 'Unavailable'}</span></p>
                    <div class="mt-2 flex space-x-2">
                        <button class="text-white px-3 py-1 text-sm rounded-md ${reserveButtonClass}" 
                                onclick="navigateTo('reserve-book', ${book.id})" ${!isAvailable ? 'disabled' : ''}>
                            Reserve
                        </button>
                        <button class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 text-sm rounded-md" onclick="removeFromFavorites(${book.id})">Remove</button>
                    </div>
                </div>
            </div>`;
    });
}

function renderReserveBook(bookId) {
    const book = allBooks.find(b => b.id == bookId);
    if (!book) {
        showModal('Error', 'Book details not found.');
        navigateTo('search-library');
        return;
    }
    const contentEl = document.getElementById('reserve-book-content');
    if (contentEl) {
        const statusColor = book.status === 'Available' ? 'text-green-600' : 'text-red-600';
        contentEl.innerHTML = `
            <img src="${book.cover}" alt="${book.title}" class="rounded-md book-cover" onerror="this.src='https://placehold.co/120x150?text=No+Image'">
            <div>
                <h2 class="text-2xl font-bold">${book.title}</h2>
                <p class="text-gray-600">Author: ${book.author}</p>
                <p class="text-sm">Publisher: ${book.publisher || 'N/A'}</p>
                <p class="text-sm">Category: ${book.category || 'N/A'}</p>
                <p class="text-sm">Year: ${book.year || 'N/A'}</p>
                <p class="font-medium mt-1">Status: <span class="${statusColor}">${book.status}</span></p>
                <p class="font-medium">Copies: <span>${book.copies}</span></p>
                <div class="mt-4">
                    <p>Select return date:</p>
                    <input id="return-date" type="date" class="border rounded p-2 mt-1">
                </div>
            </div>`;
    }
    const confirmBtn = document.getElementById('confirm-reservation-button');
    if (confirmBtn) {
        confirmBtn.disabled = book.status !== 'Available';
        confirmBtn.onclick = () => confirmReservation(book.id);
    }
}

function renderReservations() {
    if (!reservationsList) return;
    reservationsList.innerHTML = '<p class="text-center text-gray-500">Loading reservations...</p>';

    fetch('api.php?action=getReservations')
        .then(res => res.json())
        .then(data => {
            reservationsList.innerHTML = '';
            if (data.length === 0) {
                reservationsList.innerHTML = '<p class="text-center text-gray-500">You have no reservations.</p>';
                return;
            }
            data.forEach(res => {
                const statusStyles = {
                    'Pending Pickup': 'bg-yellow-200 text-yellow-800',
                    'Claimed': 'bg-green-200 text-green-800',
                    'Cancelled': 'bg-red-200 text-red-800'
                };
                const statusClass = statusStyles[res.status] || 'bg-gray-200 text-gray-800';
                const canCancel = res.status === 'Pending Pickup';
                const canDelete = res.status === 'Cancelled';

                let actionButtonHTML = '';

                if (canCancel) {
                    actionButtonHTML = `<button class="mt-2 bg-red-500 text-white px-3 py-1 text-sm rounded-md hover:bg-red-600" onclick="cancelReservation(${res.id})">Cancel Reservation</button>`;
                } else if (canDelete) {
                    const originalBook = allBooks.find(b => b.id == res.book_id);
                    const canReserveAgain = originalBook && originalBook.copies > 0;

                    actionButtonHTML = `
                        <div class="flex items-center space-x-2">
                            <button class="bg-red-500 text-white px-3 py-1 text-sm rounded-md hover:bg-red-600" 
                                    onclick="deleteReservation(${res.id})">
                                Delete
                            </button>
                            <button class="text-white px-3 py-1 text-sm rounded-md ${canReserveAgain ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-400 cursor-not-allowed'}" 
                                    onclick="navigateTo('reserve-book', ${res.book_id})" 
                                    ${!canReserveAgain ? 'disabled' : ''}>
                                Reserve Again
                            </button>
                        </div>
                    `;
                }

                reservationsList.innerHTML += `
                    <div class="bg-white p-4 rounded-lg shadow-md flex items-start space-x-4">
                        <img src="${res.cover}" alt="${res.title}" class="rounded-md book-cover" onerror="this.src='https://placehold.co/120x150?text=No+Image'">
                        <div class="flex-grow flex flex-col justify-between self-stretch">
                            <div>
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-bold">${res.title}</h3>
                                        <p class="text-sm text-gray-600">Author: ${res.author}</p>
                                    </div>
                                    <span class="text-xs font-semibold px-2 py-1 rounded-full ${statusClass} flex-shrink-0">${res.status}</span>
                                </div>
                                <p class="text-sm mt-1">Transaction #: <span class="font-mono">${res.transaction_number}</span></p>
                                <p class="text-sm">Reserved on: ${new Date(res.reservation_date).toLocaleDateString()}</p>
                                <p class="text-sm">Due Date: ${new Date(res.due_date).toLocaleDateString()}</p>
                            </div>
                            <div class="self-end">
                                ${actionButtonHTML}
                            </div>
                        </div>
                    </div>
                `;
            });
        })
        .catch(error => {
            console.error('Error fetching reservations:', error);
            reservationsList.innerHTML = '<p class="text-center text-red-500">Could not load reservations.</p>';
        });
}

function renderBorrowedBooksOnHome(borrowedBooks) {
    const borrowedListHome = document.getElementById('home-borrowed-books-list');
    if (!borrowedListHome) return;

    borrowedListHome.innerHTML = '';
    if (borrowedBooks.length === 0) {
        borrowedListHome.innerHTML = '<p class="text-center text-gray-500">You have no borrowed books.</p>';
        return;
    }

    borrowedBooks.forEach(book => {
        let displayStatus = book.status;
        if (displayStatus === 'Issued') {
            displayStatus = 'Borrowed';
        }

        // --- NEW: Overdue Status Check ---
        const today = new Date();
        const dueDate = new Date(book.due_date);
        today.setHours(0, 0, 0, 0); // Normalize today's date to midnight for accurate comparison

        // If the book is still borrowed and the due date is in the past, mark it as Overdue
        if (displayStatus === 'Borrowed' && dueDate < today) {
            displayStatus = 'Overdue';
        }
        // --- END OF NEW LOGIC ---

        const statusStyles = {
            'Borrowed': 'bg-blue-200 text-blue-800',
            'Overdue': 'bg-red-200 text-red-800', // Style for the new status
            'Returned': 'bg-green-200 text-green-800'
        };
        const statusClass = statusStyles[displayStatus] || 'bg-gray-200 text-gray-800';

        borrowedListHome.innerHTML += `
            <div class="bg-white p-3 rounded-lg shadow-md flex items-start space-x-3">
                <img src="${book.cover}" alt="${book.title}" class="rounded book-cover-sm" onerror="this.src='https://placehold.co/80x110?text=No+Image'">
                <div class="flex-grow flex flex-col">
                    <div class="flex justify-between items-center">
                        <h3 class="font-semibold text-sm">${book.title}</h3>
                        <span class="text-xs font-semibold px-2 py-1 rounded-full ${statusClass}">${displayStatus}</span>
                    </div>
                    <p class="text-xs text-gray-600">Author: ${book.author}</p>
                    <p class="text-xs mt-1">Borrowed on: ${new Date(book.borrow_date).toLocaleDateString()}</p>
                    <p class="text-xs">Due Date: ${new Date(book.due_date).toLocaleDateString()}</p>
                </div>
            </div>
        `;
    });
}

// --- API Communication ---
function addToFavorites(bookId) {
    const book = allBooks.find(b => b.id == bookId);
    if (book && !myFavoriteBooks.some(fav => fav.id == bookId)) {
        fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'addToFavorites', bookId: bookId }),
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    myFavoriteBooks.push(book);
                    showModal('Added to Favorites', `"${book.title}" has been added to your favorites.`);
                    renderBooks(allBooks, searchResults); // Also update the search results page
                    renderBooks(allBooks, reserveBookGrid); // And the new reserve page
                }
            });
    }
}

function removeFromFavorites(bookId) {
    const book = myFavoriteBooks.find(b => b.id == bookId);
    fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'removeFromFavorites', bookId: bookId }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (book) {
                    showModal('Removed from Favorites', `"${book.title}" has been removed from your favorites.`);
                }
                myFavoriteBooks = myFavoriteBooks.filter(fav => fav.id != bookId);
                renderMyBooks();
            }
        });
}

function confirmReservation(bookId) {
    const returnDateInput = document.getElementById('return-date');
    const dueDateValue = returnDateInput ? returnDateInput.value : null;
    if (!dueDateValue) {
        showModal('Input Required', 'Please select a return date.');
        return;
    }
    const selectedDate = new Date(dueDateValue);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (selectedDate < today) {
        showModal('Invalid Date', 'The return date cannot be in the past.');
        return;
    }
    fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'createReservation', bookId: bookId, dueDate: dueDateValue })
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                const book = allBooks.find(b => b.id == bookId);
                if (book) book.copies--;
                showModal('Success!', `<p>Reservation confirmed!</p><p class="mt-2 font-mono">Transaction #: ${result.transactionNumber}</p>`);
                navigateTo('reservations'); // Go to reservations page to see the new reservation
            } else {
                showModal('Reservation Failed', result.message || 'An unknown error occurred.');
            }
        });
}

function cancelReservation(reservationId) {
    if (confirm("Are you sure you want to cancel this reservation?")) {
        fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cancelReservation', reservationId: reservationId })
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    showModal('Success', 'Your reservation has been cancelled.');
                    renderReservations();
                } else {
                    showModal('Error', result.message || 'Could not cancel the reservation.');
                }
            });
    }
}

function deleteReservation(reservationId) {
    if (confirm("Are you sure you want to permanently delete this reservation record?")) {
        fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'deleteReservation', reservationId: reservationId })
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    showModal('Success', result.message);
                    renderReservations();
                } else {
                    showModal('Error', result.message || 'Could not delete the reservation.');
                }
            });
    }
}

// --- Initial Setup & Data Fetching ---
function fetchInitialData() {
    return Promise.all([
            fetch('api.php?action=getAllBooks').then(res => res.json()),
            fetch('api.php?action=getFavorites').then(res => res.json()),
            fetch('api.php?action=getBorrowedBooks').then(res => res.json())
        ])
        .then(([booksData, favoritesData, borrowedData]) => {
            allBooks = booksData;
            myFavoriteBooks = favoritesData;
            renderBorrowedBooksOnHome(borrowedData);
        })
        .catch(error => console.error('Initial data fetch error:', error));
}

document.addEventListener('DOMContentLoaded', () => {
    fetchInitialData().then(() => {
        function handlePageNavigation() {
            const pageId = window.location.hash.substring(1) || 'home';
            navigateTo(pageId);
            navLinks.forEach(link => {
                link.classList.toggle('active', link.getAttribute('data-page') === pageId);
            });
        }
        window.addEventListener('hashchange', handlePageNavigation);
        handlePageNavigation();
    });

    // --- NEW: Event listener for category filters ---
    if (categoryFiltersContainer) {
        categoryFiltersContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('category-filter-btn')) {
                document.querySelectorAll('.category-filter-btn').forEach(btn => btn.classList.remove('active'));
                e.target.classList.add('active');

                const categoryId = e.target.dataset.categoryId;
                if (categoryId === 'all') {
                    renderBooks(allBooks, reserveBookGrid);
                } else {
                    const filteredBooks = allBooks.filter(book => book.category_id == categoryId);
                    renderBooks(filteredBooks, reserveBookGrid);
                }
            }
        });
    }
    
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {});
    });

    if (searchButton) {
        searchButton.addEventListener('click', () => {
            const query = searchInput.value.toLowerCase();
            const filteredBooks = allBooks.filter(b =>
                b.title.toLowerCase().includes(query) ||
                b.author.toLowerCase().includes(query)
            );
            navigateTo('search-library');
            renderBooks(filteredBooks, searchResults); // Pass container
        });
    }

    if (userLogoutButton) {
        userLogoutButton.addEventListener('click', () => {
            if (confirm("Are you sure you want to log out?")) {
                window.location.href = "../logout.php";
            }
        });
    }
});