<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();
if (!challenges_window_open()) { http_response_code(403); redirect('/403.php'); }
csrf_validate();

$u = current_user();
$user_id = (int)$u['id'];

rate_limit_submit($user_id);

$challenge_id = (int)($_POST['challenge_id'] ?? 0);
$flag = trim((string)($_POST['flag'] ?? ''));

if ($challenge_id<=0 || $flag==='') { flash_set('danger','Invalid submission.'); redirect('/challenges.php'); }

$stmt = db()->prepare("SELECT id,title,points,flag_hash FROM challenges WHERE id=? AND is_active=1");
$stmt->execute([$challenge_id]);
$c = $stmt->fetch();
if (!$c) { flash_set('danger','Challenge not found.'); redirect('/challenges.php'); }

$stmt2 = db()->prepare("SELECT 1 FROM solves WHERE user_id=? AND challenge_id=? LIMIT 1");
$stmt2->execute([$user_id,$challenge_id]);
if ($stmt2->fetchColumn()) { flash_set('info','Already solved.'); redirect('/challenge.php?id='.$challenge_id); }

$is_correct = password_verify($flag, $c['flag_hash'] ?? '');

db()->prepare("INSERT INTO submissions (user_id,challenge_id,submitted_flag,is_correct,ip_addr,created_at) VALUES (?,?,?,?,?,NOW())")
   ->execute([$user_id,$challenge_id,$flag,$is_correct?1:0,ip_address()]);

if (!$is_correct) { flash_set('danger','Incorrect flag.'); redirect('/challenge.php?id='.$challenge_id); }

$pdo = db();
$pdo->beginTransaction();
try {
  $pdo->prepare("INSERT INTO solves (user_id,challenge_id,points_awarded,solved_at) VALUES (?,?,?,NOW())")
      ->execute([$user_id,$challenge_id,(int)$c['points']]);
  $pdo->commit();
} catch (PDOException $e) {
  $pdo->rollBack();
  flash_set('danger','Could not record solve (maybe already solved).');
}
flash_set('success','Correct! Points awarded.');
redirect('/challenge.php?id='.$challenge_id);
