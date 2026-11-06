<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (!is_treasurer()) go('home.php');
if (guard_post()) {
    $act = $_POST['act'] ?? '';
    if ($act === 'org' && trim($_POST['org'] ?? '') !== '') {
        q('UPDATE settings SET v = ? WHERE k = "org_name"', [trim($_POST['org'])]); audit('SETTINGS', 'Org name changed'); flash('Saved.');
    } elseif ($act === 'reset' || $act === 'sample') {
        q('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['transactions', 'members', 'counters', 'audit_log'] as $tb) q("TRUNCATE TABLE $tb");
        q('SET FOREIGN_KEY_CHECKS = 1');
        q("DELETE FROM users WHERE role = 'member'");
        audit($act === 'sample' ? 'SAMPLE' : 'RESET', $act === 'sample' ? 'Loaded sample scenario' : 'All data cleared');
        if ($act === 'sample') {
            $ids = [];
            for ($i = 1; $i <= 50; $i++) {
                q('INSERT INTO members (member_code,student_id,name,course,year_level,fee) VALUES (?,?,?,?,?,100)', [next_ref('M', 3), '2026-' . str_pad($i, 4, '0', STR_PAD_LEFT), "Member $i", 'BSIT', '3rd Year']);
                $ids[] = (int)one('SELECT LAST_INSERT_ID()');
            }
            $d = date('Y-m-d');
            foreach (array_slice($ids, 0, 45) as $id) add_tx('PAYMENT', next_ref('OR', 4), $d, 100, ['member_id' => $id, 'method' => 'Cash', 'description' => 'Sample payment']);
            $ca = next_ref('CA', 3);
            add_tx('ADVANCE', $ca, $d, 2000, ['person' => 'Activity Chair', 'description' => 'Activity budget']);
            foreach ([['Snacks', 'Food', 800, 'OR-1001'], ['Materials', 'Supplies', 500, 'OR-1002'], ['Printing', 'Supplies', 350, 'OR-1003']] as $x)
                add_tx('EXPENSE', next_ref('EX', 4), $d, $x[2], ['description' => $x[0], 'category' => $x[1], 'receipt_ref' => $x[3], 'advance_ref' => $ca, 'person' => 'Activity Chair']);
            add_tx('RETURN', next_ref('RT', 4), $d, 350, ['advance_ref' => $ca, 'person' => 'Activity Chair', 'description' => 'Unspent cash returned']);
            flash('Sample scenario loaded. Cash balance should be ₱2,850.00.');
        } else flash('All data cleared.');
    }
    go('settings.php');
}
head('Settings', 'settings.php');
?>
<h2>Settings</h2>
<form method="post" class="box row"><?= csrf_field() ?><input type="hidden" name="act" value="org">
<label>Organization name<input name="org" value="<?= e(setting('org_name')) ?>" style="min-width:280px"></label><button class="btn">Save</button></form>
<div class="box"><p class="note">Test the system: the sample scenario loads 50 members × ₱100, ₱4,500 collected, a ₱2,000 advance, ₱1,650 in receipted expenses and ₱350 returned. Expected cash balance: ₱2,850. Loading it replaces all current records.</p>
<div class="row">
<form method="post" onsubmit="return confirm('Replace all records with the sample scenario?')"><?= csrf_field() ?><input type="hidden" name="act" value="sample"><button class="btn">Load sample scenario</button></form>
<form method="post" onsubmit="return confirm('Delete ALL records? This cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="act" value="reset"><button class="btn alt">Clear all data</button></form>
</div></div>
<?php foot();
