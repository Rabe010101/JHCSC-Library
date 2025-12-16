<?php
// users_api.php

header('Content-Type: application/json');

// 1. Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection Failed']));
}

// 2. Get Parameters
$search = $_GET['search'] ?? '';
$course = $_GET['course'] ?? '';
$year = $_GET['year'] ?? '';

// --- PAGINATION PARAMETERS ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// 3. Build Base SQL
$baseSQL = " FROM users WHERE is_verified = 1 AND user_type = 'student'";

$params = [];
$types = '';

if (!empty($search)) {
    $baseSQL .= " AND (firstname LIKE ? OR surname LIKE ? OR email LIKE ?)";
    $searchTerm = "%" . $search . "%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
    $types .= 'sss';
}

if (!empty($course)) {
    $baseSQL .= " AND course = ?";
    $params[] = $course;
    $types .= 's';
}

if (!empty($year)) {
    $baseSQL .= " AND year = ?";
    $params[] = $year;
    $types .= 's';
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

// --- QUERY 2: Get Data with BOTH Counts (THE FIX) ---
$sql = "SELECT 
            id, firstname, surname, course, year, email,
            -- Subquery 1: Overdue Books
            (SELECT COUNT(*) FROM issued_books ib 
             WHERE ib.user_id = users.id 
             AND ib.status = 'Issued' 
             AND ib.due_date < CURDATE()) as overdue_count,
            -- Subquery 2: Total Active Books
            (SELECT COUNT(*) FROM issued_books ib 
             WHERE ib.user_id = users.id 
             AND ib.status = 'Issued') as active_count
        " . $baseSQL 
        . " ORDER BY surname ASC LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// 4. Return JSON
echo json_encode([
    'data' => $users,
    'pagination' => [
        'totalRecords' => $totalRecords,
        'totalPages' => ceil($totalRecords / $limit),
        'currentPage' => $page,
        'limit' => $limit
    ]
]);

$stmt->close();
$conn->close();
?>