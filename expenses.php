<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (guard_post()) {
    $act = $_POST['act'] ?? ''; $amt = $_POST['amount'] ?? ''; $date = $_POST['date'] ?? date('Y-m-d');
    if (!is_numeric($amt) || $amt <= 0) { flash('Enter an amount greater than zero.', true); go('expenses.php'); }
    if ($act !== 'settle' && !valid_date($date)) { flash('Enter a valid date.', true); go('expenses.php'); }

    if ($act === 'advance') {
        $p = trim($_POST['person'] ?? '');
        if ($p === '') flash('Enter who received the cash advance.', true);
        else { $r = next_ref('CA', 3); add_tx('ADVANCE', $r, $date, $amt, ['person' => $p, 'description' => trim($_POST['purpose'] ?? '') ?: 'Cash advance']); flash("Cash advance $r issued."); }
    } elseif ($act === 'expense') {
        $d = trim($_POST['description'] ?? ''); $rc = trim($_POST['receipt'] ?? ''); $adv = $_POST['advance_ref'] ?? '';
        if ($d === '') flash('Enter an expense description.', true);
        elseif ($rc === '') flash('Enter the receipt or supporting-document reference.', true);
        elseif ($adv !== '' && !one("SELECT COUNT(*) FROM transactions WHERE type='ADVANCE' AND voided=0 AND ref = ?", [$adv])) flash('Unknown cash advance.', true);
        else { add_tx('EXPENSE', next_ref('EX', 4), $date, $amt, ['description' => $d, 'category' => $_POST['category'] ?? '', 'person' => trim($_POST['person'] ?? ''),
            'receipt_ref' => $rc, 'advance_ref' => $adv === '' ? null : $adv]); flash('Expense recorded.'); }
    } elseif ($act === 'settle') {
        $kind = $_POST['kind'] === 'REIMBURSE' ? 'REIMBURSE' : 'RETURN';
        $a = current(array_filter(advances(), fn($x) => $x['ref'] === ($_POST['advance_ref'] ?? '')));
        $max = $a ? ($kind === 'RETURN' ? $a['due'] : -$a['due']) : 0;
        if (!$a || $max <= 0) flash('Nothing to settle for this advance.', true);
        elseif ($amt > $max + 0.001) flash('Amount is more than ' . peso($max) . '.', true);
        else { add_tx($kind, next_ref($kind === 'RETURN' ? 'RT' : 'RB', 4), date('Y-m-d'), $amt, ['advance_ref' => $a['ref'], 'person' => $a['person'],
            'description' => ($kind === 'RETURN' ? 'Unspent cash returned' : 'Reimbursement') . ' for ' . $a['ref']]); flash('Recorded.'); }
    }
    go('expenses.php');
}
$adv = advances();
$exp = rows("SELECT * FROM transactions WHERE type='EXPENSE' AND voided=0 ORDER BY id DESC");
head('Expenses & liquidation', 'expenses.php');
?>
<h2>Expenses and liquidation</h2>
<?php if (can_edit()): ?>
<h3>1. Issue a cash advance</h3>
<form method="post" class="box row noprint"><?= csrf_field() ?><input type="hidden" name="act" value="advance">
<label>Date<input name="date" type="date" value="<?= date('Y-m-d') ?>" required></label><label>Received by<input name="person" required></label>
<label>Purpose<input name="purpose"></label><label>Amount (₱)<input name="amount" type="number" min="0" step="0.01" required style="min-width:100px;width:110px"></label>
<button class="btn">Issue advance</button></form>
<h3>2. Record an expense</h3>
<form method="post" class="box row noprint"><?= csrf_field() ?><input type="hidden" name="act" value="expense">
<label>Date<input name="date" type="date" value="<?= date('Y-m-d') ?>" required></label><label>Description<input name="description" required></label>
<label>Category<select name="category"><option>Food</option><option>Supplies</option><option>Transportation</option><option>Printing</option><option>Other</option></select></label>
<label>Responsible person<input name="person"></label><label>Receipt ref.<input name="receipt" required style="min-width:100px;width:110px"></label>
<label>Charge to advance<select name="advance_ref"><option value="">None (paid from cash)</option>
<?php foreach ($adv as $a): ?><option value="<?= e($a['ref']) ?>"><?= e($a['ref'] . ' · ' . $a['person']) ?></option><?php endforeach; ?></select></label>
<label>Amount (₱)<input name="amount" type="number" min="0" step="0.01" required style="min-width:100px;width:110px"></label>
<button class="btn">Record expense</button></form>
<?php endif; ?>
<h3>Liquidation of cash advances</h3>
<div class="tw"><table><tr><th>Ref</th><th>Person</th><th class="r">Advance</th><th class="r">Expenses</th><th class="r">Returned</th><th class="r">Reimbursed</th><th class="r">Balance</th><th>Status</th><th class="noprint"></th></tr>
<?php foreach ($adv as $a): ?><tr><td><?= e($a['ref']) ?></td><td><?= e($a['person']) ?></td><td class="r"><?= peso($a['amount']) ?></td><td class="r"><?= peso($a['spent']) ?></td>
<td class="r"><?= peso($a['ret']) ?></td><td class="r"><?= peso($a['reimb']) ?></td>
<td class="r"><?= $a['due'] > 0 ? 'Return ' . peso($a['due']) : ($a['due'] < 0 ? 'Reimburse ' . peso(-$a['due']) : peso(0)) ?></td>
<td><span class="tag <?= $a['status'] ?>"><?= $a['status'] ?></span></td>
<td class="noprint"><?php if (can_edit() && $a['due'] != 0): $k = $a['due'] > 0 ? 'RETURN' : 'REIMBURSE'; ?>
<form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="act" value="settle"><input type="hidden" name="advance_ref" value="<?= e($a['ref']) ?>"><input type="hidden" name="kind" value="<?= $k ?>">
<input name="amount" type="number" step="0.01" min="0" value="<?= abs($a['due']) ?>" style="min-width:80px;width:90px"><button class="btn sm"><?= $k === 'RETURN' ? 'Record return' : 'Reimburse' ?></button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$adv): ?><tr><td colspan="9" class="note">No cash advances yet.</td></tr><?php endif; ?></table></div>
<h3>Expense list</h3>
<div class="tw"><table><tr><th>Ref</th><th>Date</th><th>Description</th><th>Category</th><th>Receipt</th><th>Advance</th><th class="r">Amount</th></tr>
<?php foreach ($exp as $t): ?><tr><td><?= e($t['ref']) ?></td><td><?= e($t['tx_date']) ?></td><td><?= e($t['description']) ?></td><td><?= e($t['category']) ?></td>
<td><?= e($t['receipt_ref']) ?></td><td><?= e($t['advance_ref'] ?: '—') ?></td><td class="r"><?= peso($t['amount']) ?></td></tr>
<?php endforeach; if (!$exp): ?><tr><td colspan="7" class="note">No expenses yet.</td></tr><?php endif; ?></table></div>
<?php foot();
