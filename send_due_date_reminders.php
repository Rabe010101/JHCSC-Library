<?php
// send_due_date_reminders.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require 'mailer_config.php'; // Ensure this is created!

// --- 1. Setup Log Tracking ---
$logFile = __DIR__ . '/reminder_log.txt';
$today = date('Y-m-d');

// Read existing log to see who we already emailed today
$logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
$sentReminders = explode(PHP_EOL, $logContent);

// --- 2. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { exit(); }

// ==========================================
// 1. Send "DUE TODAY" Reminders
// ==========================================
$sql_today = "SELECT ib.transaction_number, u.firstname, u.email, b.title 
              FROM issued_books ib
              JOIN users u ON ib.user_id = u.id
              JOIN books b ON ib.book_id = b.id
              WHERE ib.status = 'Issued' AND ib.due_date = CURDATE()";

$result_today = $conn->query($sql_today);

if ($result_today && $result_today->num_rows > 0) {
    while($row = $result_today->fetch_assoc()) {
        
        // Unique Key: Date + Type + TransactionID
        $logKey = $today . "|DueToday|" . $row['transaction_number'];

        // Skip if already sent today
        if (in_array($logKey, $sentReminders)) {
            continue;
        }

        $sent = sendEmail($row['email'], $row['firstname'], 'URGENT: Book Return Due Today', 
            "<h2 style='color: #ff8800;'>Book Due Date Reminder</h2>
             <p>Hi <b>" . htmlspecialchars($row['firstname']) . "</b>,</p>
             <p>This is a reminder that <b>" . htmlspecialchars($row['title']) . "</b> is due TODAY.</p>
             <p>Please return it by 4:00 PM.</p>");

        // Log it immediately if successful
        if ($sent) {
            file_put_contents($logFile, $logKey . PHP_EOL, FILE_APPEND);
        }
    }
}

// ==========================================
// 2. Send "OVERDUE" Notices (Daily)
// ==========================================
$sql_overdue = "SELECT ib.transaction_number, u.firstname, u.email, b.title, ib.due_date
                FROM issued_books ib
                JOIN users u ON ib.user_id = u.id
                JOIN books b ON ib.book_id = b.id
                WHERE ib.status = 'Issued' AND ib.due_date < CURDATE()";

$result_overdue = $conn->query($sql_overdue);

if ($result_overdue && $result_overdue->num_rows > 0) {
    while($row = $result_overdue->fetch_assoc()) {
        
        // Unique Key: Date + Type + TransactionID
        $logKey = $today . "|Overdue|" . $row['transaction_number'];

        // Skip if already sent today
        if (in_array($logKey, $sentReminders)) {
            continue;
        }
        
        // Calculate days overdue
        $dueDate = new DateTime($row['due_date']);
        $todayDate = new DateTime();
        $daysOverdue = $todayDate->diff($dueDate)->days;

        $sent = sendEmail($row['email'], $row['firstname'], 'OVERDUE NOTICE: Please Return Book', 
            "<h2 style='color: #d32f2f;'>OVERDUE NOTICE</h2>
             <p>Hi <b>" . htmlspecialchars($row['firstname']) . "</b>,</p>
             <p>The book <b>" . htmlspecialchars($row['title']) . "</b> is now <b style='color:red;'>$daysOverdue days overdue</b>.</p>
             <p>You cannot borrow any new books until this is returned.</p>
             <p>Please return it immediately to avoid penalties.</p>");

        // Log it immediately if successful
        if ($sent) {
            file_put_contents($logFile, $logKey . PHP_EOL, FILE_APPEND);
        }
    }
}

$conn->close();

// --- Helper Function ---
function sendEmail($to, $name, $subject, $bodyContent) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to, $name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "<div style='font-family: Arial, sans-serif; color: #333;'>" . $bodyContent . "<br><br>JHCSC Library Team</div>";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Optional: Log error to a separate error_log file
        return false;
    }
}
?>