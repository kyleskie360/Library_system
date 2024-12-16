<?php
session_start();
require_once('../config/database.php');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Handle loan actions (add, return, extend)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $user_id = $_POST['user_id'];
                $resource_id = $_POST['resource_id'];
                $borrow_date = date('Y-m-d');
                $due_date = date('Y-m-d', strtotime('+14 days')); // 2 weeks loan period
                
                // Check if book is available
                $check_sql = "SELECT quantity FROM library_resources WHERE resource_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $resource_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $book = $result->fetch_assoc();

                if ($book['quantity'] > 0) {
                    // Create loan record
                    $sql = "INSERT INTO borrowings (user_id, resource_id, borrow_date, due_date, status) 
                            VALUES (?, ?, ?, ?, 'Active')";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isss", $user_id, $resource_id, $borrow_date, $due_date);
                    
                    if ($stmt->execute()) {
                        // Update book quantity
                        $update_sql = "UPDATE library_resources SET quantity = quantity - 1 WHERE resource_id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("i", $resource_id);
                        $update_stmt->execute();
                        
                        $_SESSION['success'] = "Loan created successfully";
                    } else {
                        $_SESSION['error'] = "Error creating loan: " . $conn->error;
                    }
                } else {
                    $_SESSION['error'] = "Book is not available for loan";
                }
                break;

            case 'return':
                $borrowing_id = $_POST['borrowing_id'];
                $resource_id = $_POST['resource_id'];
                
                // Update loan status
                $sql = "UPDATE borrowings SET status = 'Returned', return_date = CURRENT_DATE() 
                        WHERE borrowing_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $borrowing_id);
                
                if ($stmt->execute()) {
                    // Update book quantity
                    $update_sql = "UPDATE library_resources SET quantity = quantity + 1 WHERE resource_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $resource_id);
                    $update_stmt->execute();
                    
                    $_SESSION['success'] = "Book returned successfully";
                } else {
                    $_SESSION['error'] = "Error returning book: " . $conn->error;
                }
                break;

            case 'extend':
                $borrowing_id = $_POST['borrowing_id'];
                $new_due_date = date('Y-m-d', strtotime($_POST['due_date'] . ' +7 days')); // Extend by 1 week
                
                $sql = "UPDATE borrowings SET due_date = ? WHERE borrowing_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $new_due_date, $borrowing_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Loan period extended successfully";
                } else {
                    $_SESSION['error'] = "Error extending loan period: " . $conn->error;
                }
                break;
        }
        
        header("Location: manage_loans.php");
        exit();
    }
}

// Get all active loans
$loans_query = "SELECT b.*, lr.title, u.username, u.first_name, u.last_name 
                FROM borrowings b
                JOIN library_resources lr ON b.resource_id = lr.resource_id
                JOIN users u ON b.user_id = u.user_id
                WHERE b.status != 'Returned'
                ORDER BY b.due_date ASC";
$loans = $conn->query($loans_query);

// Get available books for new loans
$books_query = "SELECT * FROM library_resources WHERE quantity > 0 AND status = 'Available'";
$available_books = $conn->query($books_query);

// Get users for new loans
$users_query = "SELECT * FROM users WHERE role_id != 1"; // Excluding admin users
$users = $conn->query($users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Loans - Library System</title>
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
        .overdue {
            color: red;
            font-weight: bold;
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
                    <a href="manage_books.php">Manage Resources</a>
                    <a href="manage_users.php">Manage Users</a>
                    <a href="manage_loans.php" class="active">Manage Loans</a>
                    <a href="reports.php">Reports</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Manage Loans</h2>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <!-- Create New Loan Button -->
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#newLoanModal">
                    Create New Loan
                </button>

                <!-- Active Loans Table -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Active Loans</h5>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Borrower</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($loan = $loans->fetch_assoc()): 
                                    $is_overdue = strtotime($loan['due_date']) < time();
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($loan['title']); ?></td>
                                    <td><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($loan['borrow_date'])); ?></td>
                                    <td class="<?php echo $is_overdue ? 'overdue' : ''; ?>">
                                        <?php echo date('M d, Y', strtotime($loan['due_date'])); ?>
                                    </td>
                                    <td><?php echo $loan['status']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-success" onclick="returnBook(<?php echo $loan['borrowing_id']; ?>, <?php echo $loan['resource_id']; ?>)">Return</button>
                                        <button class="btn btn-sm btn-warning" onclick="extendLoan(<?php echo $loan['borrowing_id']; ?>, '<?php echo $loan['due_date']; ?>')">Extend</button>
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

    <!-- New Loan Modal -->
    <div class="modal fade" id="newLoanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Select User</label>
                            <select class="form-control" name="user_id" required>
                                <?php while($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Book</label>
                            <select class="form-control" name="resource_id" required>
                                <?php while($book = $available_books->fetch_assoc()): ?>
                                    <option value="<?php echo $book['resource_id']; ?>">
                                        <?php echo htmlspecialchars($book['title']); ?> 
                                        (<?php echo $book['quantity']; ?> available)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Create Loan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function returnBook(borrowingId, resourceId) {
            if (confirm('Are you sure you want to return this book?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="return">
                    <input type="hidden" name="borrowing_id" value="${borrowingId}">
                    <input type="hidden" name="resource_id" value="${resourceId}">
                `;
                document.body.append(form);
                form.submit();
            }
        }

        function extendLoan(borrowingId, currentDueDate) {
            if (confirm('Are you sure you want to extend this loan by 1 week?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="extend">
                    <input type="hidden" name="borrowing_id" value="${borrowingId}">
                    <input type="hidden" name="due_date" value="${currentDueDate}">
                `;
                document.body.append(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 