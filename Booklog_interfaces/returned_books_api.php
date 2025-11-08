<?php
// returned_books_api.php

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

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
}

switch ($action) {
    // --- ACTION: GET ALL RETURNED BOOKS ---
    case 'getReturnedBooks':
        $search = $_GET['search'] ?? '';
        
        $sql = "SELECT 
                    ib.id, 
                    ib.transaction_number,
                    ib.issue_date,
                    ib.return_date,
                    u.id AS user_id,
                    CONCAT(u.firstname, ' ', u.surname) AS name,
                    u.email,
                    b.title AS book_title
                FROM issued_books ib
                JOIN users u ON ib.user_id = u.id
                JOIN books b ON ib.book_id = b.id
                WHERE ib.status = 'Returned'"; // The key difference: fetch only 'Returned' books

        if (!empty($search)) {
            $sql .= " AND (u.firstname LIKE ? OR u.surname LIKE ? OR u.email LIKE ? OR b.title LIKE ? OR ib.transaction_number LIKE ?)";
            $searchTerm = "%" . $search . "%";
        }

        $sql .= " ORDER BY ib.return_date DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($search)) {
            $stmt->bind_param("sssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $returned_books = [];
        while($row = $result->fetch_assoc()) {
            $returned_books[] = $row;
        }
        echo json_encode($returned_books);
        $stmt->close();
        break;

    // --- ACTION: DELETE A RETURNED RECORD (for cleanup) ---
    case 'deleteReturnedRecord':
        $issuedId = $data['issuedId'] ?? 0;
        if ($issuedId > 0) {
            $stmt = $conn->prepare("DELETE FROM issued_books WHERE id = ? AND status = 'Returned'");
            $stmt->bind_param("i", $issuedId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Record deleted permanently.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Record ID.']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action specified.']);
        break;
}

$conn->close();
?>