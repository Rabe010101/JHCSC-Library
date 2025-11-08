<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
// --- THIS PATH IS NOW FIXED ---
require '../vendor/autoload.php';

session_start();

// --- Anti-Caching Headers ---
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header('Content-Type: application/json');

// --- ADMIN SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied. Administrator privileges required.']));
}

// --- Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection Failed']));
}

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
}

switch ($action) {
    // --- ACTION: GET ALL ISSUED BOOKS (Not Overdue) ---
    case 'getIssuedBooks':
        $search = $_GET['search'] ?? '';
        
        // MODIFIED: Added ib.otp_expires
        $sql = "SELECT 
                    ib.id, 
                    ib.transaction_number,
                    ib.issue_date,
                    ib.due_date,
                    ib.otp_expires, 
                    u.id AS user_id,
                    CONCAT(u.firstname, ' ', u.surname) AS name,
                    u.email,
                    b.title AS book_title,
                    b.id AS book_id
                FROM issued_books ib
                JOIN users u ON ib.user_id = u.id
                JOIN books b ON ib.book_id = b.id
                WHERE ib.status = 'Issued' AND ib.due_date >= CURDATE()"; 

        if (!empty($search)) {
            $sql .= " AND (u.firstname LIKE ? OR u.surname LIKE ? OR u.email LIKE ? OR b.title LIKE ? OR ib.transaction_number LIKE ?)";
            $searchTerm = "%" . $search . "%";
        }
        $sql .= " ORDER BY ib.issue_date DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($search)) {
            $stmt->bind_param("sssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $issued_books = [];
        while($row = $result->fetch_assoc()) {
            $issued_books[] = $row;
        }
        echo json_encode($issued_books);
        $stmt->close();
        break;

    // --- NEW ACTION: Send OTP for Return ---
    case 'sendReturnOTP':
        $issuedId = $data['issuedId'] ?? 0;
        if ($issuedId === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid Issued ID.']);
            break;
        }

        $otp = rand(10000, 99999); // 5-digit OTP
        $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Store hashed OTP in the issued_books table
        $stmt_update = $conn->prepare("UPDATE issued_books SET otp_code = ?, otp_expires = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $hashed_otp, $expires, $issuedId);
        $stmt_update->execute();
        $stmt_update->close();

        // Get user email and book title for the email
        $stmt_get = $conn->prepare("SELECT u.email, u.firstname, b.title 
                                    FROM issued_books ib
                                    JOIN users u ON ib.user_id = u.id
                                    JOIN books b ON ib.book_id = b.id
                                    WHERE ib.id = ?");
        $stmt_get->bind_param("i", $issuedId);
        $stmt_get->execute();
        $info = $stmt_get->get_result()->fetch_assoc();
        
        if (!$info) {
             echo json_encode(['success' => false, 'message' => 'Could not find issued book details.']);
             break;
        }

        // Send the OTP email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jhcsc.e.lib@gmail.com';
            $mail->Password   = 'tmci lyzg vauy ibwd';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('jhcsc.e.lib@gmail.com', 'JHCSC Library');
            $mail->addAddress($info['email'], $info['firstname']);

            $mail->isHTML(true);
            $mail->Subject = 'Your Book Return Verification Code';
            $mail->Body    = "<h1>Confirm Your Book Return</h1>
                              <p>Hi " . htmlspecialchars($info['firstname']) . ",</p>
                              <p>To confirm you are returning the book: <b>" . htmlspecialchars($info['title']) . "</b>, please provide the following 5-digit code to the librarian:</p>
                              <h2 style='font-size: 28px; letter-spacing: 5px;'><b>" . $otp . "</b></h2>
                              <p>This code is valid for 5 minutes.</p>";
            $mail->AltBody = "Your 5-digit return code is: " . $otp;

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'OTP sent to user.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "Could not send OTP. Mailer Error: {$mail->ErrorInfo}"]);
        }
        break;

    // --- REPLACES 'markAsReturned' ---
    case 'verifyAndReturnBook':
        $issuedId = $data['issuedId'] ?? 0;
        $submittedOTP = $data['otp'] ?? '';

        if ($issuedId === 0 || empty($submittedOTP)) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID or OTP.']);
            break;
        }

        // Get the stored OTP
        $stmt_get = $conn->prepare("SELECT * FROM issued_books WHERE id = ?");
        $stmt_get->bind_param("i", $issuedId);
        $stmt_get->execute();
        $issued_book = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if (!$issued_book) {
            echo json_encode(['success' => false, 'message' => 'Issued book not found.']);
            break;
        }

        // Check if OTP is expired
        if (strtotime($issued_book['otp_expires']) < time()) {
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please send a new one.']);
            break;
        }

        // Check if OTP matches
        if (password_verify($submittedOTP, $issued_book['otp_code'])) {
            // SUCCESS! Mark the book as returned
            $conn->begin_transaction();
            try {
                // Update status to 'Returned' and set the actual return_date
                $stmt_update = $conn->prepare("UPDATE issued_books SET status = 'Returned', return_date = CURDATE(), otp_code = NULL, otp_expires = NULL WHERE id = ?");
                $stmt_update->bind_param("i", $issuedId);
                $stmt_update->execute();

                // Add the book copy back to inventory
                $stmt_book = $conn->prepare("UPDATE books SET copies = copies + 1 WHERE id = ?");
                $stmt_book->bind_param("i", $issued_book['book_id']);
                $stmt_book->execute();

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Book marked as returned.']);

            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to update book status. Database error.']);
            }
        } else {
            // OTP is incorrect
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        }
        break;

    default:
        // This is what was causing the 'undefined' error
        echo json_encode(['error' => 'Invalid action specified.']);
        break;
}

$conn->close();
?>