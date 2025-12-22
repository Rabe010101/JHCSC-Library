<?php
// signup_step2.php
session_start();

// Security: Must have verified email
if (!isset($_SESSION['is_email_verified']) || $_SESSION['is_email_verified'] !== true) {
    header('Location: signup_step1.php');
    exit();
}

$message = "";
// Pre-fill fields (for Google or previous input)
$pre_firstname = $_SESSION['signup_google_firstname'] ?? ($_SESSION['signup_firstname'] ?? '');
$pre_surname   = $_SESSION['signup_google_surname'] ?? ($_SESSION['signup_surname'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = $_POST['firstname'];
    $surname = $_POST['surname'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_SESSION['signup_email'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match!";
    } else {
        // 1. Connect to DB (Only to check for duplicates)
        $conn = new mysqli("localhost", "root", "", "jhcsc_library");
        if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

        // 2. Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "An account with this email already exists.";
        } else {
            // 3. SUCCESS! DO NOT SAVE TO DB YET.
            // Save details to SESSION to carry over to Step 3
            $_SESSION['signup_firstname'] = $firstname;
            $_SESSION['signup_surname'] = $surname;
            
            // DEMO MODE: Saving plain text password to session
            $_SESSION['signup_password'] = $password; 

            // 4. Redirect to Step 3
            header('Location: signup_step3.php');
            exit();
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
    <title>Account Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
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
      .password-wrapper { position: relative; width: 100%; }
      .password-wrapper input { padding-right: 40px !important; }
      .password-toggle-icon { position: absolute; top: 50%; right: 15px; transform: translateY(-50%); cursor: pointer; color: #888; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center" style="color: #004d14;">Setup Profile</h1>
        <h2 class="text-lg font-medium mb-4 text-green-700">Account Details</h2>

        <?php if ($message): ?>
            <p class="bg-red-100 text-red-700 p-3 rounded-md mb-4"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST" action="signup_step2.php">
            <div class="mb-4">
                <label class="block mb-1 text-sm font-medium">First Name</label>
                <input type="text" name="firstname" value="<?php echo htmlspecialchars($pre_firstname); ?>" class="bg-gray-50 border border-gray-300 rounded-lg block w-full p-2.5" required>
            </div>
            <div class="mb-4">
                <label class="block mb-1 text-sm font-medium">Surname</label>
                <input type="text" name="surname" value="<?php echo htmlspecialchars($pre_surname); ?>" class="bg-gray-50 border border-gray-300 rounded-lg block w-full p-2.5" required>
            </div>
            
            <div class="mb-4">
                <label class="block mb-1 text-sm font-medium">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="bg-gray-50 border border-gray-300 rounded-lg block w-full p-2.5" required>
                    <i class="fas fa-eye password-toggle-icon" onclick="togglePass('password', this)"></i>
                </div>
            </div>

            <div class="mb-6">
                <label class="block mb-1 text-sm font-medium">Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" class="bg-gray-50 border border-gray-300 rounded-lg block w-full p-2.5" required>
                    <i class="fas fa-eye password-toggle-icon" onclick="togglePass('confirm_password', this)"></i>
                </div>
            </div>

            <button type="submit" class="w-full text-white bg-green-700 hover:bg-green-800 font-medium rounded-lg text-sm px-5 py-2.5">Continue</button>
        </form>
    </div>

<script>
function togglePass(id, icon) {
    const input = document.getElementById(id);
    if(input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>