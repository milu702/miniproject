<?php
// Database Configuration
class DatabaseConfig {
    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $database = "growguide";
    public $conn;

    public function __construct() {
        // Establish initial connection
        $this->conn = mysqli_connect($this->host, $this->user, $this->password);
        
        // Check connection
        if (!$this->conn) {
            $this->handleError("Connection failed", mysqli_connect_error());
        }

        // Create database if not exists
        $this->createDatabase();

        // Select the database
        mysqli_select_db($this->conn, $this->database);

        // Create tables
        $this->createUsersTable();
        $this->createEmployeesTable();
    }

    private function createDatabase() {
        $sql = "CREATE DATABASE IF NOT EXISTS " . $this->database;
        if (!mysqli_query($this->conn, $sql)) {
            $this->handleError("Error creating database", mysqli_error($this->conn));
        }
    }

    private function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'employee', 'farmer') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status BOOLEAN DEFAULT TRUE,
            first_name VARCHAR(50),
            last_name VARCHAR(50),
            phone VARCHAR(15)
        )";

        if (!mysqli_query($this->conn, $sql)) {
            $this->handleError("Error creating users table", mysqli_error($this->conn));
        }
    }

    private function createEmployeesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS employees (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            profile VARCHAR(255) NOT NULL CHECK (profile LIKE '%.jpg' OR profile LIKE '%.png'),
            qualification ENUM('M.Sc. Agronomy', 'Soil Science', 'Biochemistry', 'Chemistry') NOT NULL,
            certificate VARCHAR(255) NOT NULL CHECK (certificate LIKE '%.pdf'),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";

        if (!mysqli_query($this->conn, $sql)) {
            $this->handleError("Error creating employees table", mysqli_error($this->conn));
        }
    }

    private function handleError($message, $details = '') {
        // Logging or more advanced error handling can be added here
        die($message . ": " . $details);
    }

    public function getConnection() {
        return $this->conn;
    }

    public function __destruct() {
        if ($this->conn) {
            mysqli_close($this->conn);
        }
    }
}

// Usage example
try {
    $dbConfig = new DatabaseConfig();
    $conn = $dbConfig->getConnection();
    
    // Optional: Add a success message or logging
    // error_log("Database and tables created successfully");
} catch (Exception $e) {
    // Handle any unexpected errors
    error_log("Database setup error: " . $e->getMessage());
}
?>
