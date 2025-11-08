<?php
// resend_verification_api.php

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

session_start();
header('Content-Type: application/json');

// --- 1. Get Email from JavaScript ---
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email address is required.']);
    exit();
}

// --- 2. Database Connection ---
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "jhcsc_library";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) { 
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit(); 
}

// --- 3. Find the user ---
// We are looking for a user that matches the email AND is_verified = 0
$stmt = $conn->prepare("SELECT id, firstname FROM users WHERE email = ? AND is_verified = 0");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    // --- 4. User Found ---
    $user = $result->fetch_assoc();
    $firstname = $user['firstname'];

    // --- NEW: Set the session variable for the verify page ---
    // This authorizes the user to access signup_verify.php
    $_SESSION['signup_verify_email'] = $email;

    // Generate a new 5-digit code
    $new_code = rand(10000, 99999);

    // Update the database with the new code
    $update_stmt = $conn->prepare("UPDATE users SET verification_token = ? WHERE email = ?");
    $update_stmt->bind_param("ss", $new_code, $email);
    $update_stmt->execute();
    $update_stmt->close();

    // --- 5. Send the new code via email ---
    $mail = new PHPMailer(true);
    try {
        // --- Server settings (Copied from your signup_step2.php) ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jhcsc.e.lib@gmail.com'; // Your app email
        $mail->Password   = 'tmci lyzg vauy ibwd'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // --- Recipients ---
        $mail->setFrom('jhcsc.e.lib@gmail.com', 'JHCSC Library');
        $mail->addAddress($email, $firstname);

        // --- Content ---
        $mail->isHTML(true);
        $mail->Subject = 'Your New JHCSC Library Verification Code';
        $mail->Body    = "<h1>Here is your new code</h1>
                          <p>Hi " . htmlspecialchars($firstname) . ",</p>
                          <p>Your new 5-digit verification code is:</p>
                          <h2 style='font-size: 28px; letter-spacing: 5px; background: #f4f4f4; padding: 10px 20px; display: inline-block;'>
                            <b>" . $new_code . "</b>
                          </h2>";
        $mail->AltBody = "Your new 5-digit verification code is: " . $new_code;

        $mail->send();
        
        // --- NEW: Send a success: true response ---
        echo json_encode([
            'success' => true, 
            'message' => 'A new verification code has been sent to ' . htmlspecialchars($email) . '. Please check your inbox.'
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => "Could not send new code. Mailer Error: {$mail->ErrorInfo}"]);
    }

} else {
    // --- User Not Found or Already Verified ---
    echo json_encode(['success' => false, 'message' => 'No unverified account was found with this email, or the account is already active.']);
}

$stmt->close();
$conn->close();
?>