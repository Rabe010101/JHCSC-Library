<?php
// signup_step3.php
session_start();

// Security: Check if we have the password from Step 2 (Use password as the "Flag" that Step 2 is done)
if (!isset($_SESSION['signup_password'])) {
    header('Location: signup_step2.php');
    exit();
}

$firstname = $_SESSION['signup_firstname'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = $_POST['course'];
    $year = $_POST['year'];
    
    // Retrieve ALL other data from Session
    $email = $_SESSION['signup_email'];
    $surname = $_SESSION['signup_surname'];
    $password = $_SESSION['signup_password']; // Plain text for demo

    // Connect to DB
    $conn = new mysqli("localhost", "root", "", "jhcsc_library");
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    // --- FINAL INSERTION ---
    // We are inserting a COMPLETED account now.
    $user_type = 'student';
    $is_verified = 1;

    $stmt = $conn->prepare("INSERT INTO users (firstname, surname, email, password, user_type, course, year, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssi", $firstname, $surname, $email, $password, $user_type, $course, $year, $is_verified);

    if ($stmt->execute()) {
        // Success! Log them in.
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['user_name'] = $firstname . ' ' . $surname;
        $_SESSION['user_type'] = $user_type;

        // Cleanup Session
        unset($_SESSION['signup_email']);
        unset($_SESSION['signup_firstname']);
        unset($_SESSION['signup_surname']);
        unset($_SESSION['signup_password']);
        unset($_SESSION['is_email_verified']);
        unset($_SESSION['signup_google_firstname']);
        unset($_SESSION['signup_google_surname']);

        // Redirect to App
        header('Location: final/index.php');
        exit();
    } else {
        $message = "Error: Could not save account. Please try again.";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Your Profile</title>
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
        <h1 class="text-2xl font-bold mb-4 text-center" style="color: #004d14;">Welcome, <?php echo htmlspecialchars($firstname); ?>!</h1>
        <p class="text-gray-600 mb-6 text-center">Please complete your profile to continue.</p>

        <?php if ($message): ?>
            <p class="bg-red-100 text-red-700 p-3 rounded-md mb-4"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST" action="signup_step3.php">
            <div class="mb-5">
                <label for="course" class="block mb-2 text-sm font-medium text-gray-900">Program/Course</label>
                <select id="course" name="course" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    <option value="">Select Course</option>
                    <option value="BSIT">BSIT</option>
                    <option value="BSN">BSN</option>
                    <option value="BSTM">BSTM</option>
                    <option value="BSE">BSE</option>
                    <option value="BSED">BSED</option>
                    <option value="JD">JD</option>
                    <option value="BSHM">BSHM</option>
                </select>
            </div>
            <div class="mb-8">
                <label for="year" class="block mb-2 text-sm font-medium text-gray-900">Year Level</label>
                <select id="year" name="year" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    <option value="">Select Year Level</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>
            </div>

            <button type="submit" class="w-full text-white bg-green-700 hover:bg-green-800 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                Save and Create Account
            </button>
        </form>
    </div>

</body>
</html>