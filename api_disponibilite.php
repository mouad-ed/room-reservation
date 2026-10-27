<?php
require __DIR__.'/app/core.php';
header('Content-Type: application/json; charset=utf-8');
if (!me()){http_response_code(401);echo json_encode(['error'=>'Authentification nécessaire.']);exit;}
$date=(string)($_GET['booking_date']??'');$start=(string)($_GET['start_time']??'');$end=(string)($_GET['end_time']??'');$n=filter_var($_GET['attendees']??null,FILTER_VALIDATE_INT);
if(!valid_date($date)||!valid_time($start)||!valid_time($end)||$start >= $end||($date<date('Y-m-d')||($date===date('Y-m-d')&&$start<=date('H:i')))||(!$n||$n<1)){
 http_response_code(422);echo json_encode(['error'=>'Date, horaires ou nombre de participants invalides.']);exit;
}
$equipment=trim((string)($_GET['equipment']??''));
$rows=all_rows("SELECT s.id,s.name,s.capacity FROM rooms s WHERE s.status='active' AND s.capacity>=? AND NOT EXISTS (SELECT 1 FROM reservations r WHERE r.room_id=s.id AND r.booking_date=? AND r.start_time<? AND r.end_time>? AND r.status IN ('pending','approved')) ORDER BY s.name",[(int)$n,$date,$end,$start]);
echo json_encode(['rooms'=>$rows],JSON_UNESCAPED_UNICODE);
