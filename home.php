<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$role = me()['role'];
$t = totals();
$owing = array_filter(members_with_paid(), fn($m) => $m['fee'] - $m['paid'] > 0);
usort($owing, fn($a, $b) => ($b['fee'] - $b['paid']) <=> ($a['fee'] - $a['paid']));
$open = array_filter(advances(), fn($a) => $a['status'] === 'Open');
$rate = $t['assessed'] > 0 ? round($t['collected'] / $t['assessed'] * 100) : 0;

function home_card($l, $v, $c = '') { echo '<div class="stat ' . $c . '"><span>' . e($l) . '</span><b class="num">' . $v . '</b></div>'; }
function link_btn($href, $label, $alt = false) { echo '<a class="btn' . ($alt ? ' alt' : '') . '" href="' . $href . '">' . e($label) . '</a> '; }
function tx_table($list, $empty) {
    echo '<div class="tw"><table><tr><th>Date</th><th>Ref</th><th>Type</th><th>Description</th><th class="r">Amount</th></tr>';
    foreach ($list as $x) echo '<tr><td>' . e($x['tx_date']) . '</td><td>' . e($x['ref']) . '</td><td>' . e($x['type']) . '</td><td>' . e($x['description']) . '</td><td class="r">' . peso($x['amount']) . '</td></tr>';
    if (!$list) echo '<tr><td colspan="5" class="note">' . e($empty) . '</td></tr>';
    echo '</table></div>';
}
function owing_table($owing, $limit) {
    echo '<div class="tw"><table><tr><th>ID</th><th>Name</th><th class="r">Balance</th><th>Status</th></tr>';
    foreach (array_slice($owing, 0, $limit) as $m) echo '<tr><td>' . e($m['member_code']) . '</td><td>' . e($m['name']) . '</td><td class="r">' . peso($m['fee'] - $m['paid']) . '</td><td><span class="tag ' . mstatus($m['fee'], $m['paid']) . '">' . mstatus($m['fee'], $m['paid']) . '</span></td></tr>';
    if (!$owing) echo '<tr><td colspan="4" class="note">Every member is fully paid.</td></tr>';
    echo '</table></div>';
}
function open_adv_table($open) {
    echo '<div class="tw"><table><tr><th>Ref</th><th>Person</th><th class="r">Advance</th><th class="r">Spent</th><th class="r">To settle</th></tr>';
    foreach ($open as $a) echo '<tr><td>' . e($a['ref']) . '</td><td>' . e($a['person']) . '</td><td class="r">' . peso($a['amount']) . '</td><td class="r">' . peso($a['spent']) . '</td><td class="r">' . ($a['due'] > 0 ? 'Return ' . peso($a['due']) : 'Reimburse ' . peso(-$a['due'])) . '</td></tr>';
    if (!$open) echo '<tr><td colspan="5" class="note">All cash advances are liquidated.</td></tr>';
    echo '</table></div>';
}

head('Home', 'home.php');

if ($role === 'treasurer'):
    $recentAudit = rows('SELECT * FROM audit_log ORDER BY id DESC LIMIT 6'); ?>
<h2>Treasurer's desk</h2>
<p class="note">Welcome, <?= e(me()['name']) ?>. You have full access: record entries, void mistakes, and manage settings.</p>
<div class="grid"><?php home_card('Cash on hand', peso($t['cash']), 'main'); home_card('Collection rate', $rate . '%'); home_card('Members still owing', count($owing)); home_card('Open cash advances', count($open)); ?></div>
<p style="margin-top:14px"><?php link_btn('payments.php', 'Record a payment'); link_btn('expenses.php', 'Expenses & liquidation'); link_btn('report.php', 'Financial report', true); link_btn('ledger.php', 'Void or review entries', true); link_btn('settings.php', 'Settings', true); ?></p>
<h3>Needs attention: open cash advances</h3><?php open_adv_table($open); ?>
<h3>Needs attention: largest unpaid balances</h3><?php owing_table($owing, 5); ?>
<h3>Latest activity</h3>
<div class="tw"><table><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr>
<?php foreach ($recentAudit as $a): ?><tr><td><?= e($a['created_at']) ?></td><td><?= e($a['username']) ?></td><td><?= e($a['action']) ?></td><td><?= e($a['details']) ?></td></tr><?php endforeach;
if (!$recentAudit): ?><tr><td colspan="4" class="note">No activity yet.</td></tr><?php endif; ?></table></div>

<?php elseif ($role === 'officer'):
    $mine = rows('SELECT * FROM transactions WHERE voided = 0 AND created_by = ? ORDER BY id DESC LIMIT 8', [me()['username']]); ?>
<h2>Officer desk</h2>
<p class="note">Welcome, <?= e(me()['name']) ?>. Use the shortcuts below to record today's entries. If you make a mistake, ask the treasurer to void it.</p>
<div class="grid"><?php home_card('Members still owing', count($owing), 'main'); home_card('Fees collected', peso($t['collected'])); home_card('Outstanding fees', peso($t['outstanding'])); home_card('Open cash advances', count($open)); ?></div>
<h3>Quick actions</h3>
<p><?php link_btn('members.php', 'Add a member'); link_btn('payments.php', 'Record a payment'); link_btn('expenses.php', 'Record an expense'); link_btn('expenses.php', 'Issue a cash advance', true); link_btn('members.php?s=', 'Find a member', true); ?></p>
<h3>Members to follow up</h3><?php owing_table($owing, 10); ?>
<h3>Cash advances waiting for liquidation</h3><?php open_adv_table($open); ?>
<h3>Your recent entries</h3><?php tx_table($mine, 'You have not recorded anything yet.'); ?>

<?php else:
    $recent = rows('SELECT * FROM transactions WHERE voided = 0 ORDER BY id DESC LIMIT 8'); ?>
<h2>Adviser overview</h2>
<p class="note">Welcome, <?= e(me()['name']) ?>. This is a view-only account: you can review and print every report, but not change records.</p>
<div class="grid"><?php home_card('Cash on hand', peso($t['cash']), 'main'); home_card('Collection rate', $rate . '%'); home_card('Fees collected', peso($t['collected'])); home_card('Outstanding fees', peso($t['outstanding'])); home_card('Total expenses', peso($t['exp'])); ?></div>
<h3>Review</h3>
<p><?php link_btn('report.php', 'Financial report'); link_btn('ledger.php', 'Transaction history', true); link_btn('audit.php', 'Audit trail', true); link_btn('expenses.php', 'Liquidation status', true); link_btn('members.php', 'Member records', true); ?></p>
<h3>Cash advances not yet liquidated</h3><?php open_adv_table($open); ?>
<h3>Latest transactions</h3><?php tx_table($recent, 'No transactions yet.'); ?>
<?php endif;
foot();
