<?php
session_start();

// Security: Must have an email in session
if (!isset($_SESSION['signup_email']) || !isset($_SESSION['signup_otp'])) {
    header('Location: signup_step1.php');
    exit();
}

$message = "";
$user_email = $_SESSION['signup_email'];

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get the code from the hidden input (populated by JS)
    $submitted_code = $_POST['verification_code'];
    $stored_code = $_SESSION['signup_otp'];

    // Check Code
    if ($submitted_code == $stored_code) {
        // SUCCESS: Mark as verified in session
        $_SESSION['is_email_verified'] = true;
        
        // Clean up OTP session data
        unset($_SESSION['signup_otp']);

        // Move to Details Step (Step 2)
        header('Location: signup_step2.php');
        exit();
    } else {
        $message = "Invalid code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Account</title>
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
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-4 text-center" style="color: #004d14;">Check Your Email</h1>
        <p class="text-gray-600 mb-6 text-center">
            We've sent a 5-digit verification code to <br>
            <b><?php echo htmlspecialchars($user_email); ?></b>
        </p>

        <?php if ($message): ?>
            <p class="bg-red-100 text-red-700 p-3 rounded-md mb-4 text-center"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <form method="POST" action="signup_verify.php">
            <div class="mb-5">
                <label class="block mb-4 text-sm font-medium text-gray-900 text-center">Enter 5-Digit Code</label>
                
                <div id="otp-container" class="flex justify-center space-x-2">
                    
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="0"
                           class="otp-input w-12 h-14 text-center text-3xl font-semibold 
                                  border-0 border-b-2 border-gray-400 
                                  focus:outline-none focus:border-green-700 focus:ring-0
                                  bg-transparent">
                    
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="1"
                           class="otp-input w-12 h-14 text-center text-3xl font-semibold 
                                  border-0 border-b-2 border-gray-400 
                                  focus:outline-none focus:border-green-700 focus:ring-0
                                  bg-transparent">

                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="2"
                           class="otp-input w-12 h-14 text-center text-3xl font-semibold 
                                  border-0 border-b-2 border-gray-400 
                                  focus:outline-none focus:border-green-700 focus:ring-0
                                  bg-transparent">

                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="3"
                           class="otp-input w-12 h-14 text-center text-3xl font-semibold 
                                  border-0 border-b-2 border-gray-400 
                                  focus:outline-none focus:border-green-700 focus:ring-0
                                  bg-transparent">

                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="4"
                           class="otp-input w-12 h-14 text-center text-3xl font-semibold 
                                  border-0 border-b-2 border-gray-400 
                                  focus:outline-none focus:border-green-700 focus:ring-0
                                  bg-transparent">
                </div>
                
                <input type="hidden" name="verification_code" id="verification_code">
            </div>
            
            <button type="submit" class="w-full text-white bg-green-700 hover:bg-green-800 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                Verify and Continue
            </button>
        </form>

        <p class="text-sm text-gray-600 text-center mt-4">
            Didn't get a code? 
            <a href="signup_step1.php" class="font-medium text-green-700 hover:underline">Try again</a>
        </p>
    </div>

<script>
// RESTORED: The Logic for the 5-box input
document.addEventListener('DOMContentLoaded', () => {
    const otpContainer = document.getElementById('otp-container');
    const otpInputs = Array.from(otpContainer.querySelectorAll('.otp-input'));
    const hiddenInput = document.getElementById('verification_code');

    // Function to combine all inputs into the hidden field
    function combineInputs() {
        let code = '';
        otpInputs.forEach(input => {
            code += input.value;
        });
        hiddenInput.value = code;
    }

    otpContainer.addEventListener('input', (e) => {
        const target = e.target;
        const index = parseInt(target.dataset.index, 10);
        
        // Only allow one digit
        if (target.value.length > 1) {
            target.value = target.value.slice(0, 1);
        }

        // Only allow digits
        if (!/^\d*$/.test(target.value)) {
            target.value = '';
            return;
        }

        // If a digit is entered, move to the next input
        if (target.value !== '' && index < otpInputs.length - 1) {
            otpInputs[index + 1].focus();
        }
        
        combineInputs();
    });

    otpContainer.addEventListener('keydown', (e) => {
        const target = e.target;
        const index = parseInt(target.dataset.index, 10);

        // Move to the previous input on backspace if current is empty
        if (e.key === 'Backspace' && target.value === '' && index > 0) {
            otpInputs[index - 1].focus();
        }
        setTimeout(combineInputs, 0);
    });
    
    // Handle Paste
    otpContainer.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasteData = (e.clipboardData || window.clipboardData).getData('text').slice(0, 5);
        
        pasteData.split('').forEach((char, i) => {
            if (otpInputs[i] && /^\d$/.test(char)) {
                otpInputs[i].value = char;
            }
        });
        
        // Focus on the last filled input
        const lastFilledIndex = Math.min(pasteData.length, otpInputs.length) - 1;
        if (lastFilledIndex >= 0) {
            otpInputs[lastFilledIndex].focus();
        }
        
        combineInputs();
    });
});
</script>
</body>
</html>