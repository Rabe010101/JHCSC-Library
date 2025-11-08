<?php
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

// Determine action based on request method
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
}

switch ($action) {
    case 'getAllBooks':
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $year = $_GET['year'] ?? '';

        $sql = "SELECT
            b.id, b.title, b.author, b.publisher, b.year, b.cover, b.copies,
            c.name AS category,
            b.category_id
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id";
        $conditions = [];
        $params = [];
        $types = '';

        // Handle search term
        if (!empty($search)) {
            $conditions[] = "(title LIKE ? OR author LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }

        // Handle category filter
        if (!empty($category)) {
            $conditions[] = "category = ?";
            $params[] = $category;
            $types .= 's';
        }

        // MODIFIED: Handle year filter to search like text
        if (!empty($year)) {
            // This converts the year to text to allow partial matches
            $conditions[] = "CAST(year AS CHAR) LIKE ?"; 
            // This adds wildcards to the year search term
            $params[] = "%" . $year . "%"; 
            // This changes the parameter type to a string
            $types .= 's'; 
        }

        // Combine conditions if any exist
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY title ASC";
        
        $stmt = $conn->prepare($sql);

        // Bind parameters if any exist
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        echo json_encode($books);
        $stmt->close();
        break;

    case 'getAllCategories':
        $result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        echo json_encode($categories);
        break;

    case 'addCategory':
        $name = $data['name'] ?? '';
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                // Return the newly created category with its ID
                $newId = $conn->insert_id;
                echo json_encode(['success' => true, 'message' => 'Category added!', 'newCategory' => ['id' => $newId, 'name' => $name]]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add category. It may already exist.']);
            }
            $stmt->close();
        }
        break;

    case 'deleteCategory':
    $categoryId = $data['categoryId'] ?? 0;

    if ($categoryId > 0) {
        // Step 1: Check if any books are using this category
        $checkStmt = $conn->prepare("SELECT COUNT(*) as book_count FROM books WHERE category_id = ?");
        $checkStmt->bind_param("i", $categoryId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        $checkStmt->close();

        if ($row['book_count'] > 0) {
            // If books are found, do not delete. Send a specific error message.
            echo json_encode(['success' => false, 'message' => 'Cannot delete category. It is currently in use by ' . $row['book_count'] . ' book(s).']);
        } else {
            // Step 2: If no books are using it, proceed with deletion
            $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $deleteStmt->bind_param("i", $categoryId);
            if ($deleteStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Category deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete category.']);
            }
            $deleteStmt->close();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID.']);
    }
    break;

    case 'addBook':
    $stmt = $conn->prepare("INSERT INTO books (title, author, publisher, category_id, year, cover, copies) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiisi", $data['title'], $data['author'], $data['publisher'], $data['category_id'], $data['year'], $data['coverUrl'], $data['copies']);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Book added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add book.']);
    }
    $stmt->close();
    break;

    case 'updateBook':
    $stmt = $conn->prepare("UPDATE books SET title=?, author=?, publisher=?, category_id=?, year=?, cover=?, copies=? WHERE id=?");
    $stmt->bind_param("sssiisii", $data['title'], $data['author'], $data['publisher'], $data['category_id'], $data['year'], $data['coverUrl'], $data['copies'], $data['bookId']);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Book updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update book.']);
    }
    $stmt->close();
    break;

    case 'deleteBook':
        $stmt = $conn->prepare("DELETE FROM books WHERE id=?");
        $stmt->bind_param("i", $data['bookId']);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete book.']);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['error' => 'Invalid action.']);
        break;
}

$conn->close();
?>