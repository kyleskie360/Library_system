-- Create the database
CREATE DATABASE library_management;
USE library_management;

-- Create Roles table
CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    contact_number VARCHAR(20),
    max_books_allowed INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Create LibraryResources table
CREATE TABLE library_resources (
    resource_id INT PRIMARY KEY AUTO_INCREMENT,
    accession_number VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    status ENUM('Available', 'Borrowed', 'Lost', 'Maintenance') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Books table
CREATE TABLE books (
    book_id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT,
    isbn VARCHAR(13),
    author VARCHAR(100),
    publisher VARCHAR(100),
    edition VARCHAR(20),
    publication_date DATE,
    FOREIGN KEY (resource_id) REFERENCES library_resources(resource_id)
);

-- Create Periodicals table
CREATE TABLE periodicals (
    periodical_id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT,
    issn VARCHAR(8),
    volume VARCHAR(20),
    issue VARCHAR(20),
    publication_date DATE,
    FOREIGN KEY (resource_id) REFERENCES library_resources(resource_id)
);

-- Create MediaResources table
CREATE TABLE media_resources (
    media_id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT,
    format VARCHAR(50),
    runtime INT,
    media_type VARCHAR(50),
    FOREIGN KEY (resource_id) REFERENCES library_resources(resource_id)
);

-- Create Borrowings table
CREATE TABLE borrowings (
    borrowing_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    resource_id INT,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('Active', 'Returned', 'Overdue') DEFAULT 'Active',
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (resource_id) REFERENCES library_resources(resource_id)
);

-- Create Fines table
CREATE TABLE fines (
    fine_id INT PRIMARY KEY AUTO_INCREMENT,
    borrowing_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE,
    payment_status ENUM('Pending', 'Paid') DEFAULT 'Pending',
    FOREIGN KEY (borrowing_id) REFERENCES borrowings(borrowing_id)
);

-- Create ActivityLog table
CREATE TABLE activity_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action_type VARCHAR(50) NOT NULL,
    action_details TEXT,
    action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Insert default roles
INSERT INTO roles (role_name) VALUES 
('Admin'),
('Librarian'),
('Faculty'),
('Student');

-- Insert default admin user (password: admin123)
INSERT INTO users (role_id, username, password, first_name, last_name, email, max_books_allowed)
VALUES (
    1, 
    'admin',
    '$2y$10$YourHashedPasswordHere',  -- Remember to hash the password in your application
    'System',
    'Administrator',
    'admin@brightfuture.edu',
    10
); 

-- Table for library resources (books)
CREATE TABLE IF NOT EXISTS library_resources (
    resource_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(50),
    quantity INT NOT NULL DEFAULT 1,
    category VARCHAR(50),
    status VARCHAR(20) DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for borrowings
CREATE TABLE IF NOT EXISTS borrowings (
    borrowing_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    resource_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (resource_id) REFERENCES library_resources(resource_id)
);

-- Add indexes for better performance
ALTER TABLE borrowings ADD INDEX idx_user_id (user_id);
ALTER TABLE borrowings ADD INDEX idx_resource_id (resource_id);
ALTER TABLE borrowings ADD INDEX idx_status (status);
ALTER TABLE library_resources ADD INDEX idx_title (title);

-- Modify the existing table without trying to add another primary key
ALTER TABLE library_resources
ADD COLUMN IF NOT EXISTS title VARCHAR(255) NOT NULL,
ADD COLUMN IF NOT EXISTS author VARCHAR(255) NOT NULL,
ADD COLUMN IF NOT EXISTS isbn VARCHAR(50),
ADD COLUMN IF NOT EXISTS quantity INT NOT NULL DEFAULT 1,
ADD COLUMN IF NOT EXISTS category VARCHAR(50),
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'Available',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
