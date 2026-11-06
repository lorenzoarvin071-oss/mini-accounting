<?php
require __DIR__ . '/includes/bootstrap.php';
require_login(true);
if (me()['role'] !== 'member') go('home.php');
$m = current(array_filter(members_with_paid(), fn($x) => $x['id'] == me()['member_id']));
head('My account summary', 'portal.php');
if (!$m) { echo '<h2>My account</h2><p class="msg">Your member record was not found. Please contact the treasurer.</p>'; foot(); exit; }
$bal = $m['fee'] - $m['paid']; $s = mstatus($m['fee'], $m['paid']);
$pct = $m['fee'] > 0 ? min(100, round($m['paid'] / $m['fee'] * 100)) : 100;
$pays = rows("SELECT * FROM transactions WHERE type='PAYMENT' AND voided=0 AND member_id = ? ORDER BY id DESC", [$m['id']]);
?>
<h2>My account summary</h2>
<p class="note"><?= e($m['name']) ?> · Student ID <?= e($m['student_id'] ?: '—') ?> · <?= e($m['member_code']) ?> · <?= e($m['course']) ?> <?= e($m['year_level']) ?></p>
<div class="grid">
<div class="stat main"><span>Balance to pay</span><b class="num"><?= peso($bal) ?></b></div>
<div class="stat"><span>Membership fee</span><b class="num"><?= peso($m['fee']) ?></b></div>
<div class="stat"><span>Total paid</span><b class="num"><?= peso($m['paid']) ?></b></div>
<div class="stat"><span>Status</span><b><span class="tag <?= $s ?>"><?= $s ?></span></b></div></div>
<div class="box" style="margin-top:14px"><div class="note"><?= $pct ?>% paid</div>
<div style="height:10px;border-radius:5px;background:var(--line);overflow:hidden"><div style="height:100%;width:<?= $pct ?>%;background:var(--ok)"></div></div></div>
<h3>My payment receipts</h3>
<div class="tw"><table><tr><th>Receipt no.</th><th>Date</th><th>Method</th><th class="r">Amount</th></tr>
<?php foreach ($pays as $t): ?><tr><td><?= e($t['ref']) ?></td><td><?= e($t['tx_date']) ?></td><td><?= e($t['method']) ?></td><td class="r"><?= peso($t['amount']) ?></td></tr>
<?php endforeach; if (!$pays): ?><tr><td colspan="4" class="note">No payments recorded yet.</td></tr><?php endif; ?></table></div>
<p class="note">Payments are recorded by the treasurer or an officer. If something looks wrong, show your receipt to them.</p>
<?php foot();
