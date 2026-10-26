<?php
/**
 * sync_products.php
 * مزامنة المنتجات من الـ POS API مع دعم صورة القطعة
 * ─────────────────────────────────────────────────────
 * يجلب: id, code, name, quantity, image/photo
 * يحسب: condition_status (جديد / مستعمل)
 */

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/db.php';

// ── إعدادات الـ API ─────────────────────────────────────────────────────────
$apiKey = 'wgswk8wgw0ksgs8cow04okg8coc04o8w0ooc0swo';
$apiUrl = 'https://pos.system2024.com/api/v1/products';
$limit  = 500;
$start  = 1;
$totalSynced = 0;
$skipped = 0;
$skippedSamples = [];

echo "<div style='font-family: Arial; padding: 20px; background: #f9f9f9; border-radius: 8px;'>";
echo "<h2>🔄 بدء مزامنة المنتجات من الـ API...</h2>";
echo "<ul style='line-height: 1.8;'>";

try {
    $db = getDB();

    // ── الاستعلام: يشمل image_url الآن ─────────────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO products (api_product_id, code, name, condition_status, image_url, quantity)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name             = VALUES(name),
            condition_status = VALUES(condition_status),
            image_url        = VALUES(image_url),
            quantity         = VALUES(quantity),
            last_sync        = NOW()
    ");

    while (true) {
        // ── طلب الـ API ──────────────────────────────────────────────────────
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$apiUrl}?limit={$limit}&start={$start}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "api-key: {$apiKey}",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            echo "<li style='color:red;'>❌ خطأ في الاتصال بالـ API. كود الاستجابة: {$httpCode}</li>";
            break;
        }

        $data     = json_decode($response, true);
        $products = $data['data'] ?? [];

        if (empty($products)) {
            echo "<li>ℹ️ لا يوجد منتجات إضافية لسحبها.</li>";
            break;
        }

        $db->beginTransaction();

        foreach ($products as $prod) {
            // 🔑 رقم المنتج: جرّب أكتر من اسم حقل (الـ API أحياناً بيرجع id فاضي أو باسم ثاني)
            $apiId = $prod['id'] ?? $prod['product_id'] ?? $prod['ID'] ?? $prod['pid'] ?? null;
            $code  = $prod['code'] ?? $prod['sku'] ?? null;
            $name  = $prod['name'] ?? null;

            // ⛔ تخطّي أي منتج ناقص بيانات أساسية بدل ما يوقف المزامنة كلها
            if ($apiId === null || $apiId === '' || !is_numeric($apiId) || $code === null || $name === null) {
                $skipped++;
                if (count($skippedSamples) < 3) {
                    $skippedSamples[] = json_encode($prod, JSON_UNESCAPED_UNICODE);
                }
                continue;
            }

            $quantity  = (int)($prod['quantity'] ?? $prod['stock'] ?? 1);

            // اكتشاف حالة القطعة من اسمها
            $condition = (mb_strpos($name, 'مستعمل') !== false)
                ? 'مستعمل ♻️'
                : 'جديد ✨';

            // ── جلب رابط الصورة ──────────────────────────────────────────────
            $imageUrl = $prod['image']
                     ?? $prod['image_url']
                     ?? $prod['photo']
                     ?? $prod['picture']
                     ?? $prod['thumbnail']
                     ?? null;

            // إذا كان الرابط نسبياً، أضف اسم السيرفر
            if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
                $imageUrl = 'https://pos.system2024.com' . $imageUrl;
            }

            $stmt->execute([
                (int)$apiId,
                $code,
                $name,
                $condition,
                $imageUrl,
                $quantity,
            ]);

            $totalSynced++;
        }

        $db->commit();

        $end = $start + count($products) - 1;
        echo "<li>✅ تم مزامنة <b>{$totalSynced}</b> منتج... (دفعة من {$start} إلى {$end})</li>";

        $totalInApi = (int)($data['total'] ?? 0);
        if ($start + $limit > $totalInApi) break;
        $start += $limit;
    }

    if ($skipped > 0) {
        echo "<li style='color:#b36b00;'>⚠️ تم تخطي <b>{$skipped}</b> منتج بدون رقم (id) أو كود/اسم. أمثلة من الـ API:</li>";
        foreach ($skippedSamples as $sm) {
            echo "<li style='direction:ltr;font-family:monospace;font-size:12px;'>" . htmlspecialchars($sm) . "</li>";
        }
    }
    echo "</ul>";
    echo "<h3 style='color:green; border-top:2px solid #ddd; padding-top:10px;'>
        🎉 تمت المزامنة! تم حفظ وتحديث <b>{$totalSynced}</b> منتج.
    </h3>";
    echo "</div>";

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo "</ul>";
    echo "<h3 style='color:red;'>❌ خطأ أثناء المزامنة: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "</div>";
}