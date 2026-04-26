<?php
// ไฟล์: api/webhook.php
include_once 'db.php';

$access_token = 'v/FwOKnoyMh1TJWHQnBELQLFuUzdItYZpVsHHHF6mJaVees4PKKDmzIdaAGu1luAAHl5CEIjcXBQuwm7TExHnDU2zLmzyd41tQDRUGSFEcKdxkfp1BAZ+ZVFkDxmdRu3O2ZT4HMsHAABzcTUv/P0pAdB04t89/1O/w1cDnyilFU=';
$weather_api_key = '037e1594d0076b4a018e6b0425ebfa3d'; // อย่าลืมใส่คีย์สภาพอากาศด้วยครับ

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        $replyToken = $event['replyToken'];
        $user_id = $event['source']['userId'];

        // 🟢 1. เมื่อกดเพิ่มเพื่อน (Follow) -> ส่งบทความต้อนรับ
        if ($event['type'] == 'follow') {
            $replyText = "สวัสดีครับ! ผมคือ AirQ AI Assistant 🤖✨\n\nยินดีที่ได้รู้จักนะครับ \n\n📌 ";
        }

       

        // 🟢 3. พิมพ์ถามปกติ (Text Message)
        elseif ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            
            // 📌 3.1 ถ้าพิมพ์คำว่า "ห้อง ..."
            if (preg_match('/^ห้อง\s*(.+)$/u', $text, $matches)) {
                $room_id = $matches[1];
                $stmt = $pdo->prepare("SELECT * FROM rooms_latest WHERE room_id = ?");
                $stmt->execute([$room_id]);
                $room = $stmt->fetch();
                if ($room) {
                    $status_emoji = ($room['status_color'] == 'danger') ? '🔴' : (($room['status_color'] == 'warning') ? '🟡' : '🟢');
                    $replyText = "🏠 ข้อมูลห้อง: {$room['room_id']}\n" .
                                 "{$status_emoji} สถานะ: {$room['status_text']}\n" .
                                 "🌫️ ฝุ่น PM 2.5: {$room['pm']} µg/m³\n" .
                                 "🌡️ อุณหภูมิ: {$room['temp']}°C\n" .
                                 "💧 ความชื้น: {$room['humid']}%\n" .
                                 "💨 ระดับกลิ่น: {$room['odor']} ppm";
                } else {
                    $replyText = "❌ ไม่พบข้อมูลห้อง '$room_id'";
                }
            }
            
            // 📌 3.2 ถ้าพิมพ์คำว่า "ดูห้องทั้งหมด"
            elseif ($text == 'ดูห้องทั้งหมด' || $text == 'all rooms') {
                $stmt = $pdo->query("SELECT room_id FROM rooms_latest ORDER BY room_id ASC");
                $rooms = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if ($rooms) {
                    $replyText = "📂 รายชื่อห้องทั้งหมดในระบบ:\n• " . implode("\n• ", $rooms) . "\n\n💡 พิมพ์ 'ห้อง ตามด้วยชื่อ' เพื่อดูรายละเอียดครับ";
                } else {
                    $replyText = "ยังไม่มีการเพิ่มห้องในระบบครับ";
                }
            }

            // 📌 3.3 ถ้าพิมพ์คำว่า "อากาศ ..." 
            elseif (preg_match('/^(สภาพอากาศ|อากาศ)\s*(.+)$/u', $text, $matches)) {
                $province = trim($matches[2]);

                $thai_city_map = [
                        // ภาคกลาง
                        "กรุงเทพ" => "Bangkok", "กรุงเทพมหานคร" => "Bangkok", "กำแพงเพชร" => "Kamphaeng Phet",
                        "ชัยนาท" => "Chai Nat", "นครนายก" => "Nakhon Nayok", "นครปฐม" => "Nakhon Pathom",
                        "นครสวรรค์" => "Nakhon Sawan", "นนทบุรี" => "Nonthaburi", "ปทุมธานี" => "Pathum Thani",
                        "พระนครศรีอยุธยา" => "Phra Nakhon Si Ayutthaya", "อยุธยา" => "Phra Nakhon Si Ayutthaya",
                        "พิจิตร" => "Phichit", "พิษณุโลก" => "Phitsanulok", "เพชรบูรณ์" => "Phetchabun",
                        "ลพบุรี" => "Lop Buri", "สมุทรปราการ" => "Samut Prakan", "สมุทรสงคราม" => "Samut Songkhram",
                        "สมุทรสาคร" => "Samut Sakhon", "สระบุรี" => "Saraburi", "สิงห์บุรี" => "Sing Buri",
                        "สุโขทัย" => "Sukhothai", "สุพรรณบุรี" => "Suphan Buri", "อ่างทอง" => "Ang Thong",
                        "อุทัยธานี" => "Uthai Thani",

                        // ภาคเหนือ
                        "เชียงราย" => "Chiang Rai", "เชียงใหม่" => "Chiang Mai", "น่าน" => "Nan",
                        "พะเยา" => "Phayao", "แพร่" => "Phrae", "แม่ฮ่องสอน" => "Mae Hong Son",
                        "ลำปาง" => "Lampang", "ลำพูน" => "Lamphun", "อุตรดิตถ์" => "Uttaradit",

                        // ภาคตะวันออกเฉียงเหนือ (อีสาน)
                        "กาฬสินธุ์" => "Kalasin", "ขอนแก่น" => "Khon Kaen", "ชัยภูมิ" => "Chaiyaphum",
                        "นครพนม" => "Nakhon Phanom", "นครราชสีมา" => "Nakhon Ratchasima", "โคราช" => "Nakhon Ratchasima",
                        "บึงกาฬ" => "Bueng Kan", "บุรีรัมย์" => "Buri Ram", "มหาสารคาม" => "Maha Sarakham",
                        "มุกดาหาร" => "Mukdahan", "ยโสธร" => "Yasothon", "ร้อยเอ็ด" => "Roi Et",
                        "เลย" => "Loei", "ศรีสะเกษ" => "Si Sa Ket", "สกลนคร" => "Sakon Nakhon",
                        "สุรินทร์" => "Surin", "หนองคาย" => "Nong Khai", "หนองบัวลำภู" => "Nong Bua Lam Phu",
                        "อำนาจเจริญ" => "Amnat Charoen", "อุดรธานี" => "Udon Thani", "อุบลราชธานี" => "Ubon Ratchathani",

                        // ภาคตะวันออก
                        "จันทบุรี" => "Chanthaburi", "ฉะเชิงเทรา" => "Chachoengsao", "ชลบุรี" => "Chon Buri",
                        "พัทยา" => "Pattaya", "ตราด" => "Trat", "ปราจีนบุรี" => "Prachin Buri",
                        "ระยอง" => "Rayong", "สระแก้ว" => "Sa Kaeo",

                        // ภาคตะวันตก
                        "กาญจนบุรี" => "Kanchanaburi", "ตาก" => "Tak", "ประจวบคีรีขันธ์" => "Prachuap Khiri Khan",
                        "เพชรบุรี" => "Phetchaburi", "ราชบุรี" => "Ratchaburi",

                        // ภาคใต้
                        "กระบี่" => "Krabi", "ชุมพร" => "Chumphon", "ตรัง" => "Trang",
                        "นครศรีธรรมราช" => "Nakhon Si Thammarat", "นราธิวาส" => "Narathiwat", "ปัตตานี" => "Pattani",
                        "พังงา" => "Phang Nga", "พัทลุง" => "Phatthalung", "ภูเก็ต" => "Phuket",
                        "ยะลา" => "Yala", "ระนอง" => "Ranong", "สงขลา" => "Songkhla",
                        "หาดใหญ่" => "Hat Yai", "สตูล" => "Satun", "สุราษฎร์ธานี" => "Surat Thani"
                    ];
                    // ถ้ามีชื่อในรายการที่กำหนดไว้ ให้เปลี่ยนเป็นภาษาอังกฤษเพื่อให้ API แม่นยำขึ้น
                    if (isset($thai_city_map[$province])) {
                        $search_city = $thai_city_map[$province];
                    } else {
                        $search_city = $province;
                    }
    
                $weather_url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($search_city) . ",TH&appid=$weather_api_key&units=metric&lang=th";
                
                $ch_w = curl_init();
                curl_setopt($ch_w, CURLOPT_URL, $weather_url);
                curl_setopt($ch_w, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch_w, CURLOPT_SSL_VERIFYPEER, false); 
                curl_setopt($ch_w, CURLOPT_TIMEOUT, 5); 
                $weather_response = curl_exec($ch_w);
                curl_close($ch_w);
                
                if ($weather_response !== false) {
                    $weather_data = json_decode($weather_response, true);
                    
                    if (isset($weather_data['cod']) && $weather_data['cod'] == 200) {
                        $temp = $weather_data['main']['temp'];
                        $desc = $weather_data['weather'][0]['description'];
                        $humid = $weather_data['main']['humidity'];
                        $city = $weather_data['name']; 

                        $replyText = "☁️ สภาพอากาศ: $city\n🌡️ อุณหภูมิ: $temp °C\n💧 ความชื้น: $humid%\n📝 ท้องฟ้า: $desc";
                    } elseif (isset($weather_data['cod']) && $weather_data['cod'] == 401) {
                        $replyText = "⏳ รอสักครู่นะครับ! API Key เพิ่งสมัครใหม่ ต้องรอระบบยืนยันประมาณ 30 นาทีครับ";
                    } elseif (isset($weather_data['cod']) && $weather_data['cod'] == 404) {
                        $replyText = "❌ ดาวเทียมไม่พบชื่อเมือง '$province'\n💡 ลองพิมพ์ชื่อจังหวัดภาษาอังกฤษดูนะครับ เช่น อากาศ Ratchaburi";
                    } else {
                        $replyText = "❌ ระบบสภาพอากาศแจ้งเตือน: " . ($weather_data['message'] ?? 'ไม่ทราบสาเหตุ');
                    }
                } else {
                    $replyText = "❌ เซิร์ฟเวอร์ไม่สามารถเชื่อมต่อกับสภาพอากาศได้ครับ";
                }
            }
        }

        // ส่งข้อความกลับไปที่ LINE
        if (isset($replyText)) {
            $data = ['replyToken' => $replyToken, 'messages' => [['type' => 'text', 'text' => $replyText]]];
            $ch = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
echo "OK";
?>