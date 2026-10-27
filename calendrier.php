<?php require __DIR__.'/app/layout.php';must_login();$month=(string)($_GET['month']??date('Y-m'));
if(!preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D',$month)) $month=date('Y-m');
$startMonth=DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01')?:new DateTimeImmutable('first day of this month');
$nextMonth=$startMonth->modify('+1 month');
$monthRows=all_rows("SELECT r.*,s.name AS room_name FROM reservations r JOIN rooms s ON s.id=r.room_id WHERE r.booking_date>=? AND r.booking_date<? AND r.status IN ('pending','approved') ORDER BY r.booking_date,r.start_time",[$startMonth->format('Y-m-d'),$nextMonth->format('Y-m-d')]);
page_start('Calendrier','calendar');titlebar('Planning','Calendrier des réservations','Visualisez les créneaux occupés par jour, semaine ou mois.');?>
<div class="panel"><div id="calendar" style="min-height:580px"></div><div id="calendarFallback" class="mt-4"><div class="d-flex justify-content-between align-items-center mb-3"><a class="btn btn-sm btn-outline-primary" href="<?=h(url('calendrier.php?month='.$startMonth->modify('-1 month')->format('Y-m')))?>">‹ Mois précédent</a><h3 class="h5 mb-0"><?=h($startMonth->format('m/Y'))?></h3><a class="btn btn-sm btn-outline-primary" href="<?=h(url('calendrier.php?month='.$nextMonth->format('Y-m')))?>">Mois suivant ›</a></div>
<p class="small muted">Consultation hors ligne : liste des réunions du mois. La vue interactive jour/semaine/mois apparaît si FullCalendar est chargé.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Horaire</th><th>Salle</th><th>Réunion</th><th>Statut</th></tr></thead><tbody>
<?php foreach($monthRows as $row):?><tr><td><?=h($row['booking_date'])?></td><td><?=h(substr($row['start_time'],0,5).' - '.substr($row['end_time'],0,5))?></td><td><?=h($row['room_name'])?></td><td><?=h($row['title'])?></td><td><?=pill($row['status'])?></td></tr><?php endforeach;?>
<?php if(!$monthRows):?><tr><td colspan="5" class="text-center muted py-4">Aucune réunion ce mois-ci.</td></tr><?php endif;?></tbody></table></div></div></div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css"><script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
window.addEventListener('load',()=>{if(!window.FullCalendar)return;document.getElementById('calendarFallback').hidden=true;const calendar=new FullCalendar.Calendar(document.getElementById('calendar'),{
 locale:'fr',firstDay:1,initialView:'dayGridMonth',height:'auto',nowIndicator:true,
 headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek,timeGridDay'},
 buttonText:{today:"Aujourd’hui",month:'Mois',week:'Semaine',day:'Jour'},
 events:window.EMS.base+'api_evenements.php',eventClick:e=>{alert(e.event.title+'\n'+e.event.start.toLocaleString('fr-FR'));}
});calendar.render();});
</script>
<?php page_end();?>
