// Registered.js

document.addEventListener('DOMContentLoaded', function () {
    // --- 1. Element Selectors ---
    const usersTableBody = document.getElementById('usersTableBody');
    const searchBox = document.getElementById('searchBox');
    const yearFilter = document.getElementById('yearFilter');
    const courseFilter = document.getElementById('courseFilter');

    // --- 2. Main Function to Fetch and Display Users ---
    function fetchUsers() {
        // Get current values from filter inputs
        const searchTerm = searchBox.value;
        const year = yearFilter.value;
        const course = courseFilter.value;

        // Construct the API URL with query parameters
        const apiUrl = `users_api.php?search=${encodeURIComponent(searchTerm)}&year=${encodeURIComponent(year)}&course=${encodeURIComponent(course)}`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(users => {
                // Clear the existing table rows before adding new ones
                usersTableBody.innerHTML = '';

                if (users.error) {
                    console.error(users.error);
                    return;
                }
                
                if (users.length === 0) {
                    usersTableBody.innerHTML = '<tr><td colspan="6">No users found.</td></tr>';
                    return;
                }

                // Loop through each user and create a table row
                users.forEach(user => {
                    const row = usersTableBody.insertRow();
                    row.innerHTML = `
                        <td>${user.id}</td>
                        <td>${user.firstname} ${user.surname}</td>
                        <td>${user.course}</td>
                        <td>${user.year}</td>
                        <td>${user.email}</td>
                        <td><button class="btn-email">Email</button></td>
                    `;
                });
            })
            .catch(error => {
                console.error('Error fetching users:', error);
                usersTableBody.innerHTML = '<tr><td colspan="6">Failed to load data.</td></tr>';
            });
    }

    // --- 3. Event Listeners for Filters ---
    // Add listeners to refetch data whenever a filter value changes
    searchBox.addEventListener('input', fetchUsers);
    yearFilter.addEventListener('change', fetchUsers);
    courseFilter.addEventListener('change', fetchUsers);

    // --- 4. Initial Data Load ---
    // Fetch users as soon as the page loads
    fetchUsers();
});