<?php
// send_due_date_reminders.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

// --- Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { exit(); }

// ==========================================
// 1. Send "DUE TODAY" Reminders
// ==========================================
$sql_today = "SELECT u.firstname, u.email, b.title 
              FROM issued_books ib
              JOIN users u ON ib.user_id = u.id
              JOIN books b ON ib.book_id = b.id
              WHERE ib.status = 'Issued' AND ib.due_date = CURDATE()";

$result_today = $conn->query($sql_today);

if ($result_today && $result_today->num_rows > 0) {
    while($row = $result_today->fetch_assoc()) {
        sendEmail($row['email'], $row['firstname'], 'URGENT: Book Return Due Today', 
            "<h2 style='color: #ff8800;'>Book Due Date Reminder</h2>
             <p>Hi <b>" . htmlspecialchars($row['firstname']) . "</b>,</p>
             <p>This is a reminder that <b>" . htmlspecialchars($row['title']) . "</b> is due TODAY.</p>
             <p>Please return it by 4:00 PM.</p>");
    }
}

// ==========================================
// 2. Send "OVERDUE" Notices (Daily)
// ==========================================
$sql_overdue = "SELECT u.firstname, u.email, b.title, ib.due_date
                FROM issued_books ib
                JOIN users u ON ib.user_id = u.id
                JOIN books b ON ib.book_id = b.id
                WHERE ib.status = 'Issued' AND ib.due_date < CURDATE()";

$result_overdue = $conn->query($sql_overdue);

if ($result_overdue && $result_overdue->num_rows > 0) {
    while($row = $result_overdue->fetch_assoc()) {
        
        // Calculate how many days overdue
        $dueDate = new DateTime($row['due_date']);
        $today = new DateTime();
        $daysOverdue = $today->diff($dueDate)->days;

        sendEmail($row['email'], $row['firstname'], 'OVERDUE NOTICE: Please Return Book', 
            "<h2 style='color: #d32f2f;'>OVERDUE NOTICE</h2>
             <p>Hi <b>" . htmlspecialchars($row['firstname']) . "</b>,</p>
             <p>The book <b>" . htmlspecialchars($row['title']) . "</b> is now <b style='color:red;'>$daysOverdue days overdue</b>.</p>
             <p>You cannot borrow any new books until this is returned.</p>
             <p>Please return it immediately to avoid penalties.</p>");
    }
}

$conn->close();
return true;

// Helper function to send email
function sendEmail($to, $name, $subject, $bodyContent) {
    $mail = new PHPMailer(true);
    try {
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
        $mail->Subject = $subject;
        $mail->Body    = "<div style='font-family: Arial, sans-serif; color: #333;'>" . $bodyContent . "<br><br>JHCSC Library Team</div>";
        
        $mail->send();
    } catch (Exception $e) { }
}
?>