<?php

require_once '../../src/config/session.php';
require_once '../../src/config/database.php';


$db = Database::getInstance()->getConnection();

$stmt = $db->query("select * from libri order by rand() limit 10;");

$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

