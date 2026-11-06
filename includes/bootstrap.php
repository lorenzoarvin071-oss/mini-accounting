<?php
session_start();
require __DIR__ . '/../config.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $ex) {
    die('Cannot connect to the database. Import database.sql and check config.php.');
}

/* ---------- small helpers ---------- */
function opts($list, $sel = '') {   // <option> list for a drop-down; keeps an old value that is not in the list
    $h = '<option value="">Select…</option>';
    if ($sel !== '' && !in_array($sel, $list, true)) $list[] = $sel;
    foreach ($list as $o) $h .= '<option' . ($o === $sel ? ' selected' : '') . '>' . e($o) . '</option>';
    return $h;
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function peso($n) { return '₱' . number_format((float)$n, 2); }
function q($sql, $p = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($p); return $st; }
function one($sql, $p = []) { return q($sql, $p)->fetchColumn(); }
function rows($sql, $p = []) { return q($sql, $p)->fetchAll(); }
function go($url) { header('Location: ' . $url); exit; }
function setting($k) { return one('SELECT v FROM settings WHERE k = ?', [$k]); }
function valid_date($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; }
function next_ref($prefix, $pad) {   // atomic running number, e.g. OR-0001
    q('INSERT INTO counters (name, value) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)', [$prefix]);
    return $prefix . '-' . str_pad((string)one('SELECT LAST_INSERT_ID()'), $pad, '0', STR_PAD_LEFT);
}

/* ---------- first-run setup: default users ---------- */
if ((int)one('SELECT COUNT(*) FROM users') === 0) {
    foreach ([['treasurer', 'treasurer123', 'Treasurer', 'treasurer'], ['officer', 'officer123', 'Officer', 'officer'], ['adviser', 'adviser123', 'Adviser', 'adviser']] as $u) {
        q('INSERT INTO users (username, password_hash, name, role) VALUES (?,?,?,?)', [$u[0], password_hash($u[1], PASSWORD_DEFAULT), $u[2], $u[3]]);
    }
}

/* ---------- auth, roles, CSRF, flash ---------- */
function me() { return $_SESSION['user'] ?? null; }
function require_login($allowMember = false) { if (!me()) go('login.php'); if (me()['role'] === 'member' && !$allowMember) go('portal.php'); }
function can_edit() { return me() && in_array(me()['role'], ['treasurer', 'officer'], true); }
function is_treasurer() { return me() && me()['role'] === 'treasurer'; }
function csrf_field() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function guard_post($needEdit = true) {  // call at the top of any page that handles a form
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) die('Invalid form token. Go back and try again.');
    if ($needEdit && !can_edit()) die('Your role is view-only.');
    return true;
}
function flash($msg, $bad = false) { $_SESSION['flash'] = [$msg, $bad]; }
function audit($action, $details = '') {
    q('INSERT INTO audit_log (username, action, details) VALUES (?,?,?)', [me()['username'] ?? '-', $action, $details]);
}

/* ---------- accounting logic ---------- */
// Cash effect of every transaction. Expenses charged to a cash advance are 0 because the cash left when the advance was issued.
const CASH_SQL = "SUM(CASE type WHEN 'PAYMENT' THEN amount WHEN 'RETURN' THEN amount WHEN 'ADVANCE' THEN -amount WHEN 'REIMBURSE' THEN -amount WHEN 'EXPENSE' THEN IF(advance_ref IS NULL, -amount, 0) ELSE 0 END)";

function sum_type($t) { return (float)one("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type = ? AND voided = 0", [$t]); }
function totals() {
    $assessed = (float)one('SELECT COALESCE(SUM(fee),0) FROM members');
    $collected = sum_type('PAYMENT');
    return ['assessed' => $assessed, 'collected' => $collected, 'outstanding' => $assessed - $collected,
        'adv' => sum_type('ADVANCE'), 'exp' => sum_type('EXPENSE'), 'returned' => sum_type('RETURN'), 'reimb' => sum_type('REIMBURSE'),
        'cash' => (float)one('SELECT COALESCE(' . CASH_SQL . ',0) FROM transactions WHERE voided = 0')];
}
function mstatus($fee, $paid) { return ($paid <= 0 && $fee > 0) ? 'Unpaid' : ($paid >= $fee ? 'Paid' : 'Partial'); }
function members_with_paid($search = '') {
    return rows("SELECT m.*, COALESCE(p.paid,0) AS paid, (SELECT username FROM users WHERE member_id = m.id LIMIT 1) AS username FROM members m
        LEFT JOIN (SELECT member_id, SUM(amount) paid FROM transactions WHERE type='PAYMENT' AND voided=0 GROUP BY member_id) p ON p.member_id = m.id
        WHERE CONCAT(m.name, m.member_code, COALESCE(m.student_id,''), m.course) LIKE ? ORDER BY m.id", ['%' . $search . '%']);
}
function advances() {   // each advance with spent / returned / reimbursed and what is still due
    $list = rows("SELECT a.*,
        COALESCE((SELECT SUM(amount) FROM transactions WHERE type='EXPENSE'   AND voided=0 AND advance_ref = a.ref),0) AS spent,
        COALESCE((SELECT SUM(amount) FROM transactions WHERE type='RETURN'    AND voided=0 AND advance_ref = a.ref),0) AS ret,
        COALESCE((SELECT SUM(amount) FROM transactions WHERE type='REIMBURSE' AND voided=0 AND advance_ref = a.ref),0) AS reimb
        FROM transactions a WHERE a.type = 'ADVANCE' AND a.voided = 0 ORDER BY a.id");
    foreach ($list as &$a) {
        $a['due'] = round($a['amount'] - $a['spent'] - $a['ret'] + $a['reimb'], 2);  // >0 return cash, <0 reimburse
        $a['status'] = $a['due'] == 0 ? 'Liquidated' : 'Open';
    }
    return $list;
}
function add_tx($type, $ref, $date, $amount, $f = []) {
    q('INSERT INTO transactions (ref,type,tx_date,amount,member_id,advance_ref,description,category,person,receipt_ref,method,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$ref, $type, $date, $amount, $f['member_id'] ?? null, $f['advance_ref'] ?? null, $f['description'] ?? '', $f['category'] ?? '',
         $f['person'] ?? '', $f['receipt_ref'] ?? '', $f['method'] ?? '', me()['username'] ?? 'system']);
    audit($type, "$ref · " . peso($amount) . ' · ' . ($f['description'] ?? ''));
}

function month_rows() {   // monthly financial summary with running cash balance
    $r = rows("SELECT DATE_FORMAT(tx_date,'%Y-%m') AS ym,
        SUM(CASE WHEN type='PAYMENT' THEN amount ELSE 0 END) AS coll,
        SUM(CASE WHEN type='ADVANCE' THEN amount ELSE 0 END) AS adv,
        SUM(CASE WHEN type='EXPENSE' THEN amount ELSE 0 END) AS exp,
        SUM(CASE WHEN type='RETURN' THEN amount ELSE 0 END) AS ret,
        SUM(CASE WHEN type='REIMBURSE' THEN amount ELSE 0 END) AS reimb,
        " . CASH_SQL . " AS net FROM transactions WHERE voided = 0 GROUP BY ym ORDER BY ym");
    $run = 0; foreach ($r as &$m) { $run += $m['net']; $m['end'] = $run; }
    return $r;
}

/* ---------- page layout ---------- */
function head($title, $active) {
    $nav = ['home.php' => 'Home', 'index.php' => 'Dashboard', 'members.php' => 'Members', 'payments.php' => 'Fee collection', 'expenses.php' => 'Expenses & liquidation',
            'ledger.php' => 'Transaction history', 'audit.php' => 'Audit trail', 'report.php' => 'Reports', 'account.php' => 'My account'];
    if (is_treasurer()) $nav['settings.php'] = 'Settings';
    if (me()['role'] === 'member') $nav = ['portal.php' => 'My account summary', 'account.php' => 'Change password'];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' · Mini Accounting</title><link rel="stylesheet" href="assets/style.css"></head><body><div id="app"><aside>';
    echo '<h1>' . e(setting('org_name')) . '</h1><small>Mini Accounting System</small>';
    foreach ($nav as $f => $label) echo '<a class="nav' . ($active === $f ? ' on' : '') . '" href="' . $f . '">' . e($label) . '</a>';
    echo '<div class="who">' . e(me()['name']) . ' · ' . e(me()['role']) . '<br><a class="btn alt sm" style="margin-top:6px;display:inline-block;color:#fff;border-color:#fff" href="logout.php">Sign out</a></div></aside><main>';
    if (!empty($_SESSION['flash'])) {
        echo '<div class="msg ' . ($_SESSION['flash'][1] ? '' : 'ok') . ' noprint">' . e($_SESSION['flash'][0]) . '</div>';
        unset($_SESSION['flash']);
    }
}
function foot() { echo '</main></div></body></html>'; }
function csv_out($name, $header, $data) {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="' . $name . '"');
    $o = fopen('php://output', 'w'); fputcsv($o, $header); foreach ($data as $r) fputcsv($o, $r); fclose($o); exit;
}
