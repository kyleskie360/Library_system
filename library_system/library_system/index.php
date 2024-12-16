<?php
require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'config/database.php';

// Get all available books
$books_query = "SELECT * FROM library_resources WHERE status='Available' ORDER BY title ASC";
$books = $conn->query($books_query);
?>

<main>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8">
                <h1>Welcome to RMMC Library Management System</h1>
                <p class="lead">Access our collection of over 10,000 books, periodicals, and media resources.</p>
                
                <div class="mt-4">
                    <h2>Quick Search</h2>
                    <form action="" method="GET" class="mb-4">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search by title, author, or ISBN...">
                            <button class="btn btn-primary" type="submit">Search</button>
                        </div>
                    </form>

                    <div class="mt-4">
                        <h3>Available Books</h3>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>ISBN</th>
                                        <th>Category</th>
                                        <th>Availability</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if(isset($_GET['search'])) {
                                        $search = $conn->real_escape_string($_GET['search']);
                                        $search_query = "SELECT * FROM library_resources 
                                                       WHERE (title LIKE '%$search%' 
                                                       OR author LIKE '%$search%' 
                                                       OR isbn LIKE '%$search%')
                                                       AND status='Available'
                                                       ORDER BY title ASC";
                                        $books = $conn->query($search_query);
                                    }

                                    while($book = $books->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                                        <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                        <td><?php echo htmlspecialchars($book['category']); ?></td>
                                        <td><?php echo $book['quantity'] > 0 ? $book['quantity'].' available' : 'Out of stock'; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <div class="alert alert-info">
                                Please <a href="student/login.php">login as a student</a> to borrow books.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Quick Links</h3>
                    </div>
                    <div class="card-body d-flex justify-content-center gap-3">
                        <a href="admin/login.php" class="btn btn-primary">Admin Login</a>
                        <a href="student/login.php" class="btn btn-success">Student Login</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Library Hours</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li>Monday - Friday: 8:00 AM - 4:00 PM</li>
                            <li>Saturday: 9:00 AM - 1:00 PM</li>
                            <li>Sunday: Closed</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
