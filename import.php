<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (!can_edit()) go('home.php');
if (isset($_GET['template'])) csv_out('members_template.csv', ['student_id', 'name', 'course', 'year', 'contact', 'fee', 'paid'],
    [['2026-0001', 'Juan Dela Cruz', 'BSIT', '3rd Year', '09171234567', '100', '100'], ['2026-0002', 'Maria Santos', 'BSCS', '2nd Year', '', '100', '']]);

function norm($s) { return preg_replace('/[^a-z0-9]/', '', strtolower($s)); }
function num_clean($v) { return preg_replace('/[^\d.]/', '', $v); }
function canon_course($v) { foreach (COURSES as $c) if (strcasecmp($c, trim($v)) === 0) return $c; return null; }
function canon_year($v) {
    $v = trim($v);
    foreach (YEARS as $y) if (strcasecmp($y, $v) === 0) return $y;
    return preg_match('/([1-4])/', $v, $m) ? YEARS[(int)$m[1] - 1] : null;
}

$summary = null; $skipped = [];
if (guard_post()) {
    $text = '';
    if (!empty($_FILES['file']['tmp_name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['file']['size'] > 2000000) flash('File is too large (2 MB max).', true); else $text = file_get_contents($_FILES['file']['tmp_name']);
    } else $text = $_POST['pasted'] ?? '';
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $text), fn($l) => trim($l) !== ''));
    if (!$lines) { if (empty($_SESSION['flash'])) flash('Paste your data or choose a file first.', true); }
    elseif (count($lines) > 1001) flash('Too many rows. Import up to 1000 at a time.', true);
    else {
        $d = substr_count($lines[0], "\t") ? "\t" : (substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',');
        $keys = array_map('norm', str_getcsv($lines[0], $d));
        $hasHeader = (bool)array_intersect($keys, ['name', 'fullname', 'studentname']);
        $idx = ['sid' => 0, 'name' => 1, 'course' => 2, 'year' => 3, 'contact' => 4, 'fee' => 5, 'paid' => 6];   // default order when there is no header row
        if ($hasHeader) {
            $idx = [];
            $alias = ['sid' => ['studentid', 'studentno', 'idno', 'idnumber', 'sid', 'id'], 'name' => ['name', 'fullname', 'studentname'], 'course' => ['course', 'program'],
                'year' => ['year', 'yearlevel', 'level'], 'contact' => ['contact', 'contactno', 'phone', 'mobile'], 'fee' => ['fee', 'membershipfee'], 'paid' => ['paid', 'amountpaid', 'payment']];
            foreach ($alias as $f => $names) foreach ($keys as $i => $k) if (in_array($k, $names, true)) { $idx[$f] = $i; break; }
            array_shift($lines);
        }
        $added = 0; $payN = 0; $seen = [];
        try {
            $pdo->beginTransaction();
            foreach ($lines as $n => $line) {
                $r = str_getcsv($line, $d); $row = $n + ($hasHeader ? 2 : 1);
                $g = fn($f) => isset($idx[$f], $r[$idx[$f]]) ? trim($r[$idx[$f]]) : '';
                $sid = $g('sid'); $name = $g('name'); $course = canon_course($g('course')); $year = canon_year($g('year'));
                $feeRaw = num_clean($g('fee')); $fee = $feeRaw === '' ? (float)setting('default_fee') : (float)$feeRaw;
                $paidRaw = num_clean($g('paid')); $paid = $paidRaw === '' ? 0 : (float)$paidRaw;
                $why = '';
                if ($sid === '') $why = 'Missing student ID';
                elseif ($name === '') $why = 'Missing name';
                elseif (isset($seen[strtolower($sid)])) $why = 'Student ID appears twice in this file';
                elseif (one('SELECT COUNT(*) FROM members WHERE student_id = ?', [$sid])) $why = 'Student ID already registered';
                elseif (!$course) $why = 'Course "' . $g('course') . '" is not in the list (' . implode(', ', COURSES) . ')';
                elseif (!$year) $why = 'Year level "' . $g('year') . '" not recognised (use 1 to 4)';
                elseif ($paid > $fee) $why = 'Paid amount is more than the fee';
                if ($why) { $skipped[] = [$row, $sid, $name, $why]; continue; }
                $seen[strtolower($sid)] = 1;
                $code = next_ref('M', 3);
                q('INSERT INTO members (member_code,student_id,name,course,year_level,contact,fee) VALUES (?,?,?,?,?,?,?)', [$code, $sid, $name, $course, $year, $g('contact'), $fee]);
                $mid = (int)one('SELECT LAST_INSERT_ID()'); $added++;
                if ($paid > 0) { add_tx('PAYMENT', next_ref('OR', 4), date('Y-m-d'), $paid, ['member_id' => $mid, 'method' => 'Import', 'description' => $name]); $payN++; }
            }
            audit('IMPORT', "$added members added, $payN payments, " . count($skipped) . ' rows skipped');
            $pdo->commit();
            $summary = "Imported $added members" . ($payN ? " and $payN payments" : '') . '. Skipped ' . count($skipped) . ' rows.';
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Import failed and nothing was saved: ' . $ex->getMessage(), true);
        }
    }
}
head('Import members', 'members.php');
?>
<h2>Import members from a file</h2>
<?php if ($summary): ?><div class="msg ok"><?= e($summary) ?></div><?php endif; ?>
<div class="box">
<p><b>Columns:</b> student_id, name, course, year, contact, fee, paid. Only student_id and name are required. If <b>fee</b> is blank, the default fee (<?= peso(setting('default_fee')) ?>) is used. If <b>paid</b> has an amount, a payment with a receipt number is recorded for that member.</p>
<p class="note">Course must be one of: <?= e(implode(', ', COURSES)) ?>. Year can be written as 1, 1st, or 1st Year. The first row may be a header row, or you can skip it and use the order above. In Excel, select the cells, copy, and paste them into the box. You can also upload a CSV file. <a href="?template=1">Download a template</a>.</p>
<form method="post" enctype="multipart/form-data" class="stack"><?= csrf_field() ?>
<label>Paste data here<textarea name="pasted" rows="9" style="width:100%;font:13px Consolas,monospace;padding:8px;border:1px solid var(--line);border-radius:6px;background:var(--card);color:var(--ink)" placeholder="student_id,name,course,year,contact,fee,paid&#10;2026-0001,Juan Dela Cruz,BSIT,3rd Year,09171234567,100,100"></textarea></label>
<label>Or upload a CSV or text file<input type="file" name="file" accept=".csv,.txt"></label>
<div class="row"><button class="btn">Import</button><a class="btn alt" href="members.php">Back to members</a></div></form></div>
<?php if ($skipped): ?><h3>Skipped rows</h3>
<div class="tw"><table><tr><th>Row</th><th>Student ID</th><th>Name</th><th>Reason</th></tr>
<?php foreach ($skipped as $s): ?><tr><td><?= $s[0] ?></td><td><?= e($s[1]) ?></td><td><?= e($s[2]) ?></td><td><?= e($s[3]) ?></td></tr><?php endforeach; ?></table></div>
<p class="note">Fix these rows and import them again. Rows that were already imported are skipped automatically as "already registered".</p><?php endif; ?>
<?php foot();
