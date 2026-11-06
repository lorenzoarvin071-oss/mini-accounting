<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (guard_post()) {
    $m = rows('SELECT m.*, COALESCE((SELECT SUM(amount) FROM transactions WHERE type="PAYMENT" AND voided=0 AND member_id = m.id),0) paid FROM members m WHERE m.id = ?', [(int)($_POST['member_id'] ?? 0)])[0] ?? null;
    $amt = $_POST['amount'] ?? ''; $date = $_POST['date'] ?? '';
    if (!$m) flash('Choose a member.', true);
    elseif (!is_numeric($amt) || $amt <= 0) flash('Enter an amount greater than zero.', true);
    elseif (!valid_date($date)) flash('Enter a valid date.', true);
    elseif ($amt > $m['fee'] - $m['paid'] + 0.001) flash('Amount is more than ' . $m['name'] . "'s balance of " . peso($m['fee'] - $m['paid']) . '.', true);
    else {
        $r = next_ref('OR', 4);
        add_tx('PAYMENT', $r, $date, $amt, ['member_id' => $m['id'], 'method' => $_POST['method'] ?? 'Cash', 'description' => $m['name']]);
        flash("Payment recorded. Receipt no. $r.");
    }
    go('payments.php');
}
$owing = array_filter(members_with_paid(), fn($m) => $m['fee'] - $m['paid'] > 0);
$pays = rows("SELECT t.*, m.name FROM transactions t JOIN members m ON m.id = t.member_id WHERE t.type='PAYMENT' AND t.voided=0 ORDER BY t.id DESC");
head('Fee collection', 'payments.php');
?>
<h2>Membership fee collection</h2>
<?php if (can_edit()): ?>
<form method="post" class="box row noprint"><?= csrf_field() ?>
<label>Member<select name="member_id" required onchange="this.form.amount.value=this.selectedOptions[0].dataset.bal||''"><option value="">Choose…</option>
<?php foreach ($owing as $m): ?><option value="<?= $m['id'] ?>" data-bal="<?= $m['fee'] - $m['paid'] ?>"><?= e(($m['student_id'] ?: $m['member_code']) . ' · ' . $m['name'] . ' · ' . $m['course']) ?> (owes <?= peso($m['fee'] - $m['paid']) ?>)</option><?php endforeach; ?></select></label>
<label>Date<input name="date" type="date" value="<?= date('Y-m-d') ?>" required></label>
<label>Amount (₱)<input name="amount" type="number" min="0" step="0.01" required style="min-width:100px;width:110px"></label>
<label>Method<select name="method"><option>Cash</option><option>GCash</option><option>Bank transfer</option></select></label>
<button class="btn">Record payment</button></form>
<?php endif; ?>
<div class="tw"><table><tr><th>Receipt no.</th><th>Date</th><th>Member</th><th>Method</th><th class="r">Amount</th></tr>
<?php foreach ($pays as $t): ?><tr><td><?= e($t['ref']) ?></td><td><?= e($t['tx_date']) ?></td><td><?= e($t['name']) ?></td><td><?= e($t['method']) ?></td><td class="r"><?= peso($t['amount']) ?></td></tr>
<?php endforeach; if (!$pays): ?><tr><td colspan="5" class="note">No payments yet.</td></tr><?php endif; ?></table></div>
<?php foot();
