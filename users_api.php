<?php
// users_api.php

header('Content-Type: application/json');

// --- 1. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection Failed']));
}

// --- 2. Get Filters from Request ---
$search = $_GET['search'] ?? '';
$course = $_GET['course'] ?? '';
$year = $_GET['year'] ?? '';

// --- 3. Build SQL Query Securely ---
// Base query selects all necessary fields but excludes admins from the list.
$sql = "SELECT id, firstname, surname, course, year, email FROM users";
$conditions = [];
$params = [];
$types = '';

// Add search condition (searches firstname, surname, and email)
if (!empty($search)) {
    $conditions[] = "(firstname LIKE ? OR surname LIKE ? OR email LIKE ?)";
    $searchTerm = "%" . $search . "%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
    $types .= 'sss';
}

// Add course filter
if (!empty($course)) {
    $conditions[] = "course = ?";
    $params[] = $course;
    $types .= 's';
}

// Add year filter
if (!empty($year)) {
    $conditions[] = "year = ?";
    $params[] = $year;
    $types .= 's';
}

// Combine conditions if any exist
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY surname ASC";

// --- 4. Prepare and Execute ---
$stmt = $conn->prepare($sql);

// Bind parameters if any exist
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// --- 5. Return JSON Response ---
echo json_encode($users);

$stmt->close();
$conn->close();
?>