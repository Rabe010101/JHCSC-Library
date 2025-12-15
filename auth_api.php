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

    // --- CHANGE 1: STOP HASHING (Save as plain text) ---
    $plain_password = $password; 

    // Insert new user into the database
    $stmt = $conn->prepare("INSERT INTO users (firstname, surname, course, year, email, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $firstname, $surname, $course, $year, $email, $plain_password);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created successfully! Please log in.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: Could not create account.']);
    }
    $stmt->close();

} elseif ($action === 'login') {
    // --- LOGIN LOGIC ---
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // 1. Check Librarian Table
    $stmt = $conn->prepare("SELECT id, password FROM librarian WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($librarianId, $stored_password);
        $stmt->fetch();

        // --- CHANGE 2: COMPARE PLAIN TEXT ---
        if ($password === $stored_password) {
            $_SESSION['user_id'] = $librarianId;
            $_SESSION['role'] = 'admin'; 
            echo json_encode(['success' => true, 'role' => 'admin']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
        $stmt->close();

    } else {
        // 2. Check Users Table
        $stmt->close(); 
        $stmt = $conn->prepare("SELECT id, password, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($userId, $stored_password, $is_verified);
            $stmt->fetch();

            // --- CHANGE 3: COMPARE PLAIN TEXT ---
            if ($password === $stored_password) {
                
                if ($is_verified == 1) {
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['role'] = 'user'; 
                    echo json_encode(['success' => true, 'role' => 'user']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Your account is not verified.']);
                }
                
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
        $stmt->close();
    }
}

$conn->close();
?>