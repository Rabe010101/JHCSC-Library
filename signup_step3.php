<?php
session_start();

// 1. Protect this page
if (!isset($_SESSION['user_id'])) {
    // Not logged in, send them to the home page
    header('Location: Home.html');
    exit();
}

// 2. Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$message = "";

// 3. Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = $_POST['course'];
    $year = $_POST['year'];

    // 4. Update the user's record in the database
    $stmt = $conn->prepare("UPDATE users SET course = ?, year = ? WHERE id = ?");
    $stmt->bind_param("ssi", $course, $year, $user_id);

    if ($stmt->execute()) {
        // Success! Redirect to the main app
        header('Location: final/index.php');
        exit();
    } else {
        $message = "Error: Could not save your information. Please try again.";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile</title>
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
        <h1 class="text-2xl font-bold mb-4 text-center" style="color: #004d14;">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
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

            <button type="submit" class="w-full text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                Save and Continue
            </button>
        </form>
    </div>

</body>
</html>