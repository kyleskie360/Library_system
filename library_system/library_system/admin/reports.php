<?php
session_start();
require_once('../config/database.php');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get various reports
$reports = array();

// Most borrowed books
$popular_books_query = "SELECT lr.title, lr.author, COUNT(b.borrowing_id) as borrow_count
    FROM borrowings b
    JOIN library_resources lr ON b.resource_id = lr.resource_id
    WHERE b.borrow_date BETWEEN ? AND ?
    GROUP BY lr.resource_id
    ORDER BY borrow_count DESC
    LIMIT 10";
$stmt = $conn->prepare($popular_books_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$reports['popular_books'] = $stmt->get_result();

// Overdue books
$overdue_query = "SELECT lr.title, u.first_name, u.last_name, b.due_date,
    DATEDIFF(CURRENT_DATE(), b.due_date) as days_overdue
    FROM borrowings b
    JOIN library_resources lr ON b.resource_id = lr.resource_id
    JOIN users u ON b.user_id = u.user_id
    WHERE b.status = 'Active' AND b.due_date < CURRENT_DATE()
    ORDER BY days_overdue DESC";
$reports['overdue'] = $conn->query($overdue_query);

// User statistics
$user_stats_query = "SELECT u.first_name, u.last_name, COUNT(b.borrowing_id) as total_borrows,
    SUM(CASE WHEN b.status = 'Active' THEN 1 ELSE 0 END) as active_borrows
    FROM users u
    LEFT JOIN borrowings b ON u.user_id = b.user_id
    WHERE u.role_id != 1
    GROUP BY u.user_id
    ORDER BY total_borrows DESC";
$reports['user_stats'] = $conn->query($user_stats_query);

// Monthly borrowing trends
$trends_query = "SELECT DATE_FORMAT(borrow_date, '%Y-%m') as month,
    COUNT(*) as total_borrows
    FROM borrowings
    WHERE borrow_date BETWEEN ? AND ?
    GROUP BY month
    ORDER BY month DESC";
$stmt = $conn->prepare($trends_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$reports['trends'] = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Library System</title>
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
        .report-card {
            margin-bottom: 20px;
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
                    <a href="manage_loans.php">Manage Loans</a>
                    <a href="reports.php" class="active">Reports</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Library Reports</h2>

                <!-- Date Range Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-auto">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Apply Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Popular Books Report -->
                <div class="card report-card">
                    <div class="card-header">
                        <h5>Most Borrowed Books</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Times Borrowed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($book = $reports['popular_books']->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo $book['borrow_count']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Overdue Books Report -->
                <div class="card report-card">
                    <div class="card-header">
                        <h5>Overdue Books</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Borrower</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($overdue = $reports['overdue']->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($overdue['title']); ?></td>
                                    <td><?php echo htmlspecialchars($overdue['first_name'] . ' ' . $overdue['last_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($overdue['due_date'])); ?></td>
                                    <td class="text-danger"><?php echo $overdue['days_overdue']; ?> days</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- User Statistics Report -->
                <div class="card report-card">
                    <div class="card-header">
                        <h5>User Statistics</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Total Borrows</th>
                                    <th>Active Borrows</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($user = $reports['user_stats']->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo $user['total_borrows']; ?></td>
                                    <td><?php echo $user['active_borrows']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Monthly Trends Report -->
                <div class="card report-card">
                    <div class="card-header">
                        <h5>Monthly Borrowing Trends</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Total Borrows</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($trend = $reports['trends']->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                                    <td><?php echo $trend['total_borrows']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Add Chart.js if you want to add visual graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>
