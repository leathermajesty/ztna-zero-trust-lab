<?php
// ===== Faculty Zone (:4181) — proxy allows director + faculty only =====
$user = $_SERVER['HTTP_X_FORWARDED_PREFERRED_USERNAME'] ?? 'unknown';
$g = $_SERVER['HTTP_X_FORWARDED_GROUPS'] ?? '';
$roles = [];
foreach (explode(',', $g) as $x){ $x = trim($x); if (strncmp($x,'role:',5)===0) $roles[] = substr($x,5); }
$director = in_array('director',$roles);
$faculty  = in_array('faculty',$roles);
$role  = $director?'Director':'Faculty';
$badge = $director?'#8e44ad':'#1f8a82';

// --- classes owned per faculty (ownership demo: prof1 cannot open prof2's class) ---
$classes = [
  'CS101'=>['owner'=>'prof1','name'=>'Intro to Programming','students'=>42],
  'CS102'=>['owner'=>'prof1','name'=>'Data Structures','students'=>38],
  'EE201'=>['owner'=>'prof2','name'=>'Circuit Theory','students'=>35],
  'EE202'=>['owner'=>'prof2','name'=>'Signals &amp; Systems','students'=>30],
];
function h($s){ return htmlspecialchars($s, ENT_QUOTES); }
$open = $_GET['class'] ?? null;
$denied = false;
if ($open !== null) {
  if (!isset($classes[$open])) { $open = null; }
  elseif (!$director && $classes[$open]['owner'] !== $user) { $denied = true; }  // not your class
}
// list the classes this user may see
$mine = [];
foreach ($classes as $code=>$c){ if ($director || $c['owner']===$user) $mine[$code]=$c; }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Faculty Zone</title>
<style>
 *{box-sizing:border-box;margin:0;padding:0} body{font-family:Segoe UI,Arial,sans-serif;background:#eef5f4;color:#14302e}
 header{background:#145049;color:#fff;padding:18px 32px;display:flex;align-items:center;justify-content:space-between}
 header .brand{font-size:22px;font-weight:700} .who{display:flex;align-items:center;gap:14px;font-size:15px}
 .pill{background:<?=$badge?>;color:#fff;padding:5px 14px;border-radius:999px;font-weight:600;font-size:13px}
 a.logout{color:#cfe8e5;text-decoration:none;border:1px solid #2f6b63;padding:6px 12px;border-radius:8px}
 a.back{color:#cfe8e5;text-decoration:none}
 main{max-width:1000px;margin:28px auto;padding:0 20px} h2{font-size:28px;margin-bottom:6px}
 .sub{color:#4c6360;margin-bottom:22px}
 table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d3e3e0;border-radius:12px;overflow:hidden}
 td,th{padding:12px 14px;border-bottom:1px solid #eef5f4;text-align:left} th{background:#f3faf9}
 .btn{display:inline-block;background:#1f8a82;color:#fff;text-decoration:none;padding:7px 13px;border-radius:8px;font-weight:600;font-size:13px}
 .panel{background:#fff;border:1px solid #d3e3e0;border-radius:14px;padding:24px;margin-top:20px}
 .deny{background:#fdecea;border:1px solid #f5c2bd;color:#b42318;padding:16px 20px;border-radius:12px;font-weight:600;margin-top:20px}
 .note{color:#6b8481;font-size:13px;margin-top:8px}
</style></head><body>
<header><div class="brand">🏛 Faculty Zone</div>
 <div class="who"><span><?=h($user)?></span><span class="pill"><?=h($role)?></span>
 <a class="logout" href="/oauth2/sign_out">Sign out</a></div></header>
<main>
 <h2>Classes</h2>
 <div class="sub"><?=$director?'Director view — all classes.':'You see only the classes you teach.'?></div>
 <table><tr><th>Code</th><th>Subject</th><th>Owner</th><th>Students</th><th></th></tr>
 <?php foreach($mine as $code=>$c): ?>
  <tr><td><b><?=h($code)?></b></td><td><?=h($c['name'])?></td><td><?=h($c['owner'])?></td>
  <td><?=h($c['students'])?></td><td><a class="btn" href="?class=<?=h($code)?>">Open</a></td></tr>
 <?php endforeach; if(!$mine): ?><tr><td colspan="5">No classes assigned.</td></tr><?php endif; ?>
 </table>

 <?php if($denied): ?>
  <div class="deny">403 — <?=h($open)?> is not your class. You can open only classes you teach.</div>
  <div class="note">Per-user access control: prof1 and prof2 both have the faculty role, but cannot open each other's classes.</div>
 <?php elseif($open): $c=$classes[$open]; ?>
  <div class="panel"><h3><?=h($open)?> — <?=h($c['name'])?></h3>
   <p style="margin-top:8px;color:#4c6360">Owner: <?=h($c['owner'])?> · Enrolled: <?=h($c['students'])?> students</p>
   <p style="margin-top:10px"><a class="btn">Enter grades</a></p>
   <div class="note">You own this class, so you can manage it.</div></div>
 <?php endif; ?>
 <p style="margin-top:22px"><a class="back" style="color:#1f8a82" href="http://172.16.10.10:4180/">← Back to Portal</a></p>
</main></body></html>
