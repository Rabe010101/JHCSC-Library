<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require '../vendor/autoload.php';

session_start();

// --- NEW: Add Anti-Caching Headers ---
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
// --- End of New Headers ---

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
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
}

switch ($action) {
    case 'getReservations':
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';

        // This SQL query is correct and includes otp_expires
        $sql = "SELECT 
                    r.id, r.user_id, r.transaction_number, r.reservation_date, r.due_date, r.status, r.otp_expires,
                    CONCAT(u.firstname, ' ', u.surname) AS name, u.email, b.title AS book_title
                FROM reservations r
                JOIN users u ON r.user_id = u.id
                JOIN books b ON r.book_id = b.id
                WHERE r.status != 'Claimed'";

        $conditions = []; $params = []; $types = '';

        if (!empty($search)) {
            $conditions[] = "(u.firstname LIKE ? OR u.surname LIKE ? OR u.email LIKE ? OR b.title LIKE ?)";
            $searchTerm = "%" . $search . "%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= 'ssss';
        }

        if (!empty($status)) {
            $conditions[] = "r.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY r.reservation_date DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $reservations = [];
        while($row = $result->fetch_assoc()) {
            $reservations[] = $row;
        }
        echo json_encode($reservations);
        $stmt->close();
        break;

    // --- ACTION 1: Generate and Send OTP ---
    case 'sendClaimOTP':
        $reservationId = $data['reservationId'] ?? 0;
        if ($reservationId === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid Reservation ID.']);
            break;
        }

        // Generate 5-digit OTP
        $otp = rand(10000, 99999);
        $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes')); // OTP is valid for 5 minutes

        // Store hashed OTP in the database
        $stmt_update = $conn->prepare("UPDATE reservations SET otp_code = ?, otp_expires = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $hashed_otp, $expires, $reservationId);
        $stmt_update->execute();
        $stmt_update->close();

        // Get user email and book title for the email
        $stmt_get = $conn->prepare("SELECT u.email, u.firstname, b.title 
                                    FROM reservations r
                                    JOIN users u ON r.user_id = u.id
                                    JOIN books b ON r.book_id = b.id
                                    WHERE r.id = ?");
        $stmt_get->bind_param("i", $reservationId);
        $stmt_get->execute();
        $result = $stmt_get->get_result();
        $info = $result->fetch_assoc();
        
        if (!$info) {
             echo json_encode(['success' => false, 'message' => 'Could not find reservation details.']);
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
            $mail->Subject = 'Your Book Pickup Verification Code';
            $mail->Body    = "<h1>Confirm Your Book Pickup</h1>
                              <p>Hi " . htmlspecialchars($info['firstname']) . ",</p>
                              <p>To confirm you are picking up the book: <b>" . htmlspecialchars($info['title']) . "</b>, please provide the following 5-digit code to the librarian:</p>
                              <h2 style='font-size: 28px; letter-spacing: 5px;'><b>" . $otp . "</b></h2>
                              <p>This code is valid for 5 minutes.</p>";
            $mail->AltBody = "Your 5-digit pickup code is: " . $otp;

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'OTP sent to user.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "Could not send OTP. Mailer Error: {$mail->ErrorInfo}"]);
        }
        break;

    // --- ACTION 2: Verify OTP and Issue Book ---
    case 'verifyAndClaimReservation':
        $reservationId = $data['reservationId'] ?? 0;
        $submittedOTP = $data['otp'] ?? '';

        if ($reservationId === 0 || empty($submittedOTP)) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID or OTP.']);
            break;
        }

        // Get the stored OTP from the database
        $stmt_get = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt_get->bind_param("i", $reservationId);
        $stmt_get->execute();
        $reservation = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if (!$reservation) {
            echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
            break;
        }

        // Check if OTP is expired
        if (strtotime($reservation['otp_expires']) < time()) {
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please send a new one.']);
            break;
        }

        // Check if OTP matches
        if (password_verify($submittedOTP, $reservation['otp_code'])) {
            // SUCCESS!
            $conn->begin_transaction();
            try {
                // Insert into issued_books
                $stmt_insert = $conn->prepare("INSERT INTO issued_books (user_id, book_id, transaction_number, due_date, status) VALUES (?, ?, ?, ?, 'Issued')");
                $stmt_insert->bind_param("iiss", $reservation['user_id'], $reservation['book_id'], $reservation['transaction_number'], $reservation['due_date']);
                $stmt_insert->execute();
                
                // Delete from reservations
                $stmt_delete = $conn->prepare("DELETE FROM reservations WHERE id = ?");
                $stmt_delete->bind_param("i", $reservationId);
                $stmt_delete->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Book has been issued successfully.']);

            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to issue the book. Database error.']);
            }

        } else {
            // OTP is incorrect
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        }
        break;

    // --- Other actions (unchanged) ---
    case 'adminCancelReservation':
        $reservationId = $data['reservationId'] ?? 0;
        if ($reservationId > 0) {
            $conn->begin_transaction();
            try {
                $stmt_get = $conn->prepare("SELECT book_id FROM reservations WHERE id = ? AND status = 'Pending Pickup'");
                $stmt_get->bind_param("i", $reservationId);
                $stmt_get->execute();
                $result = $stmt_get->get_result();
                if ($result->num_rows > 0) {
                    $book = $result->fetch_assoc();
                    $bookId = $book['book_id'];
                    $stmt_update = $conn->prepare("UPDATE reservations SET status = 'Cancelled' WHERE id = ?");
                    $stmt_update->bind_param("i", $reservationId);
                    $stmt_update->execute();
                    $stmt_book = $conn->prepare("UPDATE books SET copies = copies + 1 WHERE id = ?");
                    $stmt_book->bind_param("i", $bookId);
                    $stmt_book->execute();
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Reservation has been cancelled.']);
                } else {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Reservation could not be cancelled. It may not be pending.']);
                }
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Reservation ID.']);
        }
        break;

    case 'deleteCancelledReservation':
         $reservationId = $data['reservationId'] ?? 0;
        if ($reservationId > 0) {
            $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ? AND status = 'Cancelled'");
            $stmt->bind_param("i", $reservationId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Cancelled reservation has been deleted.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete reservation.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Reservation ID.']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action specified.']);
        break;
}

$conn->close();
?>