document.addEventListener('DOMContentLoaded', function () {
    // --- Selectors ---
    const usersTableBody = document.getElementById('usersTableBody');
    const searchBox = document.getElementById('searchBox');
    const yearFilter = document.getElementById('yearFilter');
    const courseFilter = document.getElementById('courseFilter');
    
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const pageInfo = document.getElementById('page-info');

    // Email Modal Selectors
    const emailModal = document.getElementById('emailModal');
    const closeEmailModalBtn = document.getElementById('closeEmailModal');
    const emailForm = document.getElementById('emailForm');
    const emailRecipientInput = document.getElementById('emailRecipient');
    const emailDisplayInput = document.getElementById('emailDisplay');

    // --- Global Variables ---
    let currentPage = 1;
    const itemsPerPage = 20;

    // --- Fetch Users ---
    function fetchUsers(page = 1) {
        const searchTerm = searchBox ? searchBox.value : '';
        const year = yearFilter ? yearFilter.value : '';
        const course = courseFilter ? courseFilter.value : '';

        const apiUrl = `users_api.php?search=${encodeURIComponent(searchTerm)}&year=${encodeURIComponent(year)}&course=${encodeURIComponent(course)}&page=${page}&limit=${itemsPerPage}`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(response => {
                const users = response.data || [];
                const pagination = response.pagination;

                usersTableBody.innerHTML = '';

                if (users.length === 0) {
                    usersTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">No registered users found.</td></tr>';
                    if(pagination) updatePaginationControls(0, 0, 1);
                    return;
                }

                users.forEach(user => {
                    const row = usersTableBody.insertRow();
                    
                    // --- NEW: Detailed Status Logic ---
                    const overdue = parseInt(user.overdue_count || 0);
                    const active = parseInt(user.active_count || 0);
                    let statusHtml = '';

                    if (overdue > 0) {
                        // Priority 1: Overdue (Red)
                        const s = overdue > 1 ? 's' : '';
                        statusHtml = `<span style="color: #d32f2f; font-weight: bold;">${overdue} Overdue Book${s}</span>`;
                    } else if (active > 0) {
                        // Priority 2: Active but safe (Green)
                        const s = active > 1 ? 's' : '';
                        statusHtml = `<span style="color: green; font-weight: 500;">${active} Active Book${s}</span>`;
                    } else {
                        // Priority 3: Nothing borrowed (Gray)
                        statusHtml = `<span style="color: #888; font-style: italic;">No Active Books</span>`;
                    }
                    // ----------------------------------

                    row.innerHTML = `
                        <td>${user.id}</td>
                        <td>${user.firstname} ${user.surname}</td>
                        <td>${user.course}</td>
                        <td>${user.year}</td>
                        <td>${user.email}</td>
                        <td>${statusHtml}</td>
                        <td>
                            <button class="btn-email" onclick="openEmailModal('${user.email}', '${user.firstname} ${user.surname}')">
                                Email
                            </button>
                        </td>
                    `;
                });

                if (pagination) {
                    updatePaginationControls(pagination.totalRecords, pagination.totalPages, pagination.currentPage);
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // --- Email Modal Logic ---
    window.openEmailModal = function(email, name) {
        emailRecipientInput.value = email;
        emailDisplayInput.value = `${name} <${email}>`;
        document.getElementById('emailSubject').value = '';
        document.getElementById('emailMessage').value = '';
        emailModal.style.display = 'block';
    };

    closeEmailModalBtn.onclick = function() {
        emailModal.style.display = 'none';
    };

    window.onclick = function(event) {
        if (event.target == emailModal) {
            emailModal.style.display = 'none';
        }
    };

    emailForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = emailForm.querySelector('button');
        const originalText = btn.textContent;
        btn.textContent = 'Sending...';
        btn.disabled = true;

        const data = {
            email: emailRecipientInput.value,
            subject: document.getElementById('emailSubject').value,
            message: document.getElementById('emailMessage').value
        };

        fetch('send_email_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                emailModal.style.display = 'none';
            }
            btn.textContent = originalText;
            btn.disabled = false;
        })
        .catch(err => {
            alert('Error sending email.');
            btn.textContent = originalText;
            btn.disabled = false;
        });
    });

    // --- Pagination Controls ---
    function updatePaginationControls(totalRecords, totalPages, current) {
        currentPage = current;
        if (pageInfo) pageInfo.textContent = `Page ${current} of ${totalPages || 1} (Total: ${totalRecords})`;

        if (prevBtn && nextBtn) {
            if (current <= 1) {
                prevBtn.disabled = true;
                prevBtn.style.background = '#ccc';
            } else {
                prevBtn.disabled = false;
                prevBtn.style.background = '#005aad';
            }

            if (current >= totalPages || totalPages === 0) {
                nextBtn.disabled = true;
                nextBtn.style.background = '#ccc';
            } else {
                nextBtn.disabled = false;
                nextBtn.style.background = '#005aad';
            }
        }
    }

    function updateFilters() {
        currentPage = 1;
        fetchUsers(1);
    }

    if (searchBox) searchBox.addEventListener('input', updateFilters);
    if (yearFilter) yearFilter.addEventListener('change', updateFilters);
    if (courseFilter) courseFilter.addEventListener('change', updateFilters);

    if (prevBtn) prevBtn.addEventListener('click', () => { if (currentPage > 1) fetchUsers(currentPage - 1); });
    if (nextBtn) nextBtn.addEventListener('click', () => { fetchUsers(currentPage + 1); });

    fetchUsers(1);
});