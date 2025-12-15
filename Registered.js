// Registered.js

document.addEventListener('DOMContentLoaded', function () {
    // --- 1. Element Selectors ---
    const usersTableBody = document.getElementById('usersTableBody');
    const searchBox = document.getElementById('searchBox'); // Ensure this ID matches your HTML
    const yearFilter = document.getElementById('yearFilter'); // Ensure this ID matches your HTML
    const courseFilter = document.getElementById('courseFilter'); // Ensure this ID matches your HTML

    // Pagination Selectors
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const pageInfo = document.getElementById('page-info');

    // --- 2. Global Variables ---
    let currentPage = 1;
    const itemsPerPage = 20;

    // --- 3. Main Function to Fetch Users ---
    function fetchUsers(page = 1) {
        // Get current values from filters
        const searchTerm = searchBox ? searchBox.value : '';
        const year = yearFilter ? yearFilter.value : '';
        const course = courseFilter ? courseFilter.value : '';

        // Construct API URL
        const apiUrl = `users_api.php?search=${encodeURIComponent(searchTerm)}&year=${encodeURIComponent(year)}&course=${encodeURIComponent(course)}&page=${page}&limit=${itemsPerPage}`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(response => {
                // Handle new API response structure { data: [...], pagination: {...} }
                const users = response.data || [];
                const pagination = response.pagination;

                usersTableBody.innerHTML = '';

                if (users.length === 0) {
                    usersTableBody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 20px;">No registered users found.</td></tr>';
                    updatePaginationControls(0, 0, 1);
                    return;
                }

                // Generate Rows
                users.forEach(user => {
                    const row = usersTableBody.insertRow();
                    row.innerHTML = `
                        <td>${user.id}</td>
                        <td>${user.firstname} ${user.surname}</td>
                        <td>${user.course}</td>
                        <td>${user.year}</td>
                        <td>${user.email}</td>
                        <td>
                            <button class="btn-email" onclick="window.open('https://mail.google.com/mail/?view=cm&fs=1&to=${user.email}', '_blank')">
                                Email
                            </button>
                        </td>
                    `;
                });

                // Update Pagination Buttons
                if (pagination) {
                    updatePaginationControls(pagination.totalRecords, pagination.totalPages, pagination.currentPage);
                }
            })
            .catch(error => {
                console.error('Error fetching users:', error);
                usersTableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Failed to load data.</td></tr>';
            });
    }

    // --- 4. Pagination UI Logic ---
    function updatePaginationControls(totalRecords, totalPages, current) {
        currentPage = current;
        
        if (pageInfo) {
            pageInfo.textContent = `Page ${current} of ${totalPages || 1} (Total: ${totalRecords})`;
        }

        if (prevBtn && nextBtn) {
            // Previous Button State
            if (current <= 1) {
                prevBtn.disabled = true;
                prevBtn.style.background = '#ccc';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.background = '#005aad'; // Your email button color
                prevBtn.style.cursor = 'pointer';
            }

            // Next Button State
            if (current >= totalPages || totalPages === 0) {
                nextBtn.disabled = true;
                nextBtn.style.background = '#ccc';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.background = '#005aad';
                nextBtn.style.cursor = 'pointer';
            }
        }
    }

    // --- 5. Event Listeners ---
    
    // Filters: Reset to Page 1 when searching/filtering
    function updateFilters() {
        currentPage = 1; 
        fetchUsers(1);
    }

    if (searchBox) searchBox.addEventListener('input', updateFilters);
    if (yearFilter) yearFilter.addEventListener('change', updateFilters);
    if (courseFilter) courseFilter.addEventListener('change', updateFilters);

    // Pagination Buttons
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) fetchUsers(currentPage - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            // Check disabled attribute to be safe
            if (!nextBtn.disabled) fetchUsers(currentPage + 1);
        });
    }

    // --- 6. Initial Load ---
    fetchUsers(1);
});