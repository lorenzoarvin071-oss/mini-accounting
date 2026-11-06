<?php
require __DIR__ . '/includes/bootstrap.php';
if (me()) go(me()['role'] === 'member' ? 'portal.php' : 'home.php');
$error = ''; $f = ['name' => '', 'student_id' => '', 'course' => '', 'year' => '', 'contact' => '', 'username' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) $f[$k] = trim($_POST[$k] ?? '');
    $pw = $_POST['password'] ?? '';
    if ($f['name'] === '') $error = 'Enter your full name.';
    elseif ($f['student_id'] === '') $error = 'Enter your student ID.';
    elseif (!in_array($f['course'], COURSES, true) || !in_array($f['year'], YEARS, true)) $error = 'Choose your course and year level from the lists.';
    elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $f['username'])) $error = 'Username must be 3 to 30 letters, numbers, or underscores.';
    elseif (one('SELECT COUNT(*) FROM users WHERE username = ?', [strtolower($f['username'])])) $error = 'That username is taken.';
    elseif (strlen($pw) < 6) $error = 'Password must be at least 6 characters.';
    elseif ($pw !== ($_POST['confirm'] ?? '')) $error = 'Passwords do not match.';
    else {
        // If the student ID is already in the system (added or imported by an officer), link the account to that record.
        $m = rows('SELECT m.*, (SELECT COUNT(*) FROM users WHERE member_id = m.id) AS acc FROM members m WHERE student_id = ?', [$f['student_id']])[0] ?? null;
        if ($m) {
            if (strcasecmp($m['name'], $f['name']) !== 0) $error = 'That student ID is registered under a different name. Enter your name exactly as the treasurer has it.';
            elseif ($m['acc']) $error = 'That student ID already has an account. Sign in, or ask the treasurer to reset your password.';
            else { $mid = $m['id']; $code = $m['member_code']; }
        } else {
            $code = next_ref('M', 3);
            q('INSERT INTO members (member_code,student_id,name,course,year_level,contact,fee) VALUES (?,?,?,?,?,?,?)', [$code, $f['student_id'], $f['name'], $f['course'], $f['year'], $f['contact'], setting('default_fee')]);
            $mid = (int)one('SELECT LAST_INSERT_ID()');
            q('INSERT INTO audit_log (username, action, details) VALUES (?,?,?)', [strtolower($f['username']), 'MEMBER', "$code {$f['name']} registered online"]);
        }
        if (!$error) {
            q("INSERT INTO users (username, password_hash, name, role, member_id) VALUES (?,?,?,'member',?)", [strtolower($f['username']), password_hash($pw, PASSWORD_DEFAULT), $f['name'], $mid]);
            q('INSERT INTO audit_log (username, action, details) VALUES (?,?,?)', [strtolower($f['username']), 'REGISTER', 'Member account created']);
            flash('Account created. Your Member ID is ' . $code . '. You can sign in now.');
            go('login.php');
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create member account</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div id="login" style="max-width:420px"><h2>Create member account</h2>
<?php if ($error): ?><div class="msg"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="box stack">
<label>Full name<input name="name" value="<?= e($f['name']) ?>" required></label>
<label>Student ID<input name="student_id" value="<?= e($f['student_id']) ?>" required></label>
<div class="row"><label>Course<select name="course" required><?= opts(COURSES, $f['course']) ?></select></label>
<label>Year level<select name="year" required><?= opts(YEARS, $f['year']) ?></select></label></div>
<label>Contact (optional)<input name="contact" value="<?= e($f['contact']) ?>"></label>
<label>Username<input name="username" value="<?= e($f['username']) ?>" required></label>
<label>Password<input name="password" type="password" required></label>
<label>Confirm password<input name="confirm" type="password" required></label>
<button class="btn">Create account</button></form>
<p class="note">If the treasurer already added you, your account links to your record using your student ID and name. A Member ID (like M-001) is assigned automatically.</p>
<p class="note"><a href="login.php">Back to sign in</a></p></div></body></html>
