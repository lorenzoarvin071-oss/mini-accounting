<?php
require __DIR__ . '/includes/bootstrap.php';
require_login(true);
if (guard_post(false)) {
    $row = rows('SELECT * FROM users WHERE id = ?', [me()['id']])[0];
    $new = $_POST['new'] ?? '';
    if (!password_verify($_POST['current'] ?? '', $row['password_hash'])) flash('Current password is wrong.', true);
    elseif (strlen($new) < 6) flash('New password must be at least 6 characters.', true);
    elseif ($new !== ($_POST['confirm'] ?? '')) flash('New passwords do not match.', true);
    else { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $row['id']]); audit('PASSWORD', 'Password changed'); flash('Password changed.'); }
    go('account.php');
}
head('My account', 'account.php');
?>
<h2>My account</h2>
<p class="note">Signed in as <b><?= e(me()['username']) ?></b> (<?= e(me()['role']) ?>). Change the default password before using real data.</p>
<form method="post" class="box stack" style="max-width:340px"><?= csrf_field() ?>
<label>Current password<input name="current" type="password" required></label><label>New password<input name="new" type="password" required></label>
<label>Confirm new password<input name="confirm" type="password" required></label><button class="btn">Change password</button></form>
<?php foot();
