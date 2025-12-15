<?php
// send_email_api.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

session_start();
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

// Get Data
$data = json_decode(file_get_contents('php://input'), true);
$recipientEmail = $data['email'] ?? '';
$subject = $data['subject'] ?? '';
$body = $data['message'] ?? '';

if (empty($recipientEmail) || empty($subject) || empty($body)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jhcsc.e.lib@gmail.com';
    $mail->Password   = 'tmci lyzg vauy ibwd'; // Your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('jhcsc.e.lib@gmail.com', 'JHCSC Library');
    $mail->addAddress($recipientEmail);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    
    // --- UPDATED FOOTER MESSAGE ---
    $mail->Body    = "<div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                        <h2 style='color: #046421;'>JHCSC Library Message</h2>
                        <p style='font-size: 16px; line-height: 1.6;'>" . nl2br(htmlspecialchars($body)) . "</p>
                        <br>
                        <hr style='border: 0; border-top: 1px solid #eee;'>
                        <small style='color: #777;'>This message was sent by the JHCSC Library Administration.</small>
                      </div>";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Email sent successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
}
?>