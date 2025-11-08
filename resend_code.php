<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

session_start();

// --- Security Check: Must come from Step 2 ---
if (!isset($_SESSION['signup_verify_email'])) {
    // Not on the right step, send back to step 1
    header('Location: signup_step1.php');
    exit();
}

$user_email = $_SESSION['signup_verify_email'];

// --- Database Connection ---
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "jhcsc_library";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// 1. Find the user
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_verified = 0");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
    $firstname = $user['firstname'];

    // 2. Generate a new code
    $new_code = rand(10000, 99999);

    // 3. Update the database with the new code
    $update_stmt = $conn->prepare("UPDATE users SET verification_token = ? WHERE email = ?");
    $update_stmt->bind_param("ss", $new_code, $user_email);
    $update_stmt->execute();
    $update_stmt->close();

    // 4. Send the new code via email
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

        // --- Recipients ---
        $mail->setFrom('YOUR_GMAIL_HERE@gmail.com', 'JHCSC Library'); // <-- EDIT THIS
        $mail->addAddress($user_email, $firstname);

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
        
        // 5. Set success message and redirect
        $_SESSION['resend_message'] = 'A new code has been sent to ' . htmlspecialchars($user_email);
        $_SESSION['resend_message_type'] = 'success';

    } catch (Exception $e) {
        // 5. Set error message and redirect
        $_SESSION['resend_message'] = "Could not send new code. Mailer Error: {$mail->ErrorInfo}";
        $_SESSION['resend_message_type'] = 'error';
    }

} else {
    // 5. Set general error message and redirect
    $_SESSION['resend_message'] = 'Could not find your account to resend code.';
    $_SESSION['resend_message_type'] = 'error';
}

$stmt->close();
$conn->close();

// 6. Go back to the verification page
header('Location: signup_verify.php');
exit();
?>