<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (guard_post()) {
    if (!is_treasurer()) die('Only the treasurer can void entries.');
    $t = rows('SELECT * FROM transactions WHERE id = ? AND voided = 0', [(int)($_POST['id'] ?? 0)])[0] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    if (!$t) flash('Entry not found or already voided.', true);
    elseif ($reason === '') flash('Enter a reason for voiding.', true);
    elseif ($t['type'] === 'ADVANCE' && one('SELECT COUNT(*) FROM transactions WHERE advance_ref = ? AND voided = 0', [$t['ref']])) flash('Void the expenses and returns linked to this advance first.', true);
    else {
        q('UPDATE transactions SET voided = 1, void_reason = ? WHERE id = ?', [$reason, $t['id']]);
        audit('VOID', $t['ref'] . ' · ' . peso($t['amount']) . ' · ' . $reason);
        flash('Voided ' . $t['ref'] . '. It stays in the history but no longer counts.');
    }
    go('ledger.php');
}
$tx = rows('SELECT * FROM transactions ORDER BY id');
function effect($t) {
    if ($t['voided']) return 0;
    switch ($t['type']) {
        case 'PAYMENT': case 'RETURN': return $t['amount'];
        case 'ADVANCE': case 'REIMBURSE': return -$t['amount'];
        case 'EXPENSE': return $t['advance_ref'] ? 0 : -$t['amount'];
    }
    return 0;
}
$bal = 0; foreach ($tx as &$t) { $t['eff'] = effect($t); $bal += $t['eff']; $t['bal'] = $bal; } unset($t);
if (isset($_GET['csv'])) csv_out('ledger.csv', ['Date', 'Ref', 'Type', 'Description', 'Amount', 'Cash effect', 'Cash balance', 'Status'],
    array_map(fn($t) => [$t['tx_date'], $t['ref'], $t['type'], $t['description'], $t['amount'], $t['eff'], $t['bal'], $t['voided'] ? 'VOID: ' . $t['void_reason'] : ''], $tx));
head('Transaction history', 'ledger.php');
?>
<h2>Transaction history</h2>
<p class="noprint"><a class="btn alt" href="?csv=1">Export CSV</a></p>
<div class="tw"><table><tr><th>Date</th><th>Ref</th><th>Type</th><th>Description</th><th class="r">Amount</th><th class="r">Cash effect</th><th class="r">Cash balance</th><?php if (is_treasurer()): ?><th class="noprint">Void</th><?php endif; ?></tr>
<?php foreach (array_reverse($tx) as $t): ?><tr<?= $t['voided'] ? ' style="text-decoration:line-through;opacity:.6"' : '' ?>><td><?= e($t['tx_date']) ?></td><td><?= e($t['ref']) ?></td><td><?= e($t['type']) ?></td><td><?= e($t['description']) ?></td>
<td class="r"><?= peso($t['amount']) ?></td><td class="r"><?= $t['eff'] != 0 ? peso($t['eff']) : '—' ?></td><td class="r"><?= peso($t['bal']) ?></td>
<?php if (is_treasurer()): ?><td class="noprint"><?php if ($t['voided']): ?><span class="tag Unpaid">Void</span> <span class="note"><?= e($t['void_reason']) ?></span>
<?php else: ?><form method="post" class="row" onsubmit="return confirm('Void <?= e($t['ref']) ?>?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $t['id'] ?>">
<input name="reason" placeholder="Reason" required style="min-width:100px;width:120px"><button class="btn alt sm">Void</button></form><?php endif; ?></td><?php endif; ?></tr>
<?php endforeach; if (!$tx): ?><tr><td colspan="8" class="note">No transactions yet.</td></tr><?php endif; ?></table></div>
<p class="note">Voided entries stay in the history (struck through) for the audit trail but do not count in any total. Expenses charged to a cash advance show “—” because the cash left when the advance was issued.</p>
<?php foot();
