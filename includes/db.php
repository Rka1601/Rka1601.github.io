<?php
require_once __DIR__ . '/config.php';

class Database {
    private $conn;

    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=".DB_HOST.";dbname=".DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
            
            // Установка временной зоны для MySQL (UTC+7)
            $this->conn->exec("SET time_zone = '+07:00'");
        } catch(PDOException $e) {
            die("Ошибка подключения: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}