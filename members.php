<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (guard_post()) {
    if (($_POST['act'] ?? '') === 'resetpw') {
        if (!is_treasurer()) die('Only the treasurer can reset passwords.');
        $u = rows("SELECT * FROM users WHERE member_id = ? AND role = 'member'", [(int)($_POST['id'] ?? 0)])[0] ?? null;
        if (!$u) flash('This member has no login account.', true);
        else {
            $tmp = substr(bin2hex(random_bytes(4)), 0, 8);
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($tmp, PASSWORD_DEFAULT), $u['id']]);
            audit('PASSWORD_RESET', $u['username']);
            flash('Temporary password for ' . $u['username'] . ': ' . $tmp . ' (ask the member to change it).');
        }
        go('members.php');
    }
    if (($_POST['act'] ?? '') === 'edit') {
        $m = members_with_paid(); $m = current(array_filter($m, fn($x) => $x['id'] == ($_POST['id'] ?? 0)));
        $name = trim($_POST['name'] ?? ''); $fee = $_POST['fee'] ?? ''; $sid = trim($_POST['student_id'] ?? '');
        if (!$m || $name === '') flash('Enter the member name.', true);
        elseif ($sid === '' || one('SELECT COUNT(*) FROM members WHERE student_id = ? AND id <> ?', [$sid, $m['id']])) flash('Student ID is required and must not belong to another member.', true);
        elseif (!is_numeric($fee) || $fee < $m['paid']) flash('Fee cannot be less than what the member already paid (' . peso($m['paid']) . ').', true);
        else {
            q('UPDATE members SET student_id=?, name=?, course=?, year_level=?, contact=?, fee=? WHERE id=?', [$sid, $name, trim($_POST['course'] ?? ''), trim($_POST['year'] ?? ''), trim($_POST['contact'] ?? ''), $fee, $m['id']]);
            audit('MEMBER_EDIT', $m['member_code'] . ' ' . $m['name'] . ' → ' . $name . ', fee ' . peso($m['fee']) . ' → ' . peso($fee));
            flash('Updated ' . $m['member_code'] . '.');
        }
        go('members.php');
    }
    $name = trim($_POST['name'] ?? ''); $fee = $_POST['fee'] ?? ''; $sid = trim($_POST['student_id'] ?? '');
    if ($name === '') flash('Enter the member name.', true);
    elseif ($sid === '') flash('Enter the student ID.', true);
    elseif (one('SELECT COUNT(*) FROM members WHERE student_id = ?', [$sid])) flash('That student ID is already registered.', true);
    elseif (!is_numeric($fee) || $fee < 0) flash('Enter a valid membership fee.', true);
    elseif (!in_array($_POST['course'] ?? '', COURSES, true) || !in_array($_POST['year'] ?? '', YEARS, true)) flash('Choose a course and year level from the lists.', true);
    else {
        $code = next_ref('M', 3);
        q('INSERT INTO members (member_code,student_id,name,course,year_level,contact,fee) VALUES (?,?,?,?,?,?,?)',
            [$code, $sid, $name, trim($_POST['course'] ?? ''), trim($_POST['year'] ?? ''), trim($_POST['contact'] ?? ''), $fee]);
        audit('MEMBER', "$code $name · fee " . peso($fee));
        flash("Added $name ($code).");
    }
    go('members.php');
}
$search = trim($_GET['s'] ?? '');
$list = members_with_paid($search);
if (isset($_GET['csv'])) csv_out('members.csv', ['ID', 'Student ID', 'Name', 'Course', 'Year', 'Fee', 'Paid', 'Balance', 'Status'],
    array_map(fn($m) => [$m['member_code'], $m['student_id'], $m['name'], $m['course'], $m['year_level'], $m['fee'], $m['paid'], $m['fee'] - $m['paid'], mstatus($m['fee'], $m['paid'])], $list));
head('Members', 'members.php');
?>
<h2>Membership records</h2>
<?php $ed = can_edit() && isset($_GET['edit']) ? current(array_filter($list, fn($x) => $x['id'] == $_GET['edit'])) : null; ?>
<?php if ($ed): ?>
<form method="post" class="box row noprint"><?= csrf_field() ?><input type="hidden" name="act" value="edit"><input type="hidden" name="id" value="<?= $ed['id'] ?>">
<b><?= e($ed['member_code']) ?></b><label>Student ID<input name="student_id" value="<?= e($ed['student_id']) ?>" required style="min-width:110px;width:120px"></label><label>Name<input name="name" value="<?= e($ed['name']) ?>" required></label><label>Course<select name="course" style="min-width:100px"><?= opts(COURSES, $ed['course']) ?></select></label>
<label>Year<select name="year" style="min-width:100px"><?= opts(YEARS, $ed['year_level']) ?></select></label><label>Contact<input name="contact" value="<?= e($ed['contact']) ?>"></label>
<label>Fee (₱)<input name="fee" type="number" min="<?= $ed['paid'] ?>" step="0.01" value="<?= $ed['fee'] ?>" style="min-width:90px;width:100px"></label>
<button class="btn">Save changes</button><a class="btn alt" href="members.php">Cancel</a></form>
<?php if (is_treasurer() && $ed['username']): ?><form method="post" class="box row noprint" onsubmit="return confirm('Reset this member\'s password?')"><?= csrf_field() ?><input type="hidden" name="act" value="resetpw"><input type="hidden" name="id" value="<?= $ed['id'] ?>">Login: <b><?= e($ed['username']) ?></b> <button class="btn alt sm">Reset password</button></form><?php endif; ?>
<?php elseif (can_edit()): ?>
<form method="post" class="box row noprint"><?= csrf_field() ?>
<label>Student ID<input name="student_id" required style="min-width:110px;width:120px"></label><label>Name<input name="name" required></label><label>Course<select name="course" required style="min-width:100px"><?= opts(COURSES) ?></select></label>
<label>Year<select name="year" required style="min-width:100px"><?= opts(YEARS) ?></select></label><label>Contact<input name="contact"></label>
<label>Fee (₱)<input name="fee" type="number" min="0" step="0.01" value="<?= e(setting('default_fee')) ?>" style="min-width:90px;width:100px"></label>
<button class="btn">Add member</button></form>
<?php endif; ?>
<form method="get" class="row noprint" style="margin-bottom:10px"><label>Search<input name="s" value="<?= e($search) ?>"></label>
<button class="btn alt">Search</button><a class="btn alt" href="?csv=1&s=<?= urlencode($search) ?>">Export CSV</a><?php if (can_edit()): ?><a class="btn" href="import.php">Import from file</a><?php endif; ?></form>
<div class="tw"><table><tr><th>ID</th><th>Student ID</th><th>Name</th><th>Course</th><th>Year</th><th class="r">Fee</th><th class="r">Paid</th><th class="r">Balance</th><th>Status</th><th>Login</th><th class="noprint"></th></tr>
<?php foreach ($list as $m): $s = mstatus($m['fee'], $m['paid']); ?>
<tr><td><?= e($m['member_code']) ?></td><td><?= e($m['student_id'] ?: '—') ?></td><td><?= e($m['name']) ?></td><td><?= e($m['course']) ?></td><td><?= e($m['year_level']) ?></td>
<td class="r"><?= peso($m['fee']) ?></td><td class="r"><?= peso($m['paid']) ?></td><td class="r"><?= peso($m['fee'] - $m['paid']) ?></td>
<td><span class="tag <?= $s ?>"><?= $s ?></span></td><td><?= e($m['username'] ?: '—') ?></td><td class="noprint"><?php if (can_edit()): ?><a href="?edit=<?= $m['id'] ?>">Edit</a><?php endif; ?></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="11" class="note">No members found.</td></tr><?php endif; ?></table></div>
<?php foot();
