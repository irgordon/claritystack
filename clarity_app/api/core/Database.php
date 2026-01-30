<?php
class Database {
    private static $instance = null;
    private $configFile = __DIR__ . '/../config/env.php';
    private $injectedConfig = null;
    public $conn;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize a singleton."); }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setConfig(array $config) {
        $this->injectedConfig = $config;
        if ($this->conn !== null) {
            $this->conn = null;
        }
    }

    public function connect() {
        // Return existing connection if available
        if ($this->conn !== null) {
            return $this->conn;
        }

        // 1. Check for Config
        if (!file_exists($this->configFile)) {
            // If we are trying to connect but no config exists, 
            // we are likely in the "InstallController" trying to set things up.
            // We return null and let the Controller handle the manual connection.
            return null;
        }

        // 2. Load Config
        if ($this->injectedConfig !== null) {
            $config = $this->injectedConfig;
        } else {
            $config = require $this->configFile;
        }

        try {
            $driver = $config['DB_DRIVER'] ?? 'pgsql';
            if ($driver === 'sqlite') {
                $dsn = "sqlite:" . $config['DB_NAME'];
            } else {
                $dsn = "pgsql:host=" . $config['DB_HOST'] . ";dbname=" . $config['DB_NAME'];
            }

            $this->conn = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            throw new Exception("Database Connection Failed. Check your config.");
        }
        return $this->conn;
    }
}
