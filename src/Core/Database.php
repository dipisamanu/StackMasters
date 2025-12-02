<?php

namespace Ottaviodipisa\StackMasters\Core;
use config\PDO;

class Database
{
    private static $instance = null;
    private $pdo;

    public function __construct()
    {
        $this->pdo = new PDO("mysql:host=localhost;dbname=   ", "root", "");
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

}