<?php
// returned_books_api.php

session_start();

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); 
    die(json_encode(['error' => 'Access denied. Administrator privileges required.']));
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

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
}

switch ($action) {
    case 'getReturnedBooks':
        $search = $_GET['search'] ?? '';
        
        // --- PAGINATION PARAMETERS ---
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;

        // Base SQL
        $baseSQL = " FROM returned_books rb
                     JOIN users u ON rb.user_id = u.id
                     JOIN books b ON rb.book_id = b.id
                     WHERE rb.is_deleted_by_librarian = 0";

        $params = [];
        $types = '';

        if (!empty($search)) {
            $baseSQL .= " AND (u.firstname LIKE ? OR u.surname LIKE ? OR u.email LIKE ? OR b.title LIKE ? OR rb.transaction_number LIKE ?)";
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
                    rb.id, 
                    rb.transaction_number,
                    rb.issue_date,
                    rb.return_date,
                    u.id AS user_id,
                    CONCAT(u.firstname, ' ', u.surname) AS name,
                    u.email,
                    b.title AS book_title"
                . $baseSQL
                . " ORDER BY rb.return_date DESC LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $returned_books = [];
        while($row = $result->fetch_assoc()) {
            $returned_books[] = $row;
        }

        // Return JSON with Pagination
        echo json_encode([
            'data' => $returned_books,
            'pagination' => [
                'totalRecords' => $totalRecords,
                'totalPages' => ceil($totalRecords / $limit),
                'currentPage' => $page,
                'limit' => $limit
            ]
        ]);

        $stmt->close();
        break;

    case 'deleteReturnedRecord':
        $issuedId = $data['issuedId'] ?? 0;
        if ($issuedId > 0) {
            $checkStmt = $conn->prepare("SELECT is_deleted_by_user FROM returned_books WHERE id = ?");
            $checkStmt->bind_param("i", $issuedId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();

            if ($row) {
                if ($row['is_deleted_by_user'] == 1) {
                    $stmt = $conn->prepare("DELETE FROM returned_books WHERE id = ?");
                } else {
                    $stmt = $conn->prepare("UPDATE returned_books SET is_deleted_by_librarian = 1 WHERE id = ?");
                }
                
                $stmt->bind_param("i", $issuedId);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found.']);
            }
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action specified.']);
        break;
}

$conn->close();
?>