<?php
require __DIR__ . '/includes/bootstrap.php';
if (me()) go(me()['role'] === 'member' ? 'portal.php' : 'home.php');
$error = ''; $notice = '';
if (!empty($_SESSION['flash'])) { $notice = $_SESSION['flash'][0]; unset($_SESSION['flash']); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = one('SELECT COUNT(*) FROM users WHERE username = ?', [strtolower(trim($_POST['username'] ?? ''))]) ? 
        rows('SELECT * FROM users WHERE username = ?', [strtolower(trim($_POST['username']))])[0] : null;
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $u['id'], 'username' => $u['username'], 'name' => $u['name'], 'role' => $u['role'], 'member_id' => $u['member_id']];
        audit('LOGIN', $u['username']);
        go($u['role'] === 'member' ? 'portal.php' : 'home.php');
    }
    $error = 'Wrong username or password.';
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · Mini Accounting</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div id="login"><h2>Mini Accounting System</h2><p class="note">Sign in to continue.</p>
<?php if ($notice): ?><div class="msg ok"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="box stack"><label>Username<input name="username" autofocus required></label>
<label>Password<input name="password" type="password" required></label><button class="btn">Sign in</button></form>
<p class="note">Are you a member? <a href="register.php">Create your member account</a>.</p>
<p class="note">Demo staff accounts: treasurer / treasurer123 (full access), officer / officer123 (record entries), adviser / adviser123 (view and print only).</p></div></body></html>
