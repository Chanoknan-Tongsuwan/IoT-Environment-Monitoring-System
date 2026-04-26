<?php
header('Content-Type: application/json');
require 'db.php'; // ไฟล์นี้ต้องแก้ Host เป็น localhost และใส่รหัสผ่านให้ถูก

// รับค่าจาก $_POST (วิธีมาตรฐานที่ AppServ เสถียรที่สุด)
$room_id = $_POST['room_id'] ?? null;
$temp    = $_POST['temp'] ?? 0;
$humid   = $_POST['humid'] ?? 0;
$pm      = $_POST['pm'] ?? 0;
$odor    = $_POST['odor'] ?? 0;

if ($room_id) {
    try {
        // 1. วิเคราะห์สถานะเพื่อกำหนดสีและไอคอน
        $color = 'success'; $text = 'ปกติ'; $icon = 'bx-check-circle';
        if ($pm >= 50 || $temp >= 35) { 
            $color = 'danger'; $text = 'อันตราย/วิกฤต'; $icon = 'bxs-error-alt'; 
        }

        // 2. อัปเดตตารางสถานะล่าสุด
        $sql = "INSERT INTO rooms_latest (room_id, temp, humid, pm, odor, status_text, status_color, status_icon, updated_at) 
                VALUES (?,?,?,?,?,?,?,?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                temp=?, humid=?, pm=?, odor=?, status_text=?, status_color=?, status_icon=?, updated_at=NOW()";
        
        $pdo->prepare($sql)->execute([
            $room_id, $temp, $humid, $pm, $odor, $text, $color, $icon,
            $temp, $humid, $pm, $odor, $text, $color, $icon
        ]);

        // 3. บันทึกประวัติลงตาราง history
        $sqlHist = "INSERT INTO room_history (room_id, temp, humid, pm, odor) VALUES (?,?,?,?,?)";
        $pdo->prepare($sqlHist)->execute([$room_id, $temp, $humid, $pm, $odor]);

        echo json_encode(["status" => "success", "room" => $room_id]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "No room_id received", "received_debug" => $_POST]);
}
?>