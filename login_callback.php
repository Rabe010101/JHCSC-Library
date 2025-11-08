<?php
session_start();

// --- CONFIGURATION ---
$CLIENT_ID = '714777541107-6bkqn1rvmc6ttgl0q6hs1le7l3dvibph.apps.googleusercontent.com';
$CLIENT_SECRET = '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';
$REDIRECT_URI = 'http://localhost/carlos_files/LMS_Admin_side/login_callback.php';
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";
$USER_APP_PATH = 'final/index.php'; 
// --- END OF CONFIGURATION ---

// --- DATABASE CONNECTION ---
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Stop if Google returns an error
if (isset($_GET['error'])) {
    die('Error from Google: ' . htmlspecialchars($_GET['error']));
}
if (!isset($_GET['code'])) {
    die('Error: No code from Google.');
}

// 1. Exchange the code for an access token
$token_url = 'https://oauth2.googleapis.com/token';
$post_data = [
    'code' => $_GET['code'],
    'client_id' => $CLIENT_ID,
    'client_secret' => $CLIENT_SECRET,
    'redirect_uri' => $REDIRECT_URI,
    'grant_type' => 'authorization_code'
];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response_json = curl_exec($ch);
curl_close($ch);
$response_data = json_decode($response_json, true);

if (empty($response_data['access_token'])) {
    die('Error: Could not get access token.');
}
$access_token = $response_data['access_token'];

// 2. Use the access token to get the user's profile info
$profile_url = 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=' . $access_token;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $profile_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$profile_json = curl_exec($ch);
curl_close($ch);
$profile_data = json_decode($profile_json, true);

if (empty($profile_data)) {
    die('Error: Could not fetch user profile.');
}

$email = $profile_data['email'];

// 3. Log in or Register the user in your database
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // --- USER EXISTS: Log them in (BUT CHECK VERIFICATION) ---
    $user = $result->fetch_assoc();
    
    // --- THIS IS THE NEW SECURITY CHECK ---
    if ($user['is_verified'] == 1) {
        // User IS verified, log them in
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['firstname'] . ' ' . $user['surname'];
        $_SESSION['user_type'] = $user['user_type'];

        // Check: Is their profile complete?
        if ($user['course'] == 'N/A' || $user['year'] == 'N/A' || $user['course'] == '' || $user['year'] == '') {
            header('Location: signup_step3.php');
            exit();
        } else {
            // Profile is complete, send to main app
            header('Location: ' . $USER_APP_PATH);
            exit();
        }
    } else {
        // User is NOT verified, block login
        // Redirect back to Home.html with our new error
        header('Location: Home.html#error=google_not_verified');
        exit();
    }
    // --- END OF NEW SECURITY CHECK ---

} else {
    // --- USER DOES NOT EXIST: DO NOT CREATE ACCOUNT ---
    // (This is the logic we added in our previous step)
    header('Location: Home.html#error=google_no_account');
    exit();
}
$stmt->close();
$conn->close();

?>