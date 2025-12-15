<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied. Please log in.']));
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed.']));
}

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];

// --- GET REQUESTS ---
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'getAllBooks') {
        $sql = "SELECT
                    b.id, b.title, b.author, b.publisher, b.year, b.cover, b.copies, b.category_id,
                    c.name AS category,
                    CASE WHEN b.copies > 0 THEN 'Available' ELSE 'Unavailable' END AS status
                FROM books b
                LEFT JOIN categories c ON b.category_id = c.id
                ORDER BY b.title ASC";
        $result = $conn->query($sql);
        $data = [];
        if ($result) {
            while($row = $result->fetch_assoc()) { $data[] = $row; }
        }
        echo json_encode($data);

    } elseif ($action === 'getCategories') {
        $sql = "SELECT * FROM categories ORDER BY name ASC";
        $result = $conn->query($sql);
        $data = [];
        if ($result) {
            while($row = $result->fetch_assoc()) { $data[] = $row; }
        }
        echo json_encode($data);

    } elseif ($action === 'getFavorites') {
        $stmt = $conn->prepare("SELECT b.*, CASE WHEN b.copies > 0 THEN 'Available' ELSE 'Unavailable' END AS status FROM books b JOIN user_favorites uf ON b.id = uf.book_id WHERE uf.user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while($row = $result->fetch_assoc()) { $data[] = $row; }
        echo json_encode($data);

    } elseif ($action === 'getReservations') {
        $stmt = $conn->prepare("SELECT r.*, b.id as book_id, b.title, b.author, b.cover FROM reservations r JOIN books b ON r.book_id = b.id WHERE r.user_id = ? ORDER BY r.reservation_date DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while($row = $result->fetch_assoc()) { $data[] = $row; }
        echo json_encode($data);

    } elseif ($action === 'getBorrowedBooks') {
        $stmt = $conn->prepare("
            SELECT 
                ib.issue_date AS borrow_date, ib.due_date, ib.return_date, ib.status, ib.transaction_number,
                b.title, b.author, b.cover
            FROM issued_books ib
            JOIN books b ON ib.book_id = b.id
            WHERE ib.user_id = ?
            ORDER BY ib.issue_date DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while($row = $result->fetch_assoc()) { $data[] = $row; }
        echo json_encode($data);

    } elseif ($action === 'getHistory') {
        $stmt = $conn->prepare("
            SELECT 
                rb.return_date, rb.transaction_number,
                b.title, b.author, b.cover
            FROM returned_books rb
            JOIN books b ON rb.book_id = b.id
            WHERE rb.user_id = ? AND rb.is_deleted_by_user = 0
            ORDER BY rb.return_date DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while($row = $result->fetch_assoc()) { $data[] = $row; }
        echo json_encode($data);
    
    } elseif ($action === 'getUserData') {
        // Fetch password so we can display it in the edit form
        $stmt = $conn->prepare("SELECT firstname, surname, course, year, email, password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
        }
    }

// --- POST REQUESTS ---
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'addToFavorites') {
        $bookId = $data['bookId'] ?? 0;
        $stmt = $conn->prepare("INSERT INTO user_favorites (user_id, book_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $bookId);
        $stmt->execute();
        echo json_encode(['success' => true]);

    } elseif ($action === 'removeFromFavorites') {
        $bookId = $data['bookId'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM user_favorites WHERE user_id = ? AND book_id = ?");
        $stmt->bind_param("ii", $userId, $bookId);
        $stmt->execute();
        echo json_encode(['success' => true]);
    
    } elseif ($action === 'clearHistory') {
        $stmt = $conn->prepare("UPDATE returned_books SET is_deleted_by_user = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $stmt->close();
            $cleanupSql = "DELETE FROM returned_books WHERE is_deleted_by_librarian = 1 AND is_deleted_by_user = 1";
            $conn->query($cleanupSql);

            echo json_encode(['success' => true, 'message' => 'History cleared.']);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Could not clear history.']);
        }

    } elseif ($action === 'createReservation') {
        $bookId = $data['bookId'] ?? 0;
        $dueDate = $data['dueDate'] ?? '';

        // --- CHECK: PREVENT DUPLICATE BOOK RESERVATIONS ---
        $stmt_duplicate = $conn->prepare("
            SELECT COUNT(*) FROM reservations WHERE user_id = ? AND book_id = ? AND (status = 'Pending Pickup' OR status = 'Active')
            UNION ALL
            SELECT COUNT(*) FROM issued_books WHERE user_id = ? AND book_id = ? AND status = 'Issued'
        ");
        $stmt_duplicate->bind_param("iiii", $userId, $bookId, $userId, $bookId);
        $stmt_duplicate->execute();
        $result_dup = $stmt_duplicate->get_result();
        $is_duplicate = false;
        while ($row = $result_dup->fetch_row()) {
            if ($row[0] > 0) {
                $is_duplicate = true;
            }
        }
        $stmt_duplicate->close();

        if ($is_duplicate) {
            echo json_encode(['success' => false, 'message' => 'You already have a copy of this book reserved or borrowed.']);
            exit();
        }
        
        // --- CHECK: OVERDUE BOOKS ---
        $stmt_overdue = $conn->prepare("SELECT COUNT(*) FROM issued_books WHERE user_id = ? AND status = 'Issued' AND due_date < CURDATE()");
        $stmt_overdue->bind_param("i", $userId);
        $stmt_overdue->execute();
        $stmt_overdue->bind_result($overdueCount);
        $stmt_overdue->fetch();
        $stmt_overdue->close();

        if ($overdueCount > 0) {
            echo json_encode(['success' => false, 'message' => 'You have overdue books! You cannot borrow any more books until they are returned.']);
            exit();
        }

        // --- CHECK: BORROWING LIMIT (Max 5) ---
        $stmt_issued = $conn->prepare("SELECT COUNT(*) FROM issued_books WHERE user_id = ? AND status = 'Issued'");
        $stmt_issued->bind_param("i", $userId);
        $stmt_issued->execute();
        $stmt_issued->bind_result($issuedCount);
        $stmt_issued->fetch();
        $stmt_issued->close();

        $stmt_reserved = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ? AND status = 'Pending Pickup'");
        $stmt_reserved->bind_param("i", $userId);
        $stmt_reserved->execute();
        $stmt_reserved->bind_result($reservedCount);
        $stmt_reserved->fetch();
        $stmt_reserved->close();

        if (($issuedCount + $reservedCount) >= 5) {
            echo json_encode(['success' => false, 'message' => 'You have reached the maximum limit of 5 books (Borrowed + Reserved).']);
            exit();
        }

        $transactionNumber = 'TXN-' . strtoupper(uniqid());
        
        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare("UPDATE books SET copies = copies - 1 WHERE id = ? AND copies > 0");
            $stmt1->bind_param("i", $bookId);
            $stmt1->execute();

            if ($stmt1->affected_rows > 0) {
                $stmt2 = $conn->prepare("INSERT INTO reservations (user_id, book_id, due_date, transaction_number) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("iiss", $userId, $bookId, $dueDate, $transactionNumber);
                $stmt2->execute();
                $conn->commit();
                echo json_encode(['success' => true, 'transactionNumber' => $transactionNumber]);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'No copies of this book are available.']);
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
        }
    
    } elseif ($action === 'cancelReservation') {
        $reservationId = $data['reservationId'] ?? 0;
        
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Cancelled' WHERE id = ? AND user_id = ? AND status = 'Pending Pickup'");
        $stmt->bind_param("ii", $reservationId, $userId);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt_get_book = $conn->prepare("SELECT book_id FROM reservations WHERE id = ?");
            $stmt_get_book->bind_param("i", $reservationId);
            $stmt_get_book->execute();
            $result = $stmt_get_book->get_result();
            if($result->num_rows > 0) {
                $book = $result->fetch_assoc();
                $stmt_add_copy = $conn->prepare("UPDATE books SET copies = copies + 1 WHERE id = ?");
                $stmt_add_copy->bind_param("i", $book['book_id']);
                $stmt_add_copy->execute();
            }
            echo json_encode(['success' => true, 'message' => 'Reservation cancelled and book copy restored.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'This reservation could not be cancelled.']);
        }

    } elseif ($action === 'deleteReservation') {
        $reservationId = $data['reservationId'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ? AND user_id = ? AND status = 'Cancelled'");
        $stmt->bind_param("ii", $reservationId, $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Reservation record has been deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not delete this reservation.']);
        }
        $stmt->close();

    } elseif ($action === 'deleteHistory') {
        $transactionNumber = $data['transactionNumber'] ?? '';
        $stmt = $conn->prepare("DELETE FROM issued_books WHERE transaction_number = ? AND user_id = ? AND status = 'Returned'");
        $stmt->bind_param("si", $transactionNumber, $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'History record has been deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not delete this record.']);
        }
        $stmt->close();
    
    } elseif ($action === 'updateUser') {
        // --- THIS IS THE FIX ---
        $updateData = $data['data'];
        $nameParts = explode(' ', $updateData['name'], 2);
        $firstname = $nameParts[0];
        $surname = $nameParts[1] ?? '';

        if (!empty($updateData['password'])) {
            // DEMO MODE: SAVE PLAIN TEXT PASSWORD (NO HASH)
            $plainPassword = $updateData['password'];
            $stmt = $conn->prepare("UPDATE users SET firstname = ?, surname = ?, course = ?, year = ?, email = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $firstname, $surname, $updateData['course'], $updateData['yearLevel'], $updateData['email'], $plainPassword, $userId);
        } else {
            // If password is empty, don't update it (keep existing)
            $stmt = $conn->prepare("UPDATE users SET firstname = ?, surname = ?, course = ?, year = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $firstname, $surname, $updateData['course'], $updateData['yearLevel'], $updateData['email'], $userId);
        }
        // ------------------------

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update information.']);
        }
    }
}
$conn->close();
?>