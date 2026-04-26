<?php
// ไฟล์: api/api.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
include_once 'db.php'; 

$action = $_GET['action'] ?? '';

// ==========================================
// 1. ระบบจัดการสมาชิก (Auth: Register / Login / Logout)
// ==========================================
if ($action === 'register') {
    $data = json_decode(file_get_contents("php://input"));
    $hash = password_hash($data->password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$data->username, $hash]);
        
        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = $data->username;
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        $errorMsg = ($e->getCode() == 23000) ? 'ชื่อนี้มีคนใช้แล้ว' : $e->getMessage();
        echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    }
}
elseif ($action === 'login') {
    $data = json_decode(file_get_contents("php://input"));
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$data->username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($data->password, $user['password_hash'])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = $user['username'];
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
    }
}
elseif ($action === 'check_auth') {
    echo json_encode(['status' => isset($_SESSION['logged_in']) ? 'success' : 'error']);
}
elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(['status' => 'success']);
}

// ==========================================
// 2. ระบบจัดการรหัสผ่าน (Reset / Change Password)
// ==========================================
elseif ($action === 'reset_password_direct') {
    $data = json_decode(file_get_contents("php://input"));
    $hash = password_hash($data->new_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$hash, $data->username]);
    
    echo json_encode(['status' => ($stmt->rowCount() > 0) ? 'success' : 'error', 'message' => 'ไม่พบผู้ใช้งานนี้ในระบบ']);
}
elseif ($action === 'change_password') {
    if (!isset($_SESSION['user'])) { 
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); 
        exit; 
    }
    
    $data = json_decode(file_get_contents("php://input"));
    $hash = password_hash($data->new_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$hash, $_SESSION['user']]);
    echo json_encode(['status' => 'success']);
}

// ==========================================
// 3. ระบบดึงข้อมูลแสดงผล (Dashboard / Rooms)
// ==========================================
elseif ($action === 'dashboard' || $action === 'rooms') {
    $stmt = $pdo->query("SELECT * FROM rooms_latest ORDER BY room_id ASC");
    $rooms = $stmt->fetchAll();
    
    if ($action === 'rooms') {
        echo json_encode($rooms);
    } else {
        $sql_chart = "SELECT HOUR(created_at) as hour, AVG(pm) as avg_pm 
                      FROM room_history 
                      WHERE created_at >= NOW() - INTERVAL 24 HOUR 
                      GROUP BY HOUR(created_at) 
                      ORDER BY hour ASC";
        $chart = $pdo->query($sql_chart)->fetchAll();
        echo json_encode(['rooms' => $rooms, 'chart' => $chart]);
    }
}
elseif ($action === 'room_detail') {
    $room = $_GET['room'] ?? '';
    
    $latest = $pdo->prepare("SELECT * FROM rooms_latest WHERE room_id = ?");
    $latest->execute([$room]);
    
    $history = $pdo->prepare("SELECT * FROM room_history WHERE room_id = ? ORDER BY id DESC LIMIT 15");
    $history->execute([$room]);
    
    echo json_encode([
        'latest' => $latest->fetch(), 
        'history' => array_reverse($history->fetchAll())
    ]);
}

// ==========================================
// 4. ระบบตั้งค่าและจัดการห้อง (Settings)
// ==========================================
elseif ($action === 'get_limits') {
    $room = $_GET['room'] ?? 'global';
    $stmt = $pdo->prepare("SELECT * FROM system_settings WHERE room_id = ?");
    $stmt->execute([$room]);
    $data = $stmt->fetch();
    
    echo json_encode(['status' => 'success', 'data' => $data]);
}
elseif ($action === 'save_setting' || $action === 'save_limits') {
    $data = json_decode(file_get_contents("php://input"), true);
    $room = $data['room'] ?? 'global';
    
    $pm = $data['limit_pm'] ?? $data['pm'] ?? 0;
    $humid = $data['limit_humid'] ?? $data['humid'] ?? 0;
    $temp = $data['limit_temp'] ?? $data['temp'] ?? 0;
    $odor = $data['limit_odor'] ?? $data['odor'] ?? 0;
    
    $sql = "INSERT INTO system_settings (room_id, limit_pm, limit_humid, limit_temp, limit_odor) 
            VALUES (?,?,?,?,?) 
            ON DUPLICATE KEY UPDATE limit_pm=?, limit_humid=?, limit_temp=?, limit_odor=?";
            
    $pdo->prepare($sql)->execute([
        $room, $pm, $humid, $temp, $odor,
        $pm, $humid, $temp, $odor
    ]);
    
    echo json_encode(['status' => 'success']);
}
elseif ($action === 'add_room') {
    $data = json_decode(file_get_contents("php://input"));
    $stmt = $pdo->prepare("INSERT IGNORE INTO rooms_latest (room_id, status_text, status_color) VALUES (?, 'รอข้อมูล...', 'secondary')");
    $stmt->execute([$data->room]);
    echo json_encode(['status' => 'success']);
}
elseif ($action === 'delete_rooms') {
    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->rooms)) {
        echo json_encode(['status' => 'error', 'message' => 'No rooms selected']);
        exit;
    }
    
    $inQuery = implode(',', array_fill(0, count($data->rooms), '?'));
    $pdo->prepare("DELETE FROM rooms_latest WHERE room_id IN ($inQuery)")->execute($data->rooms);
    $pdo->prepare("DELETE FROM room_history WHERE room_id IN ($inQuery)")->execute($data->rooms);
    echo json_encode(['status' => 'success']);
}
else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
}
?>