<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$t = totals();
$open = array_filter(advances(), fn($a) => $a['status'] === 'Open');
head('Dashboard', 'index.php');
function stat_card($l, $v, $c = '') { echo '<div class="stat ' . $c . '"><span>' . e($l) . '</span><b class="num">' . peso($v) . '</b></div>'; }
?>
<h2>Dashboard</h2>
<div class="grid"><?php stat_card('Cash on hand', $t['cash'], 'main'); stat_card('Fees assessed', $t['assessed']); stat_card('Fees collected', $t['collected']);
stat_card('Outstanding fees', $t['outstanding']); stat_card('Total expenses', $t['exp']); stat_card('Cash advances issued', $t['adv']); ?></div>
<h3>Open cash advances</h3>
<div class="tw"><table><tr><th>Ref</th><th>Person</th><th class="r">Advance</th><th class="r">Spent</th><th class="r">To settle</th></tr>
<?php foreach ($open as $a): ?><tr><td><?= e($a['ref']) ?></td><td><?= e($a['person']) ?></td><td class="r"><?= peso($a['amount']) ?></td>
<td class="r"><?= peso($a['spent']) ?></td><td class="r"><?= $a['due'] > 0 ? 'Return ' . peso($a['due']) : 'Reimburse ' . peso(-$a['due']) ?></td></tr>
<?php endforeach; if (!$open): ?><tr><td colspan="5" class="note">No open advances.</td></tr><?php endif; ?></table></div>
<p class="note">Cash on hand = fees collected + cash returned − advances issued − reimbursements − expenses paid directly. Unpaid fees are never counted as cash.</p>
<?php foot();
