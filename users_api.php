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
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; // Default 20 users per page
$offset = ($page - 1) * $limit;

// 3. Build Base SQL (Used for both Count and Data)
// Note: We use "WHERE 1=1" trick so we can easily append "AND ..." conditions
$baseSQL = " FROM users WHERE is_verified = 1 AND user_type = 'student'";

$params = [];
$types = '';

// Add search condition
if (!empty($search)) {
    $baseSQL .= " AND (firstname LIKE ? OR surname LIKE ? OR email LIKE ?)";
    $searchTerm = "%" . $search . "%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
    $types .= 'sss';
}

// Add course filter
if (!empty($course)) {
    $baseSQL .= " AND course = ?";
    $params[] = $course;
    $types .= 's';
}

// Add year filter
if (!empty($year)) {
    $baseSQL .= " AND year = ?";
    $params[] = $year;
    $types .= 's';
}

// --- QUERY 1: Get Total Count (For Pagination) ---
$countSql = "SELECT COUNT(*) as total" . $baseSQL;
$stmtCount = $conn->prepare($countSql);
if (!empty($params)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$totalResult = $stmtCount->get_result()->fetch_assoc();
$totalRecords = $totalResult['total'];
$stmtCount->close();

// --- QUERY 2: Get Actual Data (With LIMIT and OFFSET) ---
$sql = "SELECT id, firstname, surname, course, year, email" . $baseSQL . " ORDER BY surname ASC LIMIT ? OFFSET ?";

// Add Pagination Params to binding
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

// 4. Return JSON Response
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