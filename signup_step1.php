<?php
// signup_step1.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

session_start();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    // 1. Connect to Database
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "jhcsc_library";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    // 2. Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "This email is already registered. Please login instead.";
    } else {
        // 3. Generate Code & Save to SESSION (Not DB yet)
        $verification_code = rand(10000, 99999);
        
        $_SESSION['signup_email'] = $email;
        $_SESSION['signup_otp'] = $verification_code;

        // 4. Send Email
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jhcsc.e.lib@gmail.com'; // Your Credentials
            $mail->Password   = 'tmci lyzg vauy ibwd';   // Your App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('jhcsc.e.lib@gmail.com', 'JHCSC Library');
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your Verification Code';
            $mail->Body    = "<h1>Welcome!</h1>
                              <p>You are creating an account at JHCSC Library.</p>
                              <p>Your verification code is:</p>
                              <h2 style='background: #eee; padding: 10px; display: inline-block;'>$verification_code</h2>";

            $mail->send();

            // Redirect to Verify Page
            header('Location: signup_verify.php');
            exit();

        } catch (Exception $e) {
            $message = "Could not send email. Error: {$mail->ErrorInfo}";
        }
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - Step 1</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
      body {
        background-image: url('images/red.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed; 
      }
      body::before {
        content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0, 0, 0, 0.3); z-index: -1;
      }
      .bg-white { position: relative; z-index: 1; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center" style="color: #004d14;">Create Account</h1>
        <h2 class="text-lg font-medium mb-4 text-green-700">First, verify your email</h2>
        
        <?php if ($message): ?>
            <p class="bg-red-100 text-red-700 p-3 rounded-md mb-4 text-sm"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST" action="signup_step1.php">
            <div class="mb-5">
                <label for="email" class="block mb-2 text-sm font-medium text-gray-900">Email Address</label>
                <input type="email" id="email" name="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-green-500 focus:border-green-500 block w-full p-2.5" required>
            </div>
            <button type="submit" class="w-full text-white bg-green-700 hover:bg-green-800 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                Send Verification Code
            </button>
        </form>
        <p class="text-sm text-center mt-4 text-gray-600">Already have an account? <a href="Home.html" class="text-green-700 underline">Login</a></p>
    </div>
</body>
</html>