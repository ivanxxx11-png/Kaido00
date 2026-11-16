<?php
// Đặt tại: donhang/vietqr_webhook.php
// Dùng với SePay (https://sepay.vn): cấu hình Webhook URL = https://TENMIEN/donhang/vietqr_webhook.php
// kiểu chứng thực "API Key", và điền cùng khóa đó vào vietqr_config.php -> sepay_api_key.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../db.php';
$cfg = require __DIR__ . '/../vietqr_config.php';
header('Content-Type: application/json');

if ($cfg['sepay_api_key'] === '') { http_response_code(503); exit(json_encode(['success' => false, 'message' => 'Chua cau hinh API key'])); }
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!hash_equals('Apikey ' . $cfg['sepay_api_key'], $auth)) { http_response_code(401); exit(json_encode(['success' => false])); }

$d = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($d) || !isset($d['id'])) { http_response_code(400); exit(json_encode(['success' => false])); }
if (($d['transferType'] ?? '') !== 'in') { exit(json_encode(['success' => true])); }   // bỏ qua tiền ra

$gid     = (string)$d['id'];
$amount  = (float)($d['transferAmount'] ?? 0);
$text    = trim(($d['content'] ?? '') . ' ' . ($d['code'] ?? '') . ' ' . ($d['description'] ?? ''));
$orderId = null;
if (preg_match('/' . preg_quote($cfg['prefix'], '/') . '(\d{6})/i', $text, $m)) $orderId = (int)$m[1];

try {
    $conn->begin_transaction();
    // Chống xử lý trùng (gateway có thể gọi lại)
    $text255 = mb_substr($text, 0, 500);
    $st = $conn->prepare('INSERT IGNORE INTO vietqr_giaodich (gateway_id,order_id,amount,content) VALUES (?,?,?,?)');
    $st->bind_param('sids', $gid, $orderId, $amount, $text255); $st->execute();
    if ($st->affected_rows === 0) { $conn->commit(); exit(json_encode(['success' => true])); }
    $st->close();

    if ($orderId) {
        $st = $conn->prepare('SELECT total_amount,status,payment_status,payment_method FROM orders WHERE id=? FOR UPDATE');
        $st->bind_param('i', $orderId); $st->execute();
        $o = $st->get_result()->fetch_assoc(); $st->close();

        if ($o && $o['payment_method'] === 'vietqr' && $o['payment_status'] === 'unpaid'
            && $o['status'] !== 'cancelled' && $amount + 0.01 >= (float)$o['total_amount']) {
            $st = $conn->prepare("UPDATE orders SET payment_status='paid' WHERE id=?");
            $st->bind_param('i', $orderId); $st->execute(); $st->close();

            $note = 'Đã nhận chuyển khoản VietQR ' . number_format($amount, 0, ',', '.') . 'đ';
            $st = $conn->prepare('INSERT INTO order_status_history (order_id,status,note) VALUES (?,?,?)');
            $st->bind_param('iss', $orderId, $o['status'], $note); $st->execute(); $st->close();

            $conn->query('UPDATE vietqr_giaodich SET matched=1 WHERE gateway_id=' . "'" . $conn->real_escape_string($gid) . "'");
        }
        // Thiếu tiền / đơn đã hủy / đã thanh toán: chỉ lưu log (matched=0) để admin xử lý tay
    }
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback(); error_log('vietqr_webhook: ' . $e->getMessage());
    http_response_code(500); echo json_encode(['success' => false]);
}
