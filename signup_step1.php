<?php
session_start();

// This page is for Name
$_SESSION['signup_step'] = 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['signup_firstname'] = $_POST['firstname'];
    $_SESSION['signup_surname'] = $_POST['surname'];
    
    // Go to the next step
    header('Location: signup_step2.php');
    exit();
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
        background-attachment: fixed; /* Keeps the background still */
      }
      /* This adds a slight dark overlay to make the white box pop */
      body::before {
        content: "";
        position: fixed;
        top: 0; left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.3); /* Adjust 0.3 to make it darker or lighter */
        z-index: -1;
      }
      /* We need to adjust the card to sit on top */
      .bg-white {
        position: relative;
        z-index: 1;
      }
    </style>

</head>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center" style="color: #004d14;" >Create Your Account</h1>
        <h2 class="text-lg font-medium mb-4 text-green-700">What's your name?</h2>
        
        <form method="POST" action="signup_step1.php">
            <div class="mb-5">
                <label for="firstname" class="block mb-2 text-sm font-medium text-gray-900">First Name</label>
                <input type="text" id="firstname" name="firstname" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
            </div>
            <div class="mb-8">
                <label for="surname" class="block mb-2 text-sm font-medium text-gray-900">Surname</label>
                <input type="text" id="surname" name="surname" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
            </div>
            <button type="submit" class="w-full text-white bg-green-700 hover:bg-green-800 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                Next
            </button>
        </form>
    </div>
</body>
</html>