<?php
// admin_api.php
session_start();

// Security Check: Ensure a user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied. Please log in.']));
}

header('Content-Type: application/json');

// --- Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection Failed']));
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'getAdminData') {
        // Fetch data for the currently logged-in admin
        $stmt = $conn->prepare("SELECT firstname, surname, email FROM librarian WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $adminData = $result->fetch_assoc();

        if ($adminData) {
            echo json_encode(['success' => true, 'data' => $adminData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Admin user not found.']);
        }
        $stmt->close();
    }

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'updateAdminData') {
        $firstname = $data['firstname'] ?? '';
        $surname = $data['surname'] ?? '';
        $password = $data['password'] ?? '';

        if (!empty($password)) {
            // If a new password was provided, hash it for security
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE librarian SET firstname = ?, surname = ?, password = ? WHERE id = ?");
            $stmt->bind_param("sssi", $firstname, $surname, $hashedPassword, $userId);
        } else {
            // If the password field was empty, do not update it
            $stmt = $conn->prepare("UPDATE librarian SET firstname = ?, surname = ? WHERE id = ?");
            $stmt->bind_param("ssi", $firstname, $surname, $userId);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Account updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update account.']);
        }
        $stmt->close();
    }
}

$conn->close();
?>