<?php
// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

// Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);

try {
    // --- Server settings ---
    $mail->isSMTP();                                      // Send using SMTP
    $mail->Host       = 'smtp.gmail.com';                 // Set the SMTP server to send through
    $mail->SMTPAuth   = true;                             // Enable SMTP authentication
    $mail->Username   = 'jhcsc.e.lib@gmail.com';       // 1. <-- EDIT THIS: Your new Gmail address
    $mail->Password   = 'tmci lyzg vauy ibwd';            // 2. <-- EDIT THIS: Your 16-character App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;    // Enable explicit TLS encryption
    $mail->Port       = 587;                              // TCP port to connect to

    // --- Recipients ---
    $mail->setFrom('YOUR_NEW_GMAIL@gmail.com', 'JHCSC Library'); // The "From" address (use your new Gmail)
    $mail->addAddress('jancarlorabe9@gmail.com');     // 3. <-- EDIT THIS: Your *personal* email to receive the test

    // --- Content ---
    $mail->isHTML(true);                                  // Set email format to HTML
    $mail->Subject = 'PHPMailer Test Successful!';
    $mail->Body    = '<h1>Hello!</h1><p>This is a test email from your new PHPMailer script. If you received this, it works!</p>';
    $mail->AltBody = 'This is a test email from your new PHPMailer script. If you received this, it works!';

    $mail->send();
    echo 'Message has been sent';

} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>