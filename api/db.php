<?php
// ไฟล์: api/db.php

$host = 'localhost'; // หรือใส่ IP ของ Docker Container หากจำเป็น
$port = '3306';      
$db   = 'airq_db';   
$user = 'root';      
$pass = '123456789';    

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ขว้าง Exception เมื่อมี Error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ดึงข้อมูลออกมาเป็น Associative Array
        PDO::ATTR_EMULATE_PREPARES   => false,                  // ปิดการจำลอง Prepared Statements เพื่อความปลอดภัย
    ]);
} catch (PDOException $e) { 
    // หากเชื่อมต่อไม่ได้ ให้คืนค่าเป็น JSON ออกไปเพื่อป้องกันเว็บพัง
    die(json_encode([
        "status" => "error", 
        "message" => "Database Connection Error: " . $e->getMessage()
    ])); 
}
?>