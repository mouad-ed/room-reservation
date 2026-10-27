<?php require __DIR__.'/app/layout.php';must_admin();
$rows=all_rows('SELECT a.*,u.name AS actor FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 300');
page_start('Historique des actions','history');titlebar('Journal','Historique des actions','Traçabilité des changements récents.'); ?>
<div class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Détails</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=h($r['created_at'])?></td><td><?=h($r['actor']??'Système')?></td><td><?=h($r['action'])?></td><td><?=h($r['details'])?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="4" class="empty-state">Aucune activité enregistrée.</td></tr><?php endif;?></tbody></table></div></div>
<?php page_end();?>
