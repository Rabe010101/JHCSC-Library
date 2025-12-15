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
const categoryFiltersContainer = document.getElementById('category-filters');
const reserveBookGrid = document.getElementById('reserve-book-grid');

// --- Data Arrays ---
let allBooks = [];
let myFavoriteBooks = [];
let previousPageId = 'home';
let currentReserveCategory = 'all';
let currentNavigationList = [];

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
        
        // Render all books when visiting the search page
        else if (pageId === 'search-library') renderBooks(allBooks, searchResults);
        
        else if (pageId === 'reserve') renderReservePage();
        else if (pageId === 'reserve-book' && bookId !== null) renderReserveBook(bookId);
        else if (pageId === 'reservations') renderReservations();
        else if (pageId === 'account') fetchAccountData();
        else if (pageId === 'history') renderHistory();
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

// Function to load categories into the Search Dropdown
function loadSearchCategories() {
    const categorySelect = document.getElementById('search-category');
    if (!categorySelect) return;

    fetch('api.php?action=getCategories')
        .then(res => res.json())
        .then(data => {
            // Keep the default option
            categorySelect.innerHTML = '<option value="">All Categories</option>';
            data.forEach(cat => {
                categorySelect.innerHTML += `<option value="${cat.name}">${cat.name}</option>`;
            });
        })
        .catch(err => console.error("Error loading categories:", err));
}

// Main Search Function (Handles Text + Category + Enter Key)
function searchBooks() {
    const inputVal = document.getElementById('search-input').value.toLowerCase();
    const categorySelect = document.getElementById('search-category');
    const selectedCategory = categorySelect ? categorySelect.value : "";
    const resultsContainer = document.getElementById('search-results');

    // Go to search page if not already there
    navigateTo('search-library');

    // Filter Logic
    const filteredBooks = allBooks.filter(book => {
        // 1. Text Match
        const matchesText = (
            book.title.toLowerCase().includes(inputVal) ||
            book.author.toLowerCase().includes(inputVal) ||
            (book.year && book.year.toString().includes(inputVal))
        );
        
        // 2. Category Match
        const matchesCategory = selectedCategory === "" || book.category === selectedCategory;

        return matchesText && matchesCategory;
    });

    renderBooks(filteredBooks, resultsContainer);
}

// Modified renderBooks to accept a target container
function renderBooks(booksToRender, container) {
    if (!container) return;
    currentNavigationList = booksToRender;
    container.innerHTML = '';
    
    // Check if empty
    if (booksToRender.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-8">
                <i class="fas fa-book-open text-gray-400 text-4xl mb-3"></i>
                <p class="text-gray-600 text-lg">No books found.</p>
                <p class="text-gray-400 text-sm">Try adjusting your search terms or category.</p>
            </div>`;
        return;
    }

    booksToRender.forEach(book => {
        const isFavorited = myFavoriteBooks.some(fav => fav.id == book.id);
        const favBtnClass = isFavorited ? 'bg-gray-400 cursor-not-allowed' : 'bg-yellow-500 hover:bg-yellow-600';
        const favBtnText = isFavorited ? 'In Favorites' : 'Add to Favorites';
        
        // Consistent Card Design
        container.innerHTML += `
            <div class="bg-white/90 backdrop-blur-sm p-4 rounded-xl shadow-md border border-white/20 hover:-translate-y-1 hover:shadow-xl transition-all duration-300 cursor-pointer group flex flex-col items-center text-center h-full" onclick="navigateTo('reserve-book', ${book.id})">
                <img src="${book.cover}" alt="${book.title}" class="rounded-md mb-3 book-cover group-hover:scale-105 transition-transform" onerror="this.src='https://placehold.co/120x150?text=No+Image'">
                <h3 class="font-bold text-gray-800 text-sm mb-1 line-clamp-2">${book.title}</h3>
                <p class="text-xs text-gray-600 mb-2">Author: ${book.author}</p>
                <div class="mt-auto w-full">
                    <button class="text-white text-xs px-3 py-2 rounded-lg w-full ${favBtnClass} transition-colors font-medium shadow-sm" onclick="event.stopPropagation(); addToFavorites(${book.id})" ${isFavorited ? 'disabled' : ''}>
                        ${favBtnText}
                    </button>
                </div>
            </div>`;
    });
}

function renderReservePage() {
    const reserveSelect = document.getElementById('reserve-category-filter');
    
    fetch('api.php?action=getCategories')
        .then(res => res.json())
        .then(categories => {
            if (reserveSelect) {
                reserveSelect.innerHTML = '<option value="all">All Categories</option>';
                categories.forEach(cat => {
                    reserveSelect.innerHTML += `<option value="${cat.name}">${cat.name}</option>`;
                });

                reserveSelect.value = currentReserveCategory;
            }

            if (currentReserveCategory === 'all') {
                renderBooks(allBooks, reserveBookGrid);
            } else {
                const filteredBooks = allBooks.filter(book => book.category === currentReserveCategory);
                renderBooks(filteredBooks, reserveBookGrid);
            }
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
        const statusText = isAvailable ? 'Available' : 'Unavailable';
        const statusClass = isAvailable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        const reserveBtnClass = isAvailable ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-gray-300 text-gray-500 cursor-not-allowed';
        const removeBtnClass = 'bg-red-500 text-white hover:bg-red-600';

        myBooksList.innerHTML += `
            <div class="bg-white/90 backdrop-blur-sm p-4 rounded-xl shadow-md border border-white/20 flex items-start space-x-4">
                <img src="${book.cover}" alt="${book.title}" class="rounded-md book-cover-sm" onerror="this.src='https://placehold.co/80x110?text=No+Image'">
                <div class="flex-grow">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-bold text-gray-800">${book.title}</h3>
                            <p class="text-sm text-gray-600">Author: ${book.author}</p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full ${statusClass}">
                            ${statusText}
                        </span>
                    </div>
                    <div class="mt-3 flex justify-end space-x-2">
                        <button class="px-3 py-1.5 text-xs font-medium rounded-md ${reserveBtnClass} transition-colors" 
                                onclick="navigateTo('reserve-book', ${book.id})" ${!isAvailable ? 'disabled' : ''}>
                            Reserve
                        </button>
                        <button class="px-3 py-1.5 text-xs font-medium rounded-md ${removeBtnClass} transition-colors" 
                                onclick="removeFromFavorites(${book.id})">
                            Remove
                        </button>
                    </div>
                </div>
            </div>`;
    });
}

function renderReserveBook(bookId) {
    // 1. USE THE SAVED LIST (Works for both Search and Reserve pages)
    // If the list is empty (edge case), fallback to allBooks
    let navigationList = (currentNavigationList && currentNavigationList.length > 0) 
                         ? currentNavigationList 
                         : allBooks;

    // 2. Find book index in that specific list
    const index = navigationList.findIndex(b => b.id == bookId);
    
    // Safety fallback: If book isn't in the list (rare), find it in allBooks
    const book = index !== -1 ? navigationList[index] : allBooks.find(b => b.id == bookId);

    if (!book) {
        showModal('Error', 'Book details not found.');
        navigateTo('search-library');
        return;
    }

    // 3. Navigation (Previous/Next)
    const prevBook = index > 0 ? navigationList[index - 1] : null;
    const nextBook = index < navigationList.length - 1 ? navigationList[index + 1] : null;
    
    // 4. Prepare Arrow HTML (Same as before)
    const prevBtn = prevBook 
        ? `<button onclick="renderReserveBook(${prevBook.id})" class="p-3 bg-white rounded-full shadow-lg text-gray-600 hover:text-green-600 hover:scale-110 transition-all">
             <i class="fas fa-chevron-left text-xl"></i>
           </button>` 
        : `<div class="w-12"></div>`; 

    const nextBtn = nextBook 
        ? `<button onclick="renderReserveBook(${nextBook.id})" class="p-3 bg-white rounded-full shadow-lg text-gray-600 hover:text-green-600 hover:scale-110 transition-all">
             <i class="fas fa-chevron-right text-xl"></i>
           </button>` 
        : `<div class="w-12"></div>`; 

    // 5. Render the Compact Container (Same as before)
    const containerPage = document.getElementById('reserve-book-page');
    
    if (containerPage) {
        containerPage.className = "page-content w-full max-w-[1920px] mx-auto px-6 flex items-center justify-center min-h-[80vh]";
        
        const statusColor = book.status === 'Available' ? 'text-green-600' : 'text-red-600';
        
        const sliderHTML = `
            <div class="flex items-center justify-center gap-6 w-full max-w-5xl">
                ${prevBtn}

                <div class="bg-white/90 backdrop-blur-sm p-8 rounded-2xl shadow-2xl flex flex-col md:flex-row gap-8 w-full max-w-3xl relative animate-fadeIn">
                    
                    <button onclick="cancelReservationFlow()" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>

                    <div class="flex-shrink-0 mx-auto md:mx-0">
                         <img src="${book.cover}" alt="${book.title}" class="rounded-lg shadow-lg w-48 h-72 object-cover" onerror="this.src='https://placehold.co/120x150?text=No+Image'">
                    </div>

                    <div class="flex-grow text-left">
                        <h2 class="text-3xl font-bold text-gray-800 mb-1 leading-tight">${book.title}</h2>
                        <p class="text-xl text-gray-600 mb-4">${book.author}</p>
                        
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm text-gray-700 mb-6 bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                            <p>Publisher: <span class="font-semibold text-gray-900">${book.publisher || 'N/A'}</span></p>
                            <p>Category: <span class="font-semibold text-gray-900">${book.category || 'N/A'}</span></p>
                            <p>Year: <span class="font-semibold text-gray-900">${book.year || 'N/A'}</span></p>
                            <p>Copies: <span class="font-semibold text-gray-900">${book.copies}</span></p>
                            <div class="col-span-2 mt-1 pt-2 border-t border-gray-200 flex justify-start items-center gap-2">
                                <span>Status:</span>
                                <span class="font-bold px-3 py-1 rounded-full bg-white shadow-sm ${statusColor}">${book.status}</span>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Select Return Date</label>
                                <input id="return-date" type="date" class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-green-500 outline-none transition-all">
                            </div>
                            
                            <button id="confirm-reservation-button" class="w-full py-3 rounded-lg font-bold text-white shadow-md transition-all transform hover:-translate-y-0.5 ${book.status === 'Available' ? 'bg-green-600 hover:bg-green-700 hover:shadow-lg' : 'bg-gray-400 cursor-not-allowed'}" ${book.status !== 'Available' ? 'disabled' : ''}>
                                ${book.status === 'Available' ? 'Confirm Reservation' : 'Unavailable'}
                            </button>
                        </div>
                    </div>
                </div>

                ${nextBtn}
            </div>
        `;
        
        containerPage.innerHTML = sliderHTML;
        
        const confirmBtn = document.getElementById('confirm-reservation-button');
        if (confirmBtn && book.status === 'Available') {
            confirmBtn.onclick = () => confirmReservation(book.id);
        }
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
                    'Pending Pickup': 'bg-yellow-100 text-yellow-800',
                    'Claimed': 'bg-green-100 text-green-800',
                    'Cancelled': 'bg-red-100 text-red-800'
                };
                const statusClass = statusStyles[res.status] || 'bg-gray-100 text-gray-800';
                
                let actionButtonHTML = '';
                if (res.status === 'Pending Pickup') {
                    actionButtonHTML = `<button class="px-3 py-1.5 text-xs font-medium bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors" onclick="cancelReservation(${res.id})">Cancel Reservation</button>`;
                } else if (res.status === 'Cancelled') {
                     actionButtonHTML = `
                        <div class="flex items-center space-x-2">
                            <button class="px-3 py-1.5 text-xs font-medium bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors" onclick="deleteReservation(${res.id})">Delete</button>
                            <button class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors" onclick="navigateTo('reserve-book', ${res.book_id})">Reserve Again</button>
                        </div>
                    `;
                }

                reservationsList.innerHTML += `
                    <div class="bg-white/90 backdrop-blur-sm p-4 rounded-xl shadow-md border border-white/20 flex items-start space-x-4">
                        <img src="${res.cover}" alt="${res.title}" class="rounded-md book-cover-sm" onerror="this.src='https://placehold.co/80x110?text=No+Image'">
                        <div class="flex-grow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold text-gray-800">${res.title}</h3>
                                    <p class="text-sm text-gray-600">Author: ${res.author}</p>
                                </div>
                                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full ${statusClass}">
                                    ${res.status}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-gray-500 space-y-1">
                                
                                <div class="flex items-center group">
                                    <p>Transaction #: <span class="font-mono text-gray-700 font-medium select-all">${res.transaction_number}</span></p>
                                    <button onclick="copyToClipboard('${res.transaction_number}', this)" 
                                            class="ml-2 text-gray-400 hover:text-gray-600 transition-colors p-1 rounded focus:outline-none" 
                                            title="Copy Transaction ID">
                                        <i class="far fa-copy"></i>
                                    </button>
                                </div>
                                <p>Reserved: ${new Date(res.reservation_date).toLocaleDateString()}</p>
                                <p>Due: ${new Date(res.due_date).toLocaleDateString()}</p>
                            </div>
                            <div class="mt-3 flex justify-end">
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
    const activeBooks = borrowedBooks.filter(book => book.status !== 'Returned');
    if (activeBooks.length === 0) {
        borrowedListHome.innerHTML = '<p class="text-center text-gray-500 py-4">You have no active borrowed books.</p>';
        return;
    }

    activeBooks.forEach(book => {
        let displayStatus = book.status === 'Issued' ? 'Active' : book.status;
        const today = new Date();
        const dueDate = new Date(book.due_date);
        today.setHours(0, 0, 0, 0);

        if (displayStatus === 'Active' && dueDate < today) {
            displayStatus = 'Overdue';
        }

        const statusStyles = {
            'Active': 'bg-green-100 text-green-800 border-green-200',
            'Overdue': 'bg-red-100 text-red-800 border-red-200',
            'Returned': 'bg-gray-100 text-gray-800'
        };
        const statusClass = statusStyles[displayStatus] || 'bg-gray-100 text-gray-800';

        borrowedListHome.innerHTML += `
            <div class="bg-white border border-gray-100 p-3 rounded-lg shadow-sm flex items-start space-x-3 mb-2">
                <img src="${book.cover}" alt="${book.title}" class="rounded book-cover-sm" onerror="this.src='https://placehold.co/80x110?text=No+Image'">
                <div class="flex-grow flex flex-col">
                    <div class="flex justify-between items-center mb-1">
                        <h3 class="font-semibold text-sm text-gray-800">${book.title}</h3>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full border ${statusClass}">${displayStatus}</span>
                    </div>
                    <p class="text-xs text-gray-600">Author: ${book.author}</p>
                    <div class="flex justify-between mt-2 text-xs text-gray-500">
                        <span>Borrowed: ${new Date(book.borrow_date).toLocaleDateString()}</span>
                        <span class="font-medium">Due: ${new Date(book.due_date).toLocaleDateString()}</span>
                    </div>
                </div>
            </div>
        `;
    });
}

function renderHistory() {
    const historyList = document.getElementById('history-list');
    if (!historyList) return;
    
    historyList.innerHTML = '<p class="text-center text-gray-500">Loading history...</p>';

    fetch('api.php?action=getHistory')
        .then(res => res.json())
        .then(data => {
            historyList.innerHTML = '';
            
            if (data.length === 0) {
                historyList.innerHTML = '<p class="text-center text-gray-500 py-8">No returned books found in your history.</p>';
                return;
            }

            data.forEach(item => {
                historyList.innerHTML += `
                    <div class="bg-white/90 backdrop-blur-sm p-5 rounded-xl shadow-md border border-white/20 flex items-start space-x-4">
                        <img src="${item.cover}" alt="${item.title}" class="rounded-md book-cover-sm" onerror="this.src='https://placehold.co/80x110?text=No+Image'">
                        <div class="flex-grow">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="font-bold text-gray-800 text-lg">${item.title}</h3>
                                <span class="bg-gray-100 text-gray-600 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">Returned</span>
                            </div>
                            <p class="text-sm text-gray-600 mb-1">Author: ${item.author}</p>
                            <div class="text-xs text-gray-500 mt-2 flex items-center">
                                <i class="far fa-calendar-check mr-2"></i>
                                Returned on: ${new Date(item.return_date).toLocaleDateString()}
                            </div>
                        </div>
                    </div>
                `;
            });
        })
        .catch(error => {
            console.error('Error fetching history:', error);
            historyList.innerHTML = '<p class="text-center text-red-500">Could not load history.</p>';
        });
}

function clearAllHistory() {
    if (confirm("Are you sure you want to clear your entire transaction history?")) {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clearHistory' })
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                renderHistory();
            } else {
                alert("Failed to clear history.");
            }
        })
        .catch(error => console.error('Error:', error));
    }
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
                    if(window.location.hash === '#search-library') searchBooks(); // Use new search function
                    if(window.location.hash === '#reserve') renderReservePage();
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
                navigateTo('reservations'); 
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

// NEW: Helper function with Fallback for older browsers/insecure contexts
function copyToClipboard(text, btnElement) {
    // 1. Try Modern API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => showCopyFeedback(btnElement));
    } else {
        // 2. Fallback for non-secure contexts (older method)
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed"; // Avoid scrolling to bottom
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showCopyFeedback(btnElement);
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }
        document.body.removeChild(textArea);
    }
}

function showCopyFeedback(btnElement) {
    const originalIcon = btnElement.innerHTML;
    btnElement.innerHTML = '<i class="fas fa-check"></i>';
    btnElement.classList.remove('text-gray-400', 'hover:text-gray-600');
    btnElement.classList.add('text-green-500'); 

    setTimeout(() => {
        btnElement.innerHTML = originalIcon;
        btnElement.classList.remove('text-green-500');
        btnElement.classList.add('text-gray-400', 'hover:text-gray-600');
    }, 2000);
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Fetch initial data
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

    // 2. Load Search Categories
    loadSearchCategories();
    
    // --- NEW: Instant Search on Category Change ---
    const categorySelect = document.getElementById('search-category');
    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            searchBooks(); // Trigger search immediately when category changes
        });
    }

    // 3. Add Enter key listener for search
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                searchBooks(); // Trigger search on Enter
            }
        });
    }

    // NEW: Handle Reserve Page Dropdown Change
    const reserveSelect = document.getElementById('reserve-category-filter');
    if (reserveSelect) {
        reserveSelect.addEventListener('change', (e) => {
            // 1. SAVE: Update the global memory variable
            currentReserveCategory = e.target.value;
            
            // 2. Filter using the new value
            if (currentReserveCategory === 'all') {
                renderBooks(allBooks, reserveBookGrid);
            } else {
                const filteredBooks = allBooks.filter(book => book.category === currentReserveCategory);
                renderBooks(filteredBooks, reserveBookGrid);
            }
        });
    }
    
    // 5. Update Search Button Click (UPDATED)
    if (searchButton) {
        searchButton.addEventListener('click', () => {
             searchBooks(); 
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