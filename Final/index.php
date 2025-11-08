<?php 
include '../session_check.php'; 

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user data
$stmt = $conn->prepare("SELECT course, year FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Check and redirect if incomplete
if ($user && ($user['course'] == 'N/A' || $user['year'] == 'N/A' || $user['course'] == '' || $user['year'] == '')) {
    header('Location: ../complete_profile.php'); // Redirect to complete profile
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen" style="font-family: 'Century Gothic', sans-serif;">
    <div class="container-wrapper rounded-lg">
        <div id="app" class="h-full flex flex-col">
            <header class="p-4 flex items-center justify-between">
                <div class="Logo-title">
                <img src="Logo.png" alt="logo" class="logo">
                <span class="site-title">JHCSC</span>
                </div>
                <nav>
                    <ul class="flex_space-x-4">
                        <li><a href="#home" class="nav-link" data-page="home">Home</a></li>
                        <li><a href="#reservations" class="nav-link" data-page="reservations">My Reservations</a></li>
                        <li><a href="#account" class="nav-link" data-page="account">Account</a></li>
                    </ul>
                </nav>
            </header>

            <main class="flex-grow p-4 overflow-auto">
                <div id="home-page" class="page-content container active">
                    <div class="text-center mb-6">
                        <h2 class="text-2xl font-semibold mb-2" style="color: #004912;">Welcome to JHCSC</h2>
                        <p class="text-gray-600">Your gateway to book reservations and library access</p>
                    </div>
                    <div class="flex justify-center items-center mb-6 space-x-4">
                        <button class="Reverse_Book" onclick="navigateTo('reserve')">Reserve a book</button>
                        <button class="Search_Lib" onclick="navigateTo('search-library')">Search Library</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white p-4 rounded-lg shadow-md text-center border border-gray-200">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-book-open text-gray-500 w-12 h-12 mb-2"></i>
                                <div class="text-center">
                                    <h3 class="text-sm font-medium text-green-700">My Books</h3>
                                    <p class="text-xs text-gray-500">View my favorited books</p>
                                </div>
                                <button class="mt-2 px-4 py-1 text-xs bg-gray-200 text-gray-800 rounded-full hover:bg-gray-300 transition-colors" onclick="navigateTo('my-books')">Click</button>
                            </div>
                        </div>

                        <div class="bg-white p-4 rounded-lg shadow-md text-center border border-gray-200">
                            <div class="flex flex-col items-center">
                                <i class="far fa-calendar-alt text-gray-500 w-12 h-12 mb-2"></i>
                                <div class="text-center">
                                    <h3 class="text-sm font-medium text-green-700">My Reservations</h3>
                                    <p class="text-xs text-gray-500">Check the status of my reservations</p>
                                </div>
                                <button class="mt-2 px-4 py-1 text-xs bg-gray-200 text-gray-800 rounded-full hover:bg-gray-300 transition-colors" onclick="navigateTo('reservations')">Click</button>
                            </div>
                        </div>

                        <div class="bg-white p-4 rounded-lg shadow-md text-center border border-gray-200">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-user-circle text-gray-500 w-12 h-12 mb-2"></i>
                                <div class="text-center">
                                    <h3 class="text-sm font-medium text-green-700">My Account</h3>
                                    <p class="text-xs text-gray-500">Manage account preferences</p>
                                </div>
                                <button class="mt-2 px-4 py-1 text-xs bg-gray-200 text-gray-800 rounded-full hover:bg-gray-300 transition-colors" onclick="navigateTo('account')">Click</button>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/70 backdrop-blur-sm p-6 rounded-lg shadow-md mt-8">
                        <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2">My Borrowed Books</h2>
                        <div id="home-borrowed-books-list" class="space-y-3 max-h-96 overflow-y-auto pr-2">
                        </div>
                    </div>
                    <div class="text-center mt-6 text-sm text-gray-500">
                        <a href="#" class="hover:underline">About</a> | 
                        <a href="#" class="hover:underline">Contact</a> | 
                        <a href="#" class="hover:underline">Help</a>
                    </div>
                </div>
                <div id="reserve-page" class="page-content container hidden">
                    <h2 class="text-xl font-bold mb-4">Reserve a Book</h2>
                    <div id="category-filters" class="flex flex-wrap gap-2 mb-6">
                        </div>
                    <div id="reserve-book-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        </div>
                </div>

                <div id="reservations-page" class="page-content container hidden">
                    <h2 class="text-xl font-bold mb-4">My Reservations</h2>
                    <div id="reservations-list" class="space-y-4">
                    </div>
                </div>

                <div id="my-books-page" class="page-content container hidden">
                    <h2 class="text-xl font-bold mb-4">My Books</h2>
                    <div id="my-books-list" class="space-y-4">
                    </div>
                </div>

                <div id="search-library-page" class="page-content container hidden">
                    <h2 class="text-xl font-bold mb-4">Search Library</h2>
                    <div class="flex items-center space-x-2 mb-4">
                        <input id="search-input" type="text" placeholder="Search by title, author, or keyword..." class="flex-grow p-2 rounded-lg border border-gray-300">
                        <button id="search-button" class="bg-green-500 text-white px-4 py-2 rounded-lg">Search</button>
                    </div>
                    <div id="search-results" class="grid grid-cols-2 gap-4">
                    </div>
                </div>

                <div id="reserve-book-page" class="page-content container hidden">
                    <div class="flex flex-col items-center p-6 bg-white rounded-lg shadow-md">
                        <div class="flex flex-col md:flex-row items-center md:items-start space-y-4 md:space-y-0 md:space-x-4" id="reserve-book-content">
                        </div>
                        <div class="flex justify-center space-x-4 mt-6">
                            <button class="bg-red-500 text-white px-6 py-2 rounded-lg shadow-md hover:bg-red-600 transition-colors" onclick="cancelReservationFlow()">Cancel</button>
                            <button class="px-6 py-2 bg-yellow-600 text-white rounded-lg shadow-md hover:bg-yellow-700 transition-colors" id="confirm-reservation-button">Confirm Reservation</button>
                        </div>
                    </div>
                </div>
                
                <div id="account-page" class="page-content container hidden">
                    <h2 class="text-xl font-bold mb-4">My Account</h2>
                    <div class="bg-white p-6 rounded-lg shadow-md max-w-lg mx-auto">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold">Personal Information</h2>
                            <div class="flex space-x-2">
                                <button id="logout-button" class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 transition-colors">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </button>
                                <button id="edit-button" class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 transition-colors" onclick="showEditForm()">
                                    <i class="fas fa-edit mr-2"></i>Edit
                                </button>
                            </div>
                        </div>
                        <div id="account-info-display">
                            <div class="flex justify-between items-center py-2 border-b">
                                <div class="text-gray-600">Name</div>
                                <div class="flex items-center">
                                    <span id="account-name" class="font-medium"></span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b">
                                <div class="text-gray-600">Course</div>
                                <div class="flex items-center">
                                    <span id="account-course" class="font-medium"></span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b">
                                <div class="text-gray-600">Year Level</div>
                                <div class="flex items-center">
                                    <span id="account-yearLevel" class="font-medium"></span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b">
                                <div class="text-gray-600">Email</div>
                                <div class="flex items-center">
                                    <span id="account-email" class="font-medium"></span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b">
                                <div class="text-gray-600">Password</div>
                                <div class="flex items-center">
                                    <span id="account-password" class="font-medium"></span>
                                </div>
                            </div>
                        </div>

                        <div id="account-edit-form" class="hidden">
                            <div class="space-y-4">
                                <div class="flex flex-col">
                                    <label class="text-gray-600 mb-1" for="edit-name">Name</label>
                                    <input type="text" id="edit-name" class="border border-gray-300 rounded-md p-2">
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-gray-600 mb-1" for="edit-course">Course</label>
                                    <input type="text" id="edit-course" class="border border-gray-300 rounded-md p-2">
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-gray-600 mb-1" for="edit-yearLevel">Year Level</label>
                                    <select id="edit-yearLevel" class="border border-gray-300 rounded-md p-2">
                                        <option value="1st Year">1st Year</option>
                                        <option value="2nd Year">2nd Year</option>
                                        <option value="3rd Year">3rd Year</option>
                                        <option value="4th Year">4th Year</option>
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-gray-600 mb-1" for="edit-email">Email</label>
                                    <input type="email" id="edit-email" class="border border-gray-300 rounded-md p-2">
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-gray-600 mb-1" for="edit-password">Password</label>
                                    <div class="relative">
                                        <input type="password" id="edit-password" class="w-full border border-gray-300 rounded-md p-2 pr-10">
                                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500" onclick="togglePasswordVisibility()">
                                            <i id="password-toggle-icon" class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-center space-x-4 mt-6">
                                <button class="bg-red-500 text-white px-4 py-2 rounded-md" onclick="cancelEdit()">Cancel</button>
                                <button class="bg-green-500 text-white px-4 py-2 rounded-md" onclick="saveEdit()">Save</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="borrowed-books-page" class="page-content container hidden">
                    <h2 class="text-xl font-bold mb-4">My Borrowed Books</h2>
                     <div id="borrowed-books-list" class="space-y-4">
                        </div>
                </div>
                
            </main>

            <div id="message-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center p-4">
                <div class="bg-white rounded-lg p-6 shadow-xl max-w-sm w-full text-center">
                    <h3 id="modal-title" class="text-lg font-semibold mb-2"></h3>
                    <p id="modal-message" class="text-gray-600 mb-4"></p>
                    <button class="px-4 py-2 bg-green-500 text-white rounded-full hover:bg-green-600 transition-colors" onclick="hideModal()">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
    <script src="account.js"></script>
</body>
</html>