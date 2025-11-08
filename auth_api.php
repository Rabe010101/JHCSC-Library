<?php
session_start();

// --- 1. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

header('Content-Type: application/json');

// --- 2. Get Request Data from JavaScript ---
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// --- 3. API Logic ---

if ($action === 'signup') {
    // --- SIGN UP LOGIC ---
    // (This part is unchanged)
    $firstname = $data['firstname'] ?? '';
    $surname = $data['surname'] ?? '';
    $course = $data['course'] ?? '';
    $year = $data['year'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user into the database
    $stmt = $conn->prepare("INSERT INTO users (firstname, surname, course, year, email, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $firstname, $surname, $course, $year, $email, $hashed_password);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created successfully! Please log in.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: Could not create account.']);
    }
    $stmt->close();

} elseif ($action === 'login') {
    // --- MODIFIED LOGIN LOGIC ---
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // First, check if the email exists in the 'librarian' table
    $stmt = $conn->prepare("SELECT id, password FROM librarian WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Librarian account found (unchanged)
        $stmt->bind_result($librarianId, $hashed_password);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $librarianId;
            $_SESSION['role'] = 'admin'; 
            echo json_encode(['success' => true, 'role' => 'admin']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
        $stmt->close();

    } else {
        // If not a librarian, check the 'users' table
        $stmt->close(); 
        
        // --- MODIFICATION 1: Select the 'is_verified' column ---
        $stmt = $conn->prepare("SELECT id, password, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // User account found
            
            // --- MODIFICATION 2: Bind the new '$is_verified' variable ---
            $stmt->bind_result($userId, $hashed_password, $is_verified);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                // Password is correct, NOW check verification
                
                // --- MODIFICATION 3: Add the verification check ---
                if ($is_verified == 1) {
                    // SUCCESS: User is verified, log them in
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['role'] = 'user'; 
                    echo json_encode(['success' => true, 'role' => 'user']);
                } else {
                    // FAILURE: User is not verified
                    echo json_encode(['success' => false, 'message' => 'Your account is not verified. Please check your email for the verification code.']);
                }
                
            } else {
                // Password was incorrect
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
        } else {
            // Email was not found in either table
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
        $stmt->close();
    }
}

$conn->close();
?>