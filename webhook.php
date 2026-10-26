<?php
/**
 * webhook.php — نقطة استقبال موحّدة لثلاث منصات ميتا:
 *   • WhatsApp Business API   (object = whatsapp_business_account)
 *   • Facebook Messenger      (object = page)
 *   • Instagram Direct        (object = instagram, أو page كصيغة قديمة)
 * ─────────────────────────────────────────────────────────────────────────
 * ليش موحّد؟
 *   نفس الرابط يُسجَّل بالثلاث منتجات بلوحة Meta for Developers
 *   (كل منتج إله Verify Token مختلف بالـ .env: WA_VERIFY_TOKEN /
 *   FB_VERIFY_TOKEN / IG_VERIFY_TOKEN) — والملف بيوجّه داخلياً حسب
 *   نوع الـ payload بدل ما يكون في 3 ملفات بمنطق مكرر.
 *
 * إعداد بميتا (Facebook Developers → App → Webhooks):
 *   Callback URL (لكل من WhatsApp / Messenger / Instagram) =
 *       https://YOUR_DOMAIN/whatsapp-bot/webhook.php
 *   Verify Token: نفس قيمة *_VERIFY_TOKEN الخاصة بكل منتج
 *   Subscribe to:
 *       WhatsApp   → messages
 *       Messenger  → messages, messaging_postbacks
 *       Instagram  → messages
 */

require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/includes/Agent.php';

header('Content-Type: application/json; charset=utf-8');

// ── GET: التحقق من الـ Webhook (Meta بترسله عند حفظ الإعدادات) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['hub_verify_token'] ?? '';

    $knownTokens = [
        WA_VERIFY_TOKEN,
        defined('FB_VERIFY_TOKEN') ? FB_VERIFY_TOKEN : '',
        defined('IG_VERIFY_TOKEN') ? IG_VERIFY_TOKEN : '',
    ];

    if (($_GET['hub_mode'] ?? '') === 'subscribe' && $token !== '' && in_array($token, $knownTokens, true)) {
        http_response_code(200);
        echo $_GET['hub_challenge'] ?? '';
    } else {
        http_response_code(403);
        echo 'Forbidden';
    }
    exit;
}

// ── POST فقط ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = (string)file_get_contents('php://input');
$input   = json_decode($rawBody, true);
$object  = is_array($input) ? ($input['object'] ?? '') : '';

// ── التحقق من توقيع Meta (اختياري - فعّله بربط App Secret الصحيح لكل منصة) ─
// معطّل حالياً (زي ما كان بالملفات الأصلية) لتخطي قيود بعض السيرفرات المشتركة.
// لو حبيت تفعّله، اختر الـ App Secret المناسب حسب $object قبل المقارنة:
/*
$secretByObject = [
    'whatsapp_business_account' => WA_APP_SECRET,
    'instagram'                 => defined('IG_APP_SECRET') ? IG_APP_SECRET : '',
    'page'                      => defined('FB_APP_SECRET') ? FB_APP_SECRET : '',
];
$appSecret = $secretByObject[$object] ?? '';
if ($appSecret !== '') {
    $sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expected  = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
    if ($sigHeader === '' || !hash_equals($expected, $sigHeader)) {
        http_response_code(403);
        error_log('[webhook] Invalid signature from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
*/

// ── ارجع 200 بسرعة لكل الحالات (Meta بتعيد المحاولة/توقف الاشتراك لو تأخرنا) ─
http_response_code(200);
echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_end_flush();
    flush();
}

if (!is_array($input)) { error_log('[webhook] Invalid JSON body'); exit; }

// ── التوجيه حسب نوع المنصة ──────────────────────────────────────────────
switch ($object) {

    case 'whatsapp_business_account':
        handleWhatsAppInput($input);
        break;

    case 'instagram':
        foreach ($input['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                processInstagramEvent($event);
            }
        }
        break;

    case 'page':
        // ملاحظة: كل من Messenger و(الصيغة القديمة لـ)Instagram يرسلان
        // object = "page". نميّز بينهم بمطابقة entry.id مع IG_PAGE_ID —
        // إذا طابق، فهي رسالة انستقرام، وإلا فهي ماسنجر (الافتراضي).
        $igPageId = defined('IG_PAGE_ID') ? IG_PAGE_ID : '';

        foreach ($input['entry'] ?? [] as $entry) {
            $isInstagram = $igPageId !== '' && ($entry['id'] ?? '') === $igPageId;

            foreach ($entry['messaging'] ?? [] as $event) {
                if ($isInstagram) {
                    processInstagramEvent($event);
                } else {
                    processMessengerEvent($event);
                }
            }
        }
        break;

    default:
        error_log('[webhook] Unrecognized object type: ' . $object);
        break;
}

// ═════════════════════════════════════════════════════════════════════════
//  WhatsApp
// ═════════════════════════════════════════════════════════════════════════

function handleWhatsAppInput(array $input): void
{
    $value = $input['entry'][0]['changes'][0]['value'] ?? null;
    if (!is_array($value)) return;

    if (!empty($value['statuses']) && empty($value['messages'])) return;

    $msgData = $value['messages'][0] ?? null;
    if (!is_array($msgData)) return;

    $phone = $msgData['from'] ?? '';
    $waId  = $msgData['id']   ?? null;
    $type  = $msgData['type'] ?? 'text';

    if ($phone === '' || $waId === null) return;

    // ── deduplication ────────────────────────────────────────────────
    $lockFile = sys_get_temp_dir() . '/wa_msg_' . preg_replace('/\W/', '', $waId) . '.lock';
    if (file_exists($lockFile)) { error_log("[webhook] Duplicate waId=$waId"); return; }
    file_put_contents($lockFile, time());
    register_shutdown_function(fn() => @unlink($lockFile));

    // ── استخراج محتوى الرسالة ────────────────────────────────────────
    $text    = '';
    $mediaId = null;

    switch ($type) {
        case 'text':
            $text = trim($msgData['text']['body'] ?? '');
            break;

        case 'audio':
        case 'voice':
            $mediaId = $msgData[$type]['id'] ?? null;
            $type    = 'audio';
            break;

        case 'image':
            $mediaId = $msgData['image']['id'] ?? null;
            $text    = trim($msgData['image']['caption'] ?? 'أرسل صورة');
            $type    = 'image';
            break;

        case 'location':
            $text = json_encode([
                'lat'     => $msgData['location']['latitude']  ?? null,
                'lng'     => $msgData['location']['longitude'] ?? null,
                'name'    => $msgData['location']['name']      ?? '',
                'address' => $msgData['location']['address']   ?? '',
            ]);
            $type = 'location';
            break;

        case 'button':
            $text = trim($msgData['button']['text'] ?? '');
            $type = 'text';
            break;

        case 'interactive':
            $text = trim(
                $msgData['interactive']['button_reply']['id']
                ?? $msgData['interactive']['list_reply']['id']
                ?? $msgData['interactive']['button_reply']['title']
                ?? $msgData['interactive']['list_reply']['title']
                ?? ''
            );
            $type = 'text';
            break;

        default:
            $text = "[رسالة من نوع {$type}]";
            $type = 'text';
            break;
    }

    // ── فحص global_bot_status ────────────────────────────────────────
    try {
        $db        = getDB();
        $botStatus = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'global_bot_status' LIMIT 1")
                        ->fetchColumn();

        if ($botStatus === 'off') {
            $custStmt = $db->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
            $custStmt->execute([$phone]);
            $customerId = $custStmt->fetchColumn();

            if (!$customerId) {
                $db->prepare("INSERT INTO customers (phone, created_at) VALUES (?, NOW())")
                   ->execute([$phone]);
                $customerId = $db->lastInsertId();
            }

            $convStmt = $db->prepare("
                SELECT id FROM conversations
                WHERE customer_id = ? AND status != 'closed'
                ORDER BY id DESC LIMIT 1
            ");
            $convStmt->execute([$customerId]);
            $conversationId = $convStmt->fetchColumn();

            if (!$conversationId) {
                $db->prepare("
                    INSERT INTO conversations (customer_id, status, bot_paused, last_msg_at)
                    VALUES (?, 'pending', 1, NOW())
                ")->execute([$customerId]);
                $conversationId = $db->lastInsertId();
            }

            $db->prepare("
                INSERT INTO messages (conversation_id, sender, msg_type, body)
                VALUES (?, 'customer', ?, ?)
            ")->execute([$conversationId, $type, $text]);

            $db->prepare("
                UPDATE conversations SET last_msg_at = NOW() WHERE id = ?
            ")->execute([$conversationId]);

            error_log('[webhook] Global bot is OFF — message saved for dashboard, phone=' . $phone);
            return;
        }
    } catch (Throwable $e) {
        error_log('[webhook] Failed to check/save global_bot_status: ' . $e->getMessage());
    }

    // ── تتبّع آخر ظهور لأرقام المالك (لصالح نافذة الـ24 ساعة) ──────────
    $ownersListForTracking = array_map('trim', explode(',', OWNER_PHONE));
    if (in_array($phone, $ownersListForTracking, true)) {
        try {
            $dbTrack = getDB();
            $dbTrack->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = NOW()
            ")->execute(["owner_last_msg_at:$phone"]);
        } catch (Throwable $e) {
            error_log('[webhook] Failed to update owner_last_msg_at: ' . $e->getMessage());
        }
    }

    // ── رد المالك بصيغة رد#ID ──────────────────────────────────────────
    if (in_array($phone, $ownersListForTracking, true) && preg_match('/^رد#(\d+):\s*(.+)/su', trim($text), $m)) {
        try {
            $agent  = new Agent();
            $result = $agent->sendOwnerAnswer((int)$m[1], trim($m[2]));
            error_log('[webhook] Owner reply — notifId=' . $m[1] . ' ok=' . ($result['ok'] ? '1' : '0'));
        } catch (Throwable $e) {
            error_log('[webhook] Owner reply failed: ' . $e->getMessage());
        }
        return;
    }

    // ── تشغيل الـ Agent ───────────────────────────────────────────────
    try {
        $agent = new Agent();
        $agent->handle([
            'phone'    => $phone,
            'text'     => $text,
            'type'     => $type,
            'media_id' => $mediaId,
            'wa_id'    => $waId,
        ]);
    } catch (Throwable $e) {
        error_log('[webhook] Agent::handle failed — waId=' . $waId . ' : ' . $e->getMessage());
    }
}

// ═════════════════════════════════════════════════════════════════════════
//  Instagram
// ═════════════════════════════════════════════════════════════════════════

function processInstagramEvent(array $event): void
{
    $igsid = $event['sender']['id'] ?? '';
    if ($igsid === '') return;

    if (!empty($event['message']['is_echo'])) return;
    if (isset($event['delivery']) || isset($event['read'])) return;

    $mid = $event['message']['mid'] ?? ($event['postback']['mid'] ?? uniqid('ig_', true));

    // ── deduplication ────────────────────────────────────────────────
    $lockFile = sys_get_temp_dir() . '/ig_msg_' . preg_replace('/\W/', '', $mid) . '.lock';
    if (file_exists($lockFile)) { error_log("[webhook] Duplicate IG mid=$mid"); return; }
    file_put_contents($lockFile, time());
    register_shutdown_function(fn() => @unlink($lockFile));

    $text    = '';
    $type    = 'text';
    $mediaId = null;

    if (isset($event['message']['text'])) {
        $text = trim($event['message']['text']);
        $type = 'text';
    } elseif (!empty($event['message']['attachments'])) {
        $att     = $event['message']['attachments'][0];
        $attType = $att['type'] ?? 'file';
        $url     = $att['payload']['url'] ?? null;

        if (in_array($attType, ['image', 'story_mention'], true) && $url) {
            $type    = 'image';
            $mediaId = $url;
            $text    = 'أرسل صورة';
        } elseif ($attType === 'audio' && $url) {
            $type    = 'audio';
            $mediaId = $url;
        } else {
            $text = "[رسالة من نوع {$attType}]";
            $type = 'text';
        }
    } elseif (isset($event['postback']['payload'])) {
        $text = trim($event['postback']['payload']);
        $type = 'text';
    } else {
        return;
    }

    // ── فحص global_bot_status الخاص بانستقرام ───────────────────────
    try {
        $db        = getDB();
        $botStatus = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'global_bot_status:instagram' LIMIT 1");
        $botStatus->execute();
        $status = $botStatus->fetchColumn();

        if ($status === 'off') {
            $custStmt = $db->prepare("SELECT id FROM customers WHERE phone = ? AND channel = 'instagram' LIMIT 1");
            $custStmt->execute([$igsid]);
            $customerId = $custStmt->fetchColumn();

            if (!$customerId) {
                $db->prepare("INSERT INTO customers (phone, channel, created_at) VALUES (?, 'instagram', NOW())")
                   ->execute([$igsid]);
                $customerId = $db->lastInsertId();
            }

            $convStmt = $db->prepare("SELECT id FROM conversations WHERE customer_id = ? AND status != 'closed' ORDER BY id DESC LIMIT 1");
            $convStmt->execute([$customerId]);
            $conversationId = $convStmt->fetchColumn();

            if (!$conversationId) {
                $db->prepare("INSERT INTO conversations (customer_id, channel, status, bot_paused, last_msg_at) VALUES (?, 'instagram', 'pending', 1, NOW())")
                   ->execute([$customerId]);
                $conversationId = $db->lastInsertId();
            }

            $db->prepare("INSERT INTO messages (conversation_id, sender, msg_type, body) VALUES (?, 'customer', ?, ?)")
               ->execute([$conversationId, $type, $text]);
            $db->prepare("UPDATE conversations SET last_msg_at = NOW() WHERE id = ?")->execute([$conversationId]);

            error_log('[webhook] IG bot is OFF — message saved, igsid=' . $igsid);
            return;
        }
    } catch (Throwable $e) {
        error_log('[webhook] Failed to check/save IG bot status: ' . $e->getMessage());
    }

    // ── تشغيل الـ Agent بقناة 'instagram' ────────────────────────────
    try {
        $agent = new Agent('instagram');
        $agent->handle([
            'phone'    => $igsid,
            'text'     => $text,
            'type'     => $type,
            'media_id' => $mediaId,
            'wa_id'    => $mid,
        ], 'instagram');
    } catch (Throwable $e) {
        error_log('[webhook] IG Agent::handle failed — mid=' . $mid . ' : ' . $e->getMessage());
    }
}

// ═════════════════════════════════════════════════════════════════════════
//  Messenger
// ═════════════════════════════════════════════════════════════════════════

function processMessengerEvent(array $event): void
{
    $psid = $event['sender']['id'] ?? '';
    if ($psid === '') return;

    if (!empty($event['message']['is_echo'])) return;
    if (isset($event['delivery']) || isset($event['read'])) return;

    $mid = $event['message']['mid'] ?? ($event['postback']['mid'] ?? uniqid('mgr_', true));

    // ── deduplication ────────────────────────────────────────────────
    $lockFile = sys_get_temp_dir() . '/mgr_msg_' . preg_replace('/\W/', '', $mid) . '.lock';
    if (file_exists($lockFile)) { error_log("[webhook] Duplicate Messenger mid=$mid"); return; }
    file_put_contents($lockFile, time());
    register_shutdown_function(fn() => @unlink($lockFile));

    $text    = '';
    $type    = 'text';
    $mediaId = null;

    if (isset($event['message']['text'])) {
        $text = trim($event['message']['text']);
        $type = 'text';
    } elseif (!empty($event['message']['attachments'])) {
        $att     = $event['message']['attachments'][0];
        $attType = $att['type'] ?? 'file';
        $url     = $att['payload']['url'] ?? null;

        if ($attType === 'image' && $url) {
            $type    = 'image';
            $mediaId = $url;
            $text    = 'أرسل صورة';
        } elseif ($attType === 'audio' && $url) {
            $type    = 'audio';
            $mediaId = $url;
        } else {
            $text = "[رسالة من نوع {$attType}]";
            $type = 'text';
        }
    } elseif (isset($event['postback']['payload'])) {
        $text = trim($event['postback']['payload']);
        $type = 'text';
    } else {
        return;
    }

    // ── فحص global_bot_status الخاص بماسنجر ───────────────────────────
    try {
        $db        = getDB();
        $botStatus = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'global_bot_status:messenger' LIMIT 1");
        $botStatus->execute();
        $status = $botStatus->fetchColumn();

        if ($status === 'off') {
            $custStmt = $db->prepare("SELECT id FROM customers WHERE phone = ? AND channel = 'messenger' LIMIT 1");
            $custStmt->execute([$psid]);
            $customerId = $custStmt->fetchColumn();

            if (!$customerId) {
                $db->prepare("INSERT INTO customers (phone, channel, created_at) VALUES (?, 'messenger', NOW())")
                   ->execute([$psid]);
                $customerId = $db->lastInsertId();
            }

            $convStmt = $db->prepare("SELECT id FROM conversations WHERE customer_id = ? AND status != 'closed' ORDER BY id DESC LIMIT 1");
            $convStmt->execute([$customerId]);
            $conversationId = $convStmt->fetchColumn();

            if (!$conversationId) {
                $db->prepare("INSERT INTO conversations (customer_id, channel, status, bot_paused, last_msg_at) VALUES (?, 'messenger', 'pending', 1, NOW())")
                   ->execute([$customerId]);
                $conversationId = $db->lastInsertId();
            }

            $db->prepare("INSERT INTO messages (conversation_id, sender, msg_type, body) VALUES (?, 'customer', ?, ?)")
               ->execute([$conversationId, $type, $text]);
            $db->prepare("UPDATE conversations SET last_msg_at = NOW() WHERE id = ?")->execute([$conversationId]);

            error_log('[webhook] Messenger bot is OFF — message saved, psid=' . $psid);
            return;
        }
    } catch (Throwable $e) {
        error_log('[webhook] Failed to check/save Messenger bot status: ' . $e->getMessage());
    }

    // ── تشغيل الـ Agent بقناة 'messenger' ───────────────────────────────
    try {
        $agent = new Agent('messenger');
        $agent->handle([
            'phone'    => $psid,
            'text'     => $text,
            'type'     => $type,
            'media_id' => $mediaId,
            'wa_id'    => $mid,
        ], 'messenger');
    } catch (Throwable $e) {
        error_log('[webhook] Messenger Agent::handle failed — mid=' . $mid . ' : ' . $e->getMessage());
    }
}
