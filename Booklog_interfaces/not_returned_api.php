<?php
// not_returned_api.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied. Administrator privileges required.']));
}

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
    // --- ACTION: GET BOOKS THAT ARE OVERDUE ---
    case 'getNotReturnedBooks':
        $search = $_GET['search'] ?? '';
        
        // --- PAGINATION PARAMETERS ---
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;

        // Base SQL
        $baseSQL = " FROM issued_books ib
                     JOIN users u ON ib.user_id = u.id
                     JOIN books b ON ib.book_id = b.id
                     WHERE ib.status = 'Issued' AND ib.due_date < CURDATE()"; 

        $params = [];
        $types = '';

        if (!empty($search)) {
            $baseSQL .= " AND (u.firstname LIKE ? OR u.surname LIKE ? OR u.email LIKE ? OR b.title LIKE ? OR ib.transaction_number LIKE ?)";
            $searchTerm = "%" . $search . "%";
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $types .= 'sssss';
        }

        // --- QUERY 1: Get Total Count ---
        $countSql = "SELECT COUNT(*) as total" . $baseSQL;
        $stmtCount = $conn->prepare($countSql);
        if (!empty($params)) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $totalResult = $stmtCount->get_result()->fetch_assoc();
        $totalRecords = $totalResult['total'];
        $stmtCount->close();

        // --- QUERY 2: Get Data ---
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
                    b.id AS book_id"
                . $baseSQL
                . " ORDER BY ib.due_date ASC LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $not_returned_books = [];
        while($row = $result->fetch_assoc()) {
            $not_returned_books[] = $row;
        }

        // Return JSON with Pagination
        echo json_encode([
            'data' => $not_returned_books,
            'pagination' => [
                'totalRecords' => $totalRecords,
                'totalPages' => ceil($totalRecords / $limit),
                'currentPage' => $page,
                'limit' => $limit
            ]
        ]);

        $stmt->close();
        break;

    // --- ACTION: Send OTP for Return ---
    case 'sendReturnOTP':
        $issuedId = $data['issuedId'] ?? 0;
        if ($issuedId === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid Issued ID.']);
            break;
        }

        $otp = rand(10000, 99999); 
        $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $stmt_update = $conn->prepare("UPDATE issued_books SET otp_code = ?, otp_expires = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $hashed_otp, $expires, $issuedId);
        $stmt_update->execute();
        $stmt_update->close();

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

    // --- ACTION: Verify OTP and Move to History ---
    case 'verifyAndReturnBook':
        $issuedId = $data['issuedId'] ?? 0;
        $submittedOTP = $data['otp'] ?? '';

        if ($issuedId === 0 || empty($submittedOTP)) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID or OTP.']);
            break;
        }

        $stmt_get = $conn->prepare("SELECT * FROM issued_books WHERE id = ?");
        $stmt_get->bind_param("i", $issuedId);
        $stmt_get->execute();
        $issued_book = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if (!$issued_book) {
            echo json_encode(['success' => false, 'message' => 'Issued book not found.']);
            break;
        }

        if (strtotime($issued_book['otp_expires']) < time()) {
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please send a new one.']);
            break;
        }

        if (password_verify($submittedOTP, $issued_book['otp_code'])) {
            $conn->begin_transaction();
            try {
                // 1. Insert into returned_books table (Move to History)
                $stmt_archive = $conn->prepare("
                    INSERT INTO returned_books (user_id, book_id, transaction_number, issue_date, due_date, return_date)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt_archive->bind_param("iisss", 
                    $issued_book['user_id'], 
                    $issued_book['book_id'], 
                    $issued_book['transaction_number'], 
                    $issued_book['issue_date'], 
                    $issued_book['due_date']
                );
                $stmt_archive->execute();
                $stmt_archive->close();

                // 2. Delete from issued_books (Remove from Active)
                $stmt_delete = $conn->prepare("DELETE FROM issued_books WHERE id = ?");
                $stmt_delete->bind_param("i", $issuedId);
                $stmt_delete->execute();
                $stmt_delete->close();

                // 3. Update Inventory (Add copy back)
                $stmt_book = $conn->prepare("UPDATE books SET copies = copies + 1 WHERE id = ?");
                $stmt_book->bind_param("i", $issued_book['book_id']);
                $stmt_book->execute();
                $stmt_book->close();

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Book returned and moved to history.']);

            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action specified.']);
        break;
}

$conn->close();
?>