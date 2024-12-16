<?php
session_start();
require_once('../config/database.php');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get admin information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];

// Get quick statistics
$stats = array();

// Total resources
$result = $conn->query("SELECT COUNT(*) as total FROM library_resources");
$stats['total_resources'] = $result->fetch_assoc()['total'];

// Total borrowed resources
$result = $conn->query("SELECT COUNT(*) as total FROM borrowings WHERE status = 'Active'");
$stats['borrowed_resources'] = $result->fetch_assoc()['total'];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id != 1"); // Excluding admins
$stats['total_users'] = $result->fetch_assoc()['total'];

// Overdue books
$result = $conn->query("SELECT COUNT(*) as total FROM borrowings WHERE status = 'Overdue'");
$stats['overdue_books'] = $result->fetch_assoc()['total'];

// Popular books (top 5)
$popular_books_query = "SELECT lr.title, COUNT(b.borrowing_id) as borrow_count
    FROM borrowings b
    JOIN library_resources lr ON b.resource_id = lr.resource_id
    GROUP BY lr.title
    ORDER BY borrow_count DESC
    LIMIT 5";
$popular_books = $conn->query($popular_books_query);

// Recent activities (last 5)
$activities_query = "SELECT b.*, lr.title, u.username as borrower_name, u.role_id,
    r.role_name as user_type
    FROM borrowings b
    LEFT JOIN library_resources lr ON b.resource_id = lr.resource_id
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN roles r ON u.role_id = r.role_id
    ORDER BY b.borrow_date DESC LIMIT 5";
$recent_activities = $conn->query($activities_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Library System</title>
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
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .main-content {
            padding: 20px;
        }
        .user-info {
            padding: 15px;
            border-bottom: 1px solid #495057;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="user-info">
                    <h5><?php echo htmlspecialchars($full_name); ?></h5>
                    <small><?php echo htmlspecialchars($_SESSION['role']); ?></small>
                </div>
                <nav>
                    <a href="dashboard.php" class="active">Dashboard</a>
                    <a href="manage_books.php">Manage Resources</a>
                    <a href="manage_users.php">Manage Users</a>
                    <a href="manage_loans.php">Manage Loans</a>
                    <a href="reports.php">Reports</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Dashboard</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5>Total Resources</h5>
                            <h3><?php echo $stats['total_resources']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5>Resources Borrowed</h5>
                            <h3><?php echo $stats['borrowed_resources']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5>Total Users</h5>
                            <h3><?php echo $stats['total_users']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h5>Overdue Books</h5>
                            <h3><?php echo $stats['overdue_books']; ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Popular Books -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Popular Books</h5>
                    </div>
                    <div class="card-body">
                        <ul>
                            <?php while($book = $popular_books->fetch_assoc()): ?>
                                <li><?php echo htmlspecialchars($book['title']); ?> (<?php echo $book['borrow_count']; ?> times borrowed)</li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Resource</th>
                                    <th>Borrower</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($activity = $recent_activities->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($activity['borrow_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['borrower_name']); ?> 
                                        <small>(<?php echo htmlspecialchars($activity['user_type']); ?>)</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $activity['status'] == 'Active' ? 'primary' : ($activity['status'] == 'Overdue' ? 'danger' : 'success'); ?>">
                                            <?php echo ucfirst($activity['status']); ?>
                                        </span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>