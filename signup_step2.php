<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

session_start();

// --- Security Check: Must come from Step 1 ---
if (!isset($_SESSION['signup_step']) || $_SESSION['signup_step'] != 1) {
    header('Location: signup_step1.php');
    exit();
}

$message = "";

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Get all data
    $firstname = $_SESSION['signup_firstname'];
    $surname = $_SESSION['signup_surname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 2. Validate data
    if ($password !== $confirm_password) {
        $message = "Passwords do not match!";
    } else {
        
        // 3. Connect to database
        $servername = "localhost";
        $username_db = "root"; // Renamed
        $password_db = ""; // DB password
        $dbname = "jhcsc_library";

        $conn = new mysqli($servername, $username_db, $password_db, $dbname);
        if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

        // 4. Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "An account with this email already exists.";
        } else {
            // 5. Create the new user (but unverified)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_type = 'student';
            $course = 'N/A';
            $year = 'N/A';
            
            // === NEW: Generate a 5-digit code ===
            $verification_code = rand(10000, 99999); 
            $is_verified = 0; // Not verified yet

            // We will store the code in the 'verification_token' column
            $insert_stmt = $conn->prepare("INSERT INTO users (firstname, surname, email, password, user_type, course, year, verification_token, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssssssssi", $firstname, $surname, $email, $hashed_password, $user_type, $course, $year, $verification_code, $is_verified);

            if ($insert_stmt->execute()) {
                
                // 6. SEND THE VERIFICATION EMAIL
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
                    $mail->addAddress($email, $firstname . ' ' . $surname);     

                    // === NEW: Email content ===
                    $mail->isHTML(true);
                    $mail->Subject = 'Your JHCSC Library Verification Code';
                    $mail->Body    = "<h1>Welcome to the JHCSC Library!</h1>
                                      <p>Hi " . htmlspecialchars($firstname) . ",</p>
                                      <p>Your 5-digit verification code is:</p>
                                      <h2 style='font-size: 28px; letter-spacing: 5px; background: #f4f4f4; padding: 10px 20px; display: inline-block;'>
                                        <b>" . $verification_code . "</b>
                                      </h2>
                                      <p>This code will expire in 10 minutes. If you did not sign up, please ignore this email.</p>";
                    $mail->AltBody = "Your 5-digit verification code is: " . $verification_code;

                    $mail->send();
                    
                    // 7. === NEW: Redirect to the verification page ===
                    
                    // Store the email in the session so the next page knows who is verifying
                    $_SESSION['signup_verify_email'] = $email;
                    
                    // Clear other session data
                    unset($_SESSION['signup_step']);
                    unset($_SESSION['signup_firstname']);
                    unset($_SESSION['signup_surname']);
                    
                    // Redirect to the new page
                    header('Location: signup_verify.php');
                    exit();

                } catch (Exception $e) {
                    $message = "Account created, but could not send verification email. Mailer Error: {$mail->ErrorInfo}";
                }
                
            } else {
                $message = "Error: Could not create account.";
            }
            $insert_stmt->close();
        }
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - Step 2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <style>
      /* ... (your background and password toggle styles) ... */
      body {
        background-image: url('images/red.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed; 
      }
      body::before {
        content: "";
        position: fixed;
        top: 0; left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.3);
        z-index: -1;
      }
      .bg-white {
        position: relative;
        z-index: 1;
      }
      .password-wrapper { position: relative; width: 100%; }
      .password-wrapper input { padding-right: 40px !important; }
      .password-toggle-icon { position: absolute; top: 50%; right: 15px; transform: translateY(-50%); cursor: pointer; color: #888; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center" style="color: #004d14;">Create Your Account</h1>
        
        <h2 class="text-lg font-medium mb-4 text-green-700">Login Details</h2>

        <?php if ($message): ?>
            <p class="bg-red-100 text-red-700 p-3 rounded-md mb-4"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <form method="POST" action="signup_step2.php">
            <div class="mb-5">
                <label for="email" class="block mb-2 text-sm font-medium text-gray-900">Email Address</label>
                <input type="email" id="email" name="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
            </div>
            
            <div class="mb-5">
                <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Create Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    <i class="fas fa-eye password-toggle-icon" id="toggleNewPassword"></i>
                </div>
            </div>
            
            <div class="mb-8">
                <label for="confirm_password" class="block mb-2 text-sm font-medium text-gray-900">Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    <i class="fas fa-eye password-toggle-icon" id="toggleConfirmPassword"></i>
                </div>
            </div>
            
            <button type="submit" class="w-full text-white bg-green-700 hover:bg-green-800 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                Create Account
            </button>
        </form>
    </div>

    <script>
    function setupPasswordToggle(inputId, toggleId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(toggleId);

        if (passwordInput && toggleIcon) {
            toggleIcon.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
    }
    setupPasswordToggle('password', 'toggleNewPassword');
    setupPasswordToggle('confirm_password', 'toggleConfirmPassword');
    </script>

</body>
</html>