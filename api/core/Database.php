<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'claritystack';
    private $username = 'postgres'; // Update for prod
    private $password = 'password'; // Update for prod
    public $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO("pgsql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        return $this->conn;
    }
}
