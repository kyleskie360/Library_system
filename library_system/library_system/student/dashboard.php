<?php
session_start();
require_once('../config/database.php');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    header("Location: login.php");
    exit();
}

// Get student's borrowed books
$borrowed_books_query = "SELECT b.*, lr.title, lr.author, lr.image_path 
                        FROM borrowings b
                        JOIN library_resources lr ON b.resource_id = lr.resource_id
                        WHERE b.user_id = ? AND b.status != 'Returned'
                        ORDER BY b.due_date ASC";
$stmt = $conn->prepare($borrowed_books_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$borrowed_books = $stmt->get_result();

// Get student's fines
$fines_query = "SELECT f.*, b.borrow_date, lr.title
                FROM fines f
                JOIN borrowings b ON f.borrowing_id = b.borrowing_id
                JOIN library_resources lr ON b.resource_id = lr.resource_id
                WHERE b.user_id = ? AND f.payment_status = 'Pending'
                ORDER BY f.fine_id DESC";
$stmt = $conn->prepare($fines_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$fines = $stmt->get_result();

// Calculate total fines
$total_fines = 0;
while ($fine = $fines->fetch_assoc()) {
    $total_fines += $fine['amount'];
}
$fines->data_seek(0); // Reset result pointer
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.2s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .card-img-top {
            border-bottom: 1px solid #dee2e6;
        }
        .overdue {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Library System</a>
            <div class="navbar-text text-white">
                Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </div>
            <a href="logout.php" class="btn btn-outline-light">Logout</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2>My Borrowed Books</h2>
                <div class="row">
                    <?php while($book = $borrowed_books->fetch_assoc()): 
                        $is_overdue = strtotime($book['due_date']) < time();
                    ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if (!empty($book['image_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($book['image_path']); ?>" 
                                     class="card-img-top" 
                                     alt="Book cover"
                                     style="height: 250px; object-fit: cover;">
                            <?php else: ?>
                                <img src="../assets/images/no-cover.png" 
                                     class="card-img-top" 
                                     alt="No cover available"
                                     style="height: 250px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                                <p class="card-text">
                                    <strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?><br>
                                    <strong>Borrowed:</strong> <?php echo date('M d, Y', strtotime($book['borrow_date'])); ?><br>
                                    <strong>Due Date:</strong> 
                                    <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo date('M d, Y', strtotime($book['due_date'])); ?>
                                    </span><br>
                                    <strong>Status:</strong> <?php echo $book['status']; ?>
                                </p>
                            </div>
                            <?php if ($is_overdue): ?>
                                <div class="card-footer bg-danger text-white">
                                    <small>OVERDUE</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="col-md-4">
                <h2>My Fines</h2>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Pending Fines: ₱<?php echo number_format($total_fines, 2); ?></h5>
                        <?php if ($fines->num_rows > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Book</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($fine = $fines->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fine['title']); ?></td>
                                        <td>₱<?php echo number_format($fine['amount'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-success">No pending fines!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 