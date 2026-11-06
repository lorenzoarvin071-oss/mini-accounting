<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$log = rows('SELECT * FROM audit_log ORDER BY id DESC LIMIT 500');
head('Audit trail', 'audit.php');
?>
<h2>Audit trail</h2>
<div class="tw"><table><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr>
<?php foreach ($log as $a): ?><tr><td><?= e($a['created_at']) ?></td><td><?= e($a['username']) ?></td><td><?= e($a['action']) ?></td><td><?= e($a['details']) ?></td></tr>
<?php endforeach; if (!$log): ?><tr><td colspan="4" class="note">No activity yet.</td></tr><?php endif; ?></table></div>
<?php foot();
