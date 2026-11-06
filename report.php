<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$t = totals();
$members = members_with_paid();
$unpaid = array_filter($members, fn($m) => mstatus($m['fee'], $m['paid']) !== 'Paid');
$paidCount = count($members) - count($unpaid);
head('Reports', 'report.php');
$sum = [['Membership fees assessed', $t['assessed']], ['Actual fees collected', $t['collected']], ['Outstanding membership fees', $t['outstanding']],
    ['Cash advances issued', $t['adv']], ['Actual expenses (with receipts)', $t['exp']], ['Unspent cash returned', $t['returned']],
    ['Reimbursements paid', $t['reimb']], ['<b>Available cash balance</b>', $t['cash']]];
?>
<div class="rep">
<p class="noprint"><button class="btn" onclick="window.print()">Print report</button></p>
<h2>Financial report</h2>
<p><b><?= e(setting('org_name')) ?></b><br>Prepared <?= date('F j, Y') ?> by <?= e(me()['name']) ?></p>
<h3>Summary</h3>
<div class="tw"><table><tr><th>Item</th><th class="r">Amount</th></tr>
<?php foreach ($sum as $r): ?><tr><td><?= $r[0] ?></td><td class="r"><?= peso($r[1]) ?></td></tr><?php endforeach; ?></table></div>
<h3>Monthly financial summary</h3>
<div class="tw"><table><tr><th>Month</th><th class="r">Collected</th><th class="r">Advances</th><th class="r">Expenses</th><th class="r">Returned</th><th class="r">Net cash change</th><th class="r">Cash at month end</th></tr>
<?php foreach (month_rows() as $m): ?><tr><td><?= date('F Y', strtotime($m['ym'] . '-01')) ?></td><td class="r"><?= peso($m['coll']) ?></td><td class="r"><?= peso($m['adv']) ?></td>
<td class="r"><?= peso($m['exp']) ?></td><td class="r"><?= peso($m['ret']) ?></td><td class="r"><?= peso($m['net']) ?></td><td class="r"><?= peso($m['end']) ?></td></tr>
<?php endforeach; ?></table></div>
<h3>Collection status (<?= $paidCount ?> paid, <?= count($unpaid) ?> with balance)</h3>
<div class="tw"><table><tr><th>ID</th><th>Student ID</th><th>Name</th><th>Course</th><th class="r">Balance</th><th>Status</th></tr>
<?php foreach ($unpaid as $m): ?><tr><td><?= e($m['member_code']) ?></td><td><?= e($m['student_id'] ?: '—') ?></td><td><?= e($m['name']) ?></td><td><?= e($m['course']) ?></td><td class="r"><?= peso($m['fee'] - $m['paid']) ?></td><td><?= mstatus($m['fee'], $m['paid']) ?></td></tr>
<?php endforeach; if (!$unpaid): ?><tr><td colspan="6" class="note">All members are fully paid.</td></tr><?php endif; ?></table></div>
<h3>Liquidation summary</h3>
<div class="tw"><table><tr><th>Ref</th><th>Person</th><th class="r">Advance</th><th class="r">Expenses</th><th class="r">Returned</th><th>Status</th></tr>
<?php foreach (advances() as $a): ?><tr><td><?= e($a['ref']) ?></td><td><?= e($a['person']) ?></td><td class="r"><?= peso($a['amount']) ?></td><td class="r"><?= peso($a['spent']) ?></td>
<td class="r"><?= peso($a['ret']) ?></td><td><?= $a['status'] ?></td></tr><?php endforeach; ?></table></div>
<p style="margin-top:40px">Prepared by: ______________________ &nbsp;&nbsp; Noted by (Adviser): ______________________</p>
</div>
<?php foot();
