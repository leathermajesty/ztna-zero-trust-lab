<?php
// ===== Admin Zone (:4182) — proxy allows director ONLY =====
$user = $_SERVER['HTTP_X_FORWARDED_PREFERRED_USERNAME'] ?? 'unknown';
function h($s){ return htmlspecialchars($s, ENT_QUOTES); }
$users = [
  ['dean','Director'],['prof1','Faculty'],['prof2','Faculty'],['stud1','Student'],['stud2','Student'],
];
$depts = [['Computer Science',2,80],['Electronics',2,65]];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin Zone</title>
<style>
 *{box-sizing:border-box;margin:0;padding:0} body{font-family:Segoe UI,Arial,sans-serif;background:#f6f2fb;color:#2a1f3d}
 header{background:#4a235a;color:#fff;padding:18px 32px;display:flex;align-items:center;justify-content:space-between}
 header .brand{font-size:22px;font-weight:700} .who{display:flex;align-items:center;gap:14px;font-size:15px}
 .pill{background:#8e44ad;color:#fff;padding:5px 14px;border-radius:999px;font-weight:600;font-size:13px}
 a.logout{color:#e6d6ef;text-decoration:none;border:1px solid #6c3483;padding:6px 12px;border-radius:8px}
 main{max-width:1000px;margin:28px auto;padding:0 20px} h2{font-size:28px;margin-bottom:6px}
 .sub{color:#6b5b7e;margin-bottom:22px}
 .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
 .panel{background:#fff;border:1px solid #e4d7ef;border-radius:14px;padding:22px}
 table{width:100%;border-collapse:collapse} td,th{padding:9px 12px;border-bottom:1px solid #f0e8f6;text-align:left} th{color:#6b5b7e}
 .badge{background:#f6f2fb;border:1px solid #e4d7ef;padding:12px 16px;border-radius:12px;margin-bottom:20px;color:#6c3483;font-weight:600}
 a.back{color:#8e44ad;text-decoration:none}
</style></head><body>
<header><div class="brand">⚙ Admin Zone</div>
 <div class="who"><span><?=h($user)?></span><span class="pill">Director</span>
 <a class="logout" href="/oauth2/sign_out">Sign out</a></div></header>
<main>
 <h2>Institution Administration</h2>
 <div class="sub">Director-only area. Faculty and students are blocked by the proxy (403) before this page loads.</div>
 <div class="badge">🔐 You reached this because your token carries the <b>director</b> role. Everyone else gets 403 at the gate.</div>
 <div class="grid">
  <div class="panel"><h3>All Users &amp; Roles</h3>
   <table><tr><th>Username</th><th>Role</th></tr>
   <?php foreach($users as $u): ?><tr><td><?=h($u[0])?></td><td><?=h($u[1])?></td></tr><?php endforeach; ?></table></div>
  <div class="panel"><h3>Departments</h3>
   <table><tr><th>Department</th><th>Faculty</th><th>Students</th></tr>
   <?php foreach($depts as $d): ?><tr><td><?=h($d[0])?></td><td><?=h($d[1])?></td><td><?=h($d[2])?></td></tr><?php endforeach; ?></table></div>
 </div>
 <p style="margin-top:22px"><a class="back" href="http://172.16.10.10:4180/">← Back to Portal</a></p>
</main></body></html>
