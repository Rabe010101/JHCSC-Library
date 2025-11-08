<?php
// add_book.php

// --- 1. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jhcsc_library";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // Exit and return a JSON error message
    die(json_encode(['success' => false, 'message' => 'Connection failed']));
}

header('Content-Type: application/json');

// --- 2. Get Data from JavaScript ---
// Reads the JSON data sent from the fetch request
$data = json_decode(file_get_contents('php://input'), true);

// --- 3. Prepare and Execute SQL Statement ---
// Get all the data from the $data array
$title = $data['title'] ?? '';
$author = $data['author'] ?? '';
$publisher = $data['publisher'] ?? '';
$category = $data['category'] ?? '';
$year = $data['year'] ?? 0;
$cover = $data['cover'] ?? '';
$copies = $data['copies'] ?? 0;

// Prepare the SQL query to prevent SQL injection
$stmt = $conn->prepare("INSERT INTO books (title, author, publisher, category, year, cover, copies) VALUES (?, ?, ?, ?, ?, ?, ?)");

// Bind the variables to the placeholders
// "ssssisi" specifies the data types: s = string, i = integer
$stmt->bind_param("ssssisi", $title, $author, $publisher, $category, $year, $cover, $copies);

// Execute the statement and send a JSON response
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Book added successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: Could not add book.']);
}

$stmt->close();
$conn->close();
?>