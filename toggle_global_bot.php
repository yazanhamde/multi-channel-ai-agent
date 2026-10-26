<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

try {
    // الاتصال بقاعدة البيانات
    $db = getDB();
    
    // معرفة نوع الطلب (GET أو POST)
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ─── ١. في حال كان الطلب GET (جلب حالة البوت) ───────────────────────────
    if ($method === 'GET') {
        $stmt = $db->prepare("
            SELECT setting_value 
            FROM settings 
            WHERE setting_key = 'global_bot_status' 
            LIMIT 1
        ");
        $stmt->execute();
        $currentStatus = $stmt->fetchColumn();

        // تحويل الحالة إلى Boolean (إذا كانت القيمة لا تساوي 'off' يعني البوت شغال true)
        $isActive = ($currentStatus !== 'off');

        echo json_encode([
            'bot_is_active' => $isActive
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── ٢. في حال كان الطلب POST (تحديث حالة البوت - الكود القديم) ──────────────
    if ($method === 'POST') {
        // استقبال البيانات القادمة من الـ Dashboard
        $data = json_decode(file_get_contents('php://input'), true);

        // تحديد حالة البوت بناءً على القيمة المرسلة
        $status = (
            isset($data['status']) && 
            $data['status'] === 'off'
        ) ? 'off' : 'on';

        // تحديث حالة البوت العامة
        $stmt = $db->prepare("
            UPDATE settings 
            SET setting_value = ? 
            WHERE setting_key = 'global_bot_status'
        ");
        $stmt->execute([$status]);

        // نجاح عملية التحديث
        echo json_encode([
            'status'     => 'success',
            'bot_status' => $status,
            'message'    => 'تم تحديث مفتاح الطوارئ بنجاح'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // في حال تم استخدام Method غير مدعومة
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Method Not Allowed'
    ]);

} catch (Throwable $e) {
    // فشل العملية
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>