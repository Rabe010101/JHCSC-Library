<?php
// Ensures the user is logged in
include 'session_check.php';

// --- NEW: DATABASE LOGIC TO GET COUNTS ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Query to get the total number of registered users (excluding admins)
$userResult = $conn->query("SELECT COUNT(id) as user_count FROM users");
$userCount = $userResult->fetch_assoc()['user_count'];

// 2. Query to get the total number of books
$bookResult = $conn->query("SELECT COUNT(id) as book_count FROM books");
$bookCount = $bookResult->fetch_assoc()['book_count'];

// Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="Dashboard.css">
</head>
<body>
  <nav>
    <div class="logo-title">
      <img src="images/School_logo.png" alt="logo" class="logo">
      <span class="site-title">JHCSC</span>
    </div>
    <ul>
      <li><a href="Dashboard.php" class="active">Dashboard</a></li>
      <li><a href="Account.html">Account</a></li>
    </ul>
  </nav>

  <section class="dashboard">
    <div class="card blue">
      <h2>Registered User</h2>
      <p>No. of users: <strong><?php echo $userCount; ?></strong></p>
      <a href="Registered.html"><button>View users</button></a>
    </div>

    <div class="card yellow">
      <h2>Total Books</h2>
      <p>Total books: <strong><?php echo $bookCount; ?></strong></p>
      <a href="Books.html"><button>View books</button></a>
    </div>

    <div class="card green">
      <h2>Book Logs</h2>
      <p>Manage borrowing and returns</p>
      <a href="Booklog_interfaces/Reservation.html"><button>View</button></a>
    </div>
  </section>
</body>
</html>