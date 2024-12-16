<?php
require_once('../config/database.php');

if (isset($_GET['resource_id'])) {
    $resource_id = (int)$_GET['resource_id'];
    
    $sql = "SELECT * FROM library_resources WHERE resource_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($book = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($book);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Book not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Resource ID not provided']);
} 