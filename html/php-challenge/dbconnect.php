<?php
try {
    $db = new PDO('mysql:dbname=test;host=mysql;charset=utf8', 'root', 'root');
} catch (PDOException $e) {
    echo 'DB接続エラー： ' . $e->getMessage();
}
