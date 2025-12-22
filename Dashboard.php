<?php
// 1. Session & Dependencies
include 'session_check.php';
require 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ====================================================
// === START: DAILY EMAIL REMINDER SYSTEM =============
// ====================================================

$log_file = 'reminder_log.txt';
$today = date('Y-m-d');

// Read the last run date
$last_run = file_exists($log_file) ? file_get_contents($log_file) : '';

// Only run if we haven't run it today yet
if ($last_run != $today) {
    
    // --- BATCH 1: BOOKS DUE TODAY (Friendly Reminder) ---
    $sql_today = "SELECT u.firstname, u.email, b.title 
                  FROM issued_books ib
                  JOIN users u ON ib.user_id = u.id
                  JOIN books b ON ib.book_id = b.id
                  WHERE ib.status = 'Issued' AND ib.due_date = CURDATE()";

    $result_today = $conn->query($sql_today);

    if ($result_today && $result_today->num_rows > 0) {
        while($row = $result_today->fetch_assoc()) {
            sendReminderEmail($row['email'], $row['firstname'], $row['title'], 'today');
        }
    }

    // --- BATCH 2: BOOKS OVERDUE (URGENT Notice) ---
    $sql_overdue = "SELECT u.firstname, u.email, b.title, ib.due_date
                    FROM issued_books ib
                    JOIN users u ON ib.user_id = u.id
                    JOIN books b ON ib.book_id = b.id
                    WHERE ib.status = 'Issued' AND ib.due_date < CURDATE()";

    $result_overdue = $conn->query($sql_overdue);

    if ($result_overdue && $result_overdue->num_rows > 0) {
        while($row = $result_overdue->fetch_assoc()) {
            // Calculate days overdue
            $dueDate = new DateTime($row['due_date']);
            $now = new DateTime();
            $daysOverdue = $now->diff($dueDate)->days;
            
            sendReminderEmail($row['email'], $row['firstname'], $row['title'], 'overdue', $daysOverdue);
        }
    }

    // Mark as done for today
    file_put_contents($log_file, $today);
}

// Helper function to keep code clean
function sendReminderEmail($to, $name, $bookTitle, $type, $days = 0) {
    $mail = new PHPMailer(true);
    try {
        // --- Server settings ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jhcsc.e.lib@gmail.com';
        $mail->Password   = 'tmci lyzg vauy ibwd';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('jhcsc.e.lib@gmail.com', 'JHCSC Library');
        $mail->addAddress($to, $name);
        $mail->isHTML(true);

        if ($type === 'today') {
            // Friendly Reminder
            $mail->Subject = 'Book Return Reminder: Due Today';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h2 style='color: #28a745;'>Book Return Reminder</h2>
                    <p>Hi <b>" . htmlspecialchars($name) . "</b>,</p>
                    <p>This is a friendly reminder that the book <b>" . htmlspecialchars($bookTitle) . "</b> is due to be returned <b>TODAY</b>.</p>
                    <p>Please return it before 5:00 PM to avoid Account Restriction.</p>
                    <br><p>JHCSC Library Team</p>
                </div>";
        } else {
            // Urgent Warning
            $mail->Subject = 'URGENT: Overdue Book Notice';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h2 style='color: #d32f2f;'>URGENT: OVERDUE NOTICE</h2>
                    <p>Hi <b>" . htmlspecialchars($name) . "</b>,</p>
                    <p>The book <b>" . htmlspecialchars($bookTitle) . "</b> is now <b style='color:red;'>$days days overdue</b>.</p>
                    <div style='background: #ffe6e6; padding: 10px; border-left: 5px solid #d32f2f; margin: 15px 0;'>
                        <p style='margin:0;'><b>Account Restricted:</b> You cannot borrow any new books until this item is returned.</p>
                    </div>
                    <p>Please return it immediately to remove Account Restriction.</p>
                    <br><p>JHCSC Library Team</p>
                </div>";
        }
        $mail->send();
    } catch (Exception $e) { }
}
// ====================================================
// === END: DAILY REMINDER SYSTEM =====================
// ====================================================


// 3. Get Dashboard Stats
$userResult = $conn->query("SELECT COUNT(id) as user_count FROM users");
$userCount = $userResult->fetch_assoc()['user_count'];

$bookResult = $conn->query("SELECT COUNT(id) as book_count FROM books");
$bookCount = $bookResult->fetch_assoc()['book_count'];

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