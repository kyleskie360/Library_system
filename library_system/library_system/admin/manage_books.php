<?php
session_start();
require_once('../config/database.php');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Create upload directory if it doesn't exist
$upload_dir = "../uploads/books/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle book actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $title = trim($_POST['title']);
                $author = trim($_POST['author']);
                $isbn = trim($_POST['isbn']);
                $quantity = (int)$_POST['quantity'];
                $category = trim($_POST['category']);
                
                // Handle image upload
                $image_path = null;
                if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['book_image']['name'];
                    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($file_ext, $allowed)) {
                        // Create unique filename
                        $new_filename = uniqid('book_') . '.' . $file_ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['book_image']['tmp_name'], $upload_path)) {
                            $image_path = 'uploads/books/' . $new_filename;
                        } else {
                            $_SESSION['error'] = "Error uploading image";
                            break;
                        }
                    } else {
                        $_SESSION['error'] = "Invalid file type. Allowed types: " . implode(', ', $allowed);
                        break;
                    }
                }
                
                $sql = "INSERT INTO library_resources (title, author, isbn, quantity, category, status, image_path) 
                        VALUES (?, ?, ?, ?, ?, 'Available', ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssiss", $title, $author, $isbn, $quantity, $category, $image_path);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Book added successfully";
                } else {
                    $_SESSION['error'] = "Error adding book: " . $conn->error;
                }
                break;

            case 'edit':
                $resource_id = $_POST['resource_id'];
                $title = trim($_POST['title']);
                $author = trim($_POST['author']);
                $isbn = trim($_POST['isbn']);
                $quantity = (int)$_POST['quantity'];
                $category = trim($_POST['category']);
                $status = $_POST['status'];
                
                // Handle image upload for edit
                $image_path = null;
                if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['book_image']['name'];
                    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($file_ext, $allowed)) {
                        $new_filename = uniqid('book_') . '.' . $file_ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['book_image']['tmp_name'], $upload_path)) {
                            $image_path = 'uploads/books/' . $new_filename;
                            
                            // Update with new image
                            $sql = "UPDATE library_resources SET title=?, author=?, isbn=?, quantity=?, category=?, status=?, image_path=? WHERE resource_id=?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("sssisssi", $title, $author, $isbn, $quantity, $category, $status, $image_path, $resource_id);
                        } else {
                            $_SESSION['error'] = "Error uploading image";
                            break;
                        }
                    } else {
                        $_SESSION['error'] = "Invalid file type. Allowed types: " . implode(', ', $allowed);
                        break;
                    }
                } else {
                    // Update without changing image
                    $sql = "UPDATE library_resources SET title=?, author=?, isbn=?, quantity=?, category=?, status=? WHERE resource_id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssissi", $title, $author, $isbn, $quantity, $category, $status, $resource_id);
                }
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Book updated successfully";
                } else {
                    $_SESSION['error'] = "Error updating book: " . $conn->error;
                }
                break;

            case 'delete':
                $resource_id = $_POST['resource_id'];
                
                // Check if book has active borrowings
                $check_sql = "SELECT COUNT(*) as count FROM borrowings WHERE resource_id = ? AND status = 'Active'";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $resource_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $active_borrowings = $result->fetch_assoc()['count'];

                if ($active_borrowings > 0) {
                    $_SESSION['error'] = "Cannot delete book with active borrowings";
                } else {
                    $sql = "DELETE FROM library_resources WHERE resource_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $resource_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Book deleted successfully";
                    } else {
                        $_SESSION['error'] = "Error deleting book: " . $conn->error;
                    }
                }
                break;
        }
        
        header("Location: manage_books.php");
        exit();
    }
}

// Get all books
$books_query = "SELECT * FROM library_resources ORDER BY title ASC";
$books = $conn->query($books_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resources - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            display: block;
        }
        .sidebar a:hover {
            background: #495057;
        }
        .main-content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="user-info p-3">
                    <h5><?php echo htmlspecialchars($_SESSION['full_name']); ?></h5>
                    <small><?php echo htmlspecialchars($_SESSION['role']); ?></small>
                </div>
                <nav>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="manage_books.php" class="active">Manage Resources</a>
                    <a href="manage_users.php">Manage Users</a>
                    <a href="manage_loans.php">Manage Loans</a>
                    <a href="reports.php">Reports</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Manage Resources</h2>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <!-- Add Book Button -->
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    Add New Book
                </button>

                <!-- Books Table -->
                <div class="card">
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>ISBN</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($book = $books->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if ($book['image_path']): ?>
                                            <img src="../<?php echo htmlspecialchars($book['image_path']); ?>" 
                                                 alt="Book cover" 
                                                 style="max-width: 50px; max-height: 75px;">
                                        <?php else: ?>
                                            <img src="../assets/images/no-cover.png" 
                                                 alt="No cover available" 
                                                 style="max-width: 50px; max-height: 75px;">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                    <td><?php echo $book['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($book['status']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editBook(<?php echo htmlspecialchars(json_encode($book)); ?>)">Edit</button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteBook(<?php echo $book['resource_id']; ?>)">Delete</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Book Modal -->
    <div class="modal fade" id="addBookModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Author</label>
                            <input type="text" class="form-control" name="author" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ISBN</label>
                            <input type="text" class="form-control" name="isbn">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Book Cover Image</label>
                            <input type="file" class="form-control" name="book_image" accept="image/*">
                            <small class="text-muted">Accepted formats: JPG, JPEG, PNG, GIF</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Add Book</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Book Modal -->
    <div class="modal fade" id="editBookModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="editBookForm">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="resource_id" id="edit_resource_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Author</label>
                            <input type="text" class="form-control" name="author" id="edit_author" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ISBN</label>
                            <input type="text" class="form-control" name="isbn" id="edit_isbn" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="edit_quantity" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-control" name="category" id="edit_category" required>
                                <option value="Fiction">Fiction</option>
                                <option value="Non-Fiction">Non-Fiction</option>
                                <option value="Reference">Reference</option>
                                <option value="Textbook">Textbook</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="edit_status" required>
                                <option value="Available">Available</option>
                                <option value="Unavailable">Unavailable</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Book Cover Image</label>
                            <input type="file" class="form-control" name="book_image" accept="image/*">
                            <small class="text-muted">Leave empty to keep current image</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Book</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Bootstrap JS and then your custom JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editBook(book) {
            // Populate the edit form with book data
            document.getElementById('edit_resource_id').value = book.resource_id;
            document.getElementById('edit_title').value = book.title;
            document.getElementById('edit_author').value = book.author;
            document.getElementById('edit_isbn').value = book.isbn;
            document.getElementById('edit_quantity').value = book.quantity;
            document.getElementById('edit_category').value = book.category;
            document.getElementById('edit_status').value = book.status;
            
            // Show the edit modal
            new bootstrap.Modal(document.getElementById('editBookModal')).show();
        }

        function deleteBook(resourceId) {
            if (confirm('Are you sure you want to delete this book?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="resource_id" value="${resourceId}">
                `;
                document.body.append(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
