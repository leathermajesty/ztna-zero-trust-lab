<?php
// ===== Portal (:4180) — open to director, faculty, student =====
$user  = $_SERVER['HTTP_X_FORWARDED_PREFERRED_USERNAME'] ?? 'unknown';
$email = $_SERVER['HTTP_X_FORWARDED_EMAIL'] ?? '';
$g = $_SERVER['HTTP_X_FORWARDED_GROUPS'] ?? '';
$roles = [];
foreach (explode(',', $g) as $x){ $x = trim($x); if (strncmp($x,'role:',5)===0) $roles[] = substr($x,5); }
$director = in_array('director',$roles);
$faculty  = in_array('faculty',$roles);
$student  = in_array('student',$roles);
$role  = $director?'Director':($faculty?'Faculty':($student?'Student':'No role'));
$badge = $director?'#8e44ad':($faculty?'#1f8a82':($student?'#2c7be5':'#888'));

// --- per-student results (ownership demo: stud1 cannot see stud2) ---
$results = [
  'stud1'=>['name'=>'Student One','sem'=>5,'gpa'=>'8.6','backlog'=>0],
  'stud2'=>['name'=>'Student Two','sem'=>5,'gpa'=>'7.9','backlog'=>1],
];
$denied = false; $view = null;
if ($student) {
  $view = $_GET['student'] ?? $user;
  if ($view !== $user) { $denied = true; }      // a student may see ONLY their own record
} elseif ($faculty || $director) {
  $view = $_GET['student'] ?? null;              // staff may look up any student
}
function h($s){ return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>RRU Portal</title>
<style>
 *{box-sizing:border-box;margin:0;padding:0} body{font-family:Segoe UI,Arial,sans-serif;background:#eef2f8;color:#1b2440}
 header{background:#1f2d5a;color:#fff;padding:18px 32px;display:flex;align-items:center;justify-content:space-between}
 header .brand{font-size:22px;font-weight:700} header .brand span{color:#67d4cc}
 .who{display:flex;align-items:center;gap:14px;font-size:15px}
 .pill{background:<?=$badge?>;color:#fff;padding:5px 14px;border-radius:999px;font-weight:600;font-size:13px}
 a.logout{color:#cdd6ee;text-decoration:none;border:1px solid #3a4a80;padding:6px 12px;border-radius:8px}
 main{max-width:1060px;margin:28px auto;padding:0 20px}
 h2{font-size:28px;margin-bottom:6px} .sub{color:#5a6b8c;margin-bottom:24px}
 .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:28px}
 .card{background:#fff;border:1px solid #dde3ef;border-radius:14px;padding:22px}
 .card h3{font-size:18px;margin-bottom:8px} .card p{color:#5a6b8c;font-size:14px;margin-bottom:14px}
 .btn{display:inline-block;background:#2c7be5;color:#fff;text-decoration:none;padding:9px 16px;border-radius:9px;font-weight:600;font-size:14px}
 .btn.lock{background:#eef2f8;color:#9aa6c0;border:1px solid #dde3ef;cursor:not-allowed}
 .panel{background:#fff;border:1px solid #dde3ef;border-radius:14px;padding:24px;margin-bottom:22px}
 table{width:100%;border-collapse:collapse} td,th{padding:10px 12px;border-bottom:1px solid #eef2f8;text-align:left}
 .deny{background:#fdecea;border:1px solid #f5c2bd;color:#b42318;padding:16px 20px;border-radius:12px;font-weight:600}
 .note{color:#8a97b5;font-size:13px;margin-top:6px}
</style></head><body>
<header>
 <div class="brand">RRU <span>University Portal</span></div>
 <div class="who"><span><?=h($user)?></span><span class="pill"><?=h($role)?></span>
 <a class="logout" href="/oauth2/sign_out">Sign out</a></div>
</header>
<main>
 <h2>Welcome, <?=h($user)?></h2>
 <div class="sub">You are signed in as <b><?=h($role)?></b>. Your role decides what you can open.</div>

 <div class="grid">
  <div class="card"><h3>📋 Notices</h3><p>Semester exams begin 15 Nov. Library open till 9 PM.</p><span class="btn">Open</span></div>
  <div class="card"><h3>🏛 Faculty Zone</h3><p>Manage classes and grades. Faculty &amp; Director only.</p>
   <?php if($faculty||$director): ?><a class="btn" href="http://172.16.10.10:4181/">Enter</a>
   <?php else: ?><span class="btn lock">🔒 No access</span><?php endif; ?></div>
  <div class="card"><h3>⚙ Admin Zone</h3><p>Institution administration. Director only.</p>
   <?php if($director): ?><a class="btn" href="http://172.16.10.10:4182/">Enter</a>
   <?php else: ?><span class="btn lock">🔒 No access</span><?php endif; ?></div>
 </div>

 <?php if($denied): ?>
  <div class="deny">403 — You can view only your own results, not <?=h($_GET['student'])?>'s.</div>
  <div class="note">This is per-user access control: a student can never open another student's record.</div>
 <?php elseif($student && isset($results[$view])): $r=$results[$view]; ?>
  <div class="panel"><h3>My Results — <?=h($view)?></h3>
   <table><tr><th>Name</th><th>Semester</th><th>GPA</th><th>Backlogs</th></tr>
   <tr><td><?=h($r['name'])?></td><td><?=h($r['sem'])?></td><td><?=h($r['gpa'])?></td><td><?=h($r['backlog'])?></td></tr></table>
   <div class="note">You are seeing <b>your own</b> record. Try adding <code>?student=stud2</code> to the URL — you will get 403.</div>
  </div>
 <?php elseif($faculty||$director): ?>
  <div class="panel"><h3>Student Records (staff view)</h3>
   <table><tr><th>Username</th><th>Name</th><th>GPA</th><th></th></tr>
   <?php foreach($results as $u=>$r): ?>
    <tr><td><?=h($u)?></td><td><?=h($r['name'])?></td><td><?=h($r['gpa'])?></td>
    <td><a href="?student=<?=h($u)?>">view</a></td></tr>
   <?php endforeach; ?></table>
   <?php if($view && isset($results[$view])): $r=$results[$view]; ?>
    <p style="margin-top:14px"><b><?=h($view)?></b> — GPA <?=h($r['gpa'])?>, Sem <?=h($r['sem'])?>, Backlogs <?=h($r['backlog'])?></p>
   <?php endif; ?>
  </div>
 <?php endif; ?>
</main></body></html>
