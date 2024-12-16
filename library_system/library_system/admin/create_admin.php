<?php
require_once('../config/database.php');

// Function to check if admin already exists
function adminExists($conn) {
    $sql = "SELECT COUNT(*) as count FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE r.role_name = 'Admin'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

// Function to get admin role ID
function getAdminRoleId($conn) {
    $sql = "SELECT role_id FROM roles WHERE role_name = 'Admin'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['role_id'];
}

try {
    // Check if admin already exists
    if (adminExists($conn)) {
        die("An admin account already exists. For security reasons, please use the existing admin account.");
    }

    // Admin account details
    $username = "admin";
    $password = "admin123"; // You should change this after first login
    $firstName = "System";
    $lastName = "Administrator";
    $email = "admin@brightfuture.edu";
    $maxBooksAllowed = 10;

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Get admin role ID
    $roleId = getAdminRoleId($conn);

    // Prepare the insert statement
    $sql = "INSERT INTO users (role_id, username, password, first_name, last_name, email, max_books_allowed) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssi", $roleId, $username, $hashedPassword, $firstName, $lastName, $email, $maxBooksAllowed);

    // Execute the statement
    if ($stmt->execute()) {
        echo "Admin account created successfully!<br>";
        echo "Username: " . htmlspecialchars($username) . "<br>";
        echo "Password: " . htmlspecialchars($password) . "<br>";
        echo "";
    } else {
        echo "Error creating admin account: " . $conn->error;
    }
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage();
}

$stmt->close();
$conn->close();
?>