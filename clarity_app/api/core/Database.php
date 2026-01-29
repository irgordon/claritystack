<?php
class Database {
    // Update these credentials for Production
    private $host = 'localhost';
    private $db_name = 'claritystack';
    private $username = 'clarity_user';
    private $password = 'secure_password'; 
    public $conn;

    public function connect() {
        $this->conn = null;
        try {
            $dsn = "pgsql:host=" . $this->host . ";dbname=" . $this->db_name;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // In prod, log this to file, do not echo to screen
            error_log("Connection Error: " . $e->getMessage());
            die("Database Connection Failed.");
        }
        return $this->conn;
    }
}
?>
