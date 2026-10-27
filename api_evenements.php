<?php require __DIR__.'/app/core.php';header('Content-Type: application/json; charset=utf-8');
if(!me()){http_response_code(401);echo '[]';exit;}
$start=(string)($_GET['start']??'');$end=(string)($_GET['end']??'');
if(!preg_match('/^\d{4}-\d\d-\d\d/',$start)||!preg_match('/^\d{4}-\d\d-\d\d/',$end)){echo '[]';exit;}
$rows=all_rows("SELECT r.id,r.title,r.booking_date,r.start_time,r.end_time,r.status,s.name AS room_name FROM reservations r JOIN rooms s ON s.id=r.room_id WHERE r.booking_date>=? AND r.booking_date<? AND r.status IN ('approved','pending') LIMIT 3000",[substr($start,0,10),substr($end,0,10)]);
$events=[];foreach($rows as $r) $events[]=['id'=>(string)$r['id'],'title'=>$r['room_name'].' · '.$r['title'],'start'=>$r['booking_date'].'T'.$r['start_time'],'end'=>$r['booking_date'].'T'.$r['end_time'],'color'=>$r['status']==='pending'?'#eba44e':'#6566e8'];
echo json_encode($events,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);
