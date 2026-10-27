<?php
// Tests unitaires autonomes (sans connexion MySQL).
require __DIR__.'/../app/core.php';
function check(bool $ok,string $name):void {if(!$ok){fwrite(STDERR,"ECHEC : $name\n");exit(1);}echo "OK : $name\n";}
check(valid_date('2026-11-05'),'Date valide');
check(!valid_date('2026-02-30'),'Date impossible rejetée');
check(valid_time('09:15'),'Heure valide');
check(!valid_time('25:15'),'Heure impossible rejetée');
check(h('<script>')==='&lt;script&gt;','Échappement XSS');
$tomorrow=(new DateTimeImmutable('+2 days'))->format('Y-m-d');
$input=['room_id'=>'1','booking_date'=>$tomorrow,'start_time'=>'10:00','end_time'=>'11:00','attendees'=>'5','title'=>'Réunion'];
check(reservation_data($input)['attendees']===5,'Réservation valide');
try{reservation_data($input+[]);check(true,'Validation première lecture');}catch(Throwable $e){check(false,'Validation première lecture');}
$invalid=$input;$invalid['start_time']='12:00';
try{reservation_data($invalid);check(false,'Conflit d’ordre des horaires');}catch(DomainException $e){check(true,'Ordre des horaires contrôlé');}
$invalid=$input;$invalid['attendees']='0';
try{reservation_data($invalid);check(false,'Capacité participants');}catch(DomainException $e){check(true,'Participants >0 contrôlés');}
check(status_text('pending')==='En attente','Statut en français');
echo "Tests unitaires sans base : succès.\n";
