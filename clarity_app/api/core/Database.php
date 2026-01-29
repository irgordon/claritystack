<?php
class Database {
    private $configFile = __DIR__ . '/../config/env.php';
    public $conn;

    public function connect() {
        $this->conn = null;

        // 1. Check for Config
        if (!file_exists($this->configFile)) {
            // If we are trying to connect but no config exists, 
            // we are likely in the "InstallController" trying to set things up.
            // We return null and let the Controller handle the manual connection.
            return null;
        }

        // 2. Load Config
        $config = require $this->configFile;

        try {
            $dsn = "pgsql:host=" . $config['DB_HOST'] . ";dbname=" . $config['DB_NAME'];
            $this->conn = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("Database Connection Failed. Check your config.");
        }
        return $this->conn;
    }
}
