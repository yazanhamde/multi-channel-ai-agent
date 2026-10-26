<?php
declare(strict_types=1);
/**
 * /api/inventory.php
 * POST ?action=sell    → بيع منتج
 * POST ?action=return  → مرتجع
 * GET  ?action=report  → جرد شهري
 * GET  ?action=sales   → كل المبيعات
 */

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api_helpers.php';

requireAuth('inventory');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$action = trim($_GET['action'] ?? '');
$body   = in_array($method, ['POST']) ? readJsonBody() : [];

try {

    // ── بيع منتج ──────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'sell') {
        $productId = (int)($body['product_id'] ?? 0);
        $qty       = max(1, (int)($body['quantity'] ?? 1));
        $soldVia   = in_array($body['sold_via'] ?? '', ['bot','manual']) ? $body['sold_via'] : 'manual';

        if ($productId === 0) respond(400, ['ok' => false, 'message' => 'product_id مطلوب']);

        $stmt = $db->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) respond(404, ['ok' => false, 'message' => 'المنتج غير موجود']);
        if ((int)$product['stock'] < $qty) {
            respond(400, ['ok' => false, 'message' => 'المخزون غير كافٍ — متوفر: ' . $product['stock']]);
        }

        $totalPrice = round((float)$product['price'] * $qty, 2);

        $db->beginTransaction();
        $db->prepare('INSERT INTO sales (product_id, quantity_sold, total_price, sold_via) VALUES (?, ?, ?, ?)')->execute([$productId, $qty, $totalPrice, $soldVia]);
        $saleId = (int)$db->lastInsertId();
        $db->prepare('UPDATE products SET stock = stock - ?, updated_at = NOW() WHERE id = ?')->execute([$qty, $productId]);
        $db->commit();

        respond(201, ['ok' => true, 'message' => 'تم تسجيل البيع', 'data' => [
            'sale_id'     => $saleId,
            'product'     => $product['name'],
            'quantity'    => $qty,
            'total_price' => $totalPrice,
            'sold_via'    => $soldVia,
        ]]);
    }

    // ── مرتجع ────────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'return') {
        $saleId = (int)($body['sale_id'] ?? 0);
        $qty    = max(1, (int)($body['quantity'] ?? 1));
        $reason = trim($body['reason'] ?? '');

        if ($saleId === 0) respond(400, ['ok' => false, 'message' => 'sale_id مطلوب']);

        $stmt = $db->prepare('SELECT * FROM sales WHERE id = ? LIMIT 1');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) respond(404, ['ok' => false, 'message' => 'عملية البيع غير موجودة']);
        if ($qty > (int)$sale['quantity_sold']) respond(400, ['ok' => false, 'message' => 'الكمية أكبر من المباعة']);

        $db->beginTransaction();
        $db->prepare('INSERT INTO returns (sale_id, product_id, quantity_returned, reason) VALUES (?, ?, ?, ?)')->execute([$saleId, $sale['product_id'], $qty, $reason]);
        $db->prepare('UPDATE products SET stock = stock + ?, updated_at = NOW() WHERE id = ?')->execute([$qty, $sale['product_id']]);
        $db->commit();

        respond(201, ['ok' => true, 'message' => 'تم تسجيل المرتجع', 'data' => [
            'sale_id'  => $saleId,
            'quantity' => $qty,
            'reason'   => $reason,
        ]]);
    }

    // ── جرد شهري ─────────────────────────────────────────────────────────
    if ($method === 'GET' && $action === 'report') {
        $month = (int)($_GET['month'] ?? date('n'));
        $year  = (int)($_GET['year']  ?? date('Y'));

        $stmt = $db->prepare("
            SELECT
                p.id, p.name, p.category, p.price,
                p.stock AS current_stock,
                COALESCE(SUM(s.quantity_sold), 0)     AS total_sold,
                COALESCE(SUM(s.total_price), 0)       AS total_revenue,
                COALESCE(SUM(r.quantity_returned), 0) AS total_returned
            FROM products p
            LEFT JOIN sales s ON s.product_id = p.id
                AND MONTH(s.sold_at) = ? AND YEAR(s.sold_at) = ?
            LEFT JOIN returns r ON r.product_id = p.id
                AND MONTH(r.returned_at) = ? AND YEAR(r.returned_at) = ?
            WHERE p.is_active = 1
            GROUP BY p.id
            ORDER BY total_sold DESC
        ");
        $stmt->execute([$month, $year, $month, $year]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        respond(200, ['ok' => true, 'data' => [
            'month'         => $month,
            'year'          => $year,
            'total_revenue' => round(array_sum(array_column($items, 'total_revenue')), 2),
            'total_sold'    => array_sum(array_column($items, 'total_sold')),
            'items'         => $items,
        ]]);
    }

    // ── كل المبيعات ───────────────────────────────────────────────────────
    if ($method === 'GET' && $action === 'sales') {
        $page    = max(1, (int)($_GET['page']     ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $stmt = $db->prepare("
            SELECT s.*, p.name AS product_name
            FROM sales s
            JOIN products p ON p.id = s.product_id
            ORDER BY s.sold_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = (int)$db->query('SELECT COUNT(*) FROM sales')->fetchColumn();

        respond(200, ['ok' => true, 'data' => [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($total / $perPage),
        ]]);
    }

    respond(400, ['ok' => false, 'message' => 'action غير صحيح — استخدم: sell, return, report, sales']);

} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[inventory API] ' . $e->getMessage());
    respond(500, ['ok' => false, 'message' => 'خطأ في السيرفر']);
}