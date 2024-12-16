<?php
session_start();
require_once('../config/database.php');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Handle user actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = trim($_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $role_id = $_POST['role_id'];
                $contact = trim($_POST['contact_number']);
                
                $sql = "INSERT INTO users (username, password, first_name, last_name, email, role_id, contact_number) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssss", $username, $password, $first_name, $last_name, $email, $role_id, $contact);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "User added successfully";
                } else {
                    $_SESSION['error'] = "Error adding user: " . $conn->error;
                }
                break;

            case 'edit':
                $user_id = $_POST['user_id'];
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $role_id = $_POST['role_id'];
                $contact = trim($_POST['contact_number']);
                
                $sql = "UPDATE users SET first_name=?, last_name=?, email=?, role_id=?, contact_number=? 
                        WHERE user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssi", $first_name, $last_name, $email, $role_id, $contact, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "User updated successfully";
                } else {
                    $_SESSION['error'] = "Error updating user: " . $conn->error;
                }
                break;

            case 'delete':
                $user_id = $_POST['user_id'];
                
                // Check if user has any active borrowings
                $check_sql = "SELECT COUNT(*) as count FROM borrowings WHERE user_id = ? AND status = 'Active'";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $active_borrowings = $result->fetch_assoc()['count'];

                if ($active_borrowings > 0) {
                    $_SESSION['error'] = "Cannot delete user with active borrowings";
                } else {
                    $sql = "DELETE FROM users WHERE user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "User deleted successfully";
                    } else {
                        $_SESSION['error'] = "Error deleting user: " . $conn->error;
                    }
                }
                break;
        }
        
        // Redirect to refresh the page
        header("Location: manage_users.php");
        exit();
    }
}

// Get all roles for the dropdown
$roles_query = "SELECT * FROM roles WHERE role_name != 'Admin'";
$roles = $conn->query($roles_query);

// Get all users with their roles
$users_query = "SELECT u.*, r.role_name, 
                (SELECT COUNT(*) FROM borrowings WHERE user_id = u.user_id AND status = 'Active') as active_borrowings
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE r.role_name != 'Admin'
                ORDER BY u.created_at DESC";
$users = $conn->query($users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Library System</title>
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
                    <a href="manage_books.php">Manage Resources</a>
                    <a href="manage_users.php" class="active">Manage Users</a>
                    <a href="manage_loans.php">Manage Loans</a>
                    <a href="reports.php">Reports</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Manage Users</h2>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <!-- Add User Button -->
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    Add New User
                </button>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Active Borrowings</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                                    <td><?php echo $user['active_borrowings']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo $user['user_id']; ?>)">Edit</button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">Delete</button>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-control" name="role_id" required>
                                <?php 
                                $roles->data_seek(0);
                                while($role = $roles->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $role['role_id']; ?>">
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(userId) {
            // Implement edit user functionality
            alert('Edit user ' + userId);
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.append(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
