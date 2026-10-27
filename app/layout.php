<?php
require_once __DIR__.'/core.php';
function page_start(string $title,string $active='dashboard'): void {
    $user=must_login();
    $count=scalar('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0',[$user['id']]);
    $menu=[['dashboard','dashboard.php','grid-1x2','Tableau de bord'],['rooms','salles.php','door-open','Salles'],
      ['mine','mesreservations.php','calendar-check','Mes réservations'],['calendar','calendrier.php','calendar3','Calendrier'],
      ['material','material.php','tools','Matériel'],['notices','notifications.php','bell','Notifications']];
    if(is_admin()) $menu=array_merge($menu,[['reservations','reservations.php','calendar2-week','Toutes les réservations'],
      ['management','gestion.php','building-gear','Gestion des salles'],['users','utilisateurs.php','people','Utilisateurs'],
      ['history','historique.php','clock-history','Historique'],['settings','parametres.php','sliders','Paramètres']]);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h($title)?> · <?=h(cfg('app_name'))?></title>
<link rel="icon" href="<?=h(url('assets/icon.svg'))?>" type="image/svg+xml">
<link href="<?=h(url('assets/vendor/bootstrap.min.css'))?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="<?=h(url('assets/css/app.css'))?>"></head>
<body>
<div class="app-shell"><aside class="sidebar" id="sidebar">
<a class="brand" href="<?=h(url('dashboard.php'))?>"><span class="brand-mark"><i class="bi bi-grid-3x3-gap-fill"></i></span><span>EMS<span class="brand-sub"> Workspace</span></span></a>
<div class="side-caption">ESPACE DE TRAVAIL</div>
<nav class="side-nav" aria-label="Navigation principale">
<?php foreach($menu as [$k,$file,$ico,$label]): ?>
<a class="side-link <?= $k===$active?'active':'' ?>" href="<?=h(url($file))?>"><i class="bi bi-<?=h($ico)?>"></i><span><?=h($label)?></span>
<?php if($k==='notices' && $count):?><span class="count-pill"><?=h($count)?></span><?php endif;?></a>
<?php endforeach;?></nav>
<div class="sidebar-bottom"><div class="identity-badge"><span class="avatar"><?=h(mb_strtoupper(mb_substr($user['name'],0,1)))?></span><span><strong><?=h(textclip($user['name'],16))?></strong><small><?=is_admin()?'Administrateur':'Utilisateur'?></small></span></div></div>
</aside><div class="shade" id="mobileShade"></div><div class="main-area">
<header class="topbar"><div class="d-flex align-items-center gap-3"><button class="icon-btn mobile-menu" id="menuToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button><div><span class="breadcrumb-top">EMS / Plateforme</span><div class="top-title"><?=h($title)?></div></div></div>
<div class="top-actions"><button id="themeToggle" class="icon-btn" title="Changer de thème" aria-label="Changer de thème"><i class="bi bi-moon-stars"></i></button><a href="<?=h(url('notifications.php'))?>" class="icon-btn" aria-label="Notifications"><i class="bi bi-bell"></i><?php if($count):?><span class="dot"></span><?php endif;?></a><a class="user-shortcut" href="<?=h(url('profil.php'))?>"><span class="avatar avatar-sm"><?=h(mb_strtoupper(mb_substr($user['name'],0,1)))?></span><span class="d-none d-md-inline"><?=h(textclip($user['name'],15))?></span></a>
<form action="<?=h(url('logout.php'))?>" method="post" class="m-0"><?=csrf_field()?><button class="icon-btn" title="Déconnexion" aria-label="Se déconnecter"><i class="bi bi-box-arrow-right"></i></button></form></div></header>
<main class="content"><div class="content-inner"><?php show_flash(); ?>
<?php }
function page_end(): void { ?>
</div></main><footer class="copyright">© <?=date('Y')?> E-Market Solutions <span>• Gestion des espaces de réunion</span></footer></div></div>
<script>window.EMS={base: <?=json_encode(rtrim(url(''),'/').'/',JSON_HEX_TAG|JSON_HEX_AMP)?>,csrf: <?=json_encode(csrf(),JSON_HEX_TAG|JSON_HEX_AMP)?>};</script>
<script src="<?=h(url('assets/vendor/bootstrap.bundle.min.js'))?>"></script><script src="<?=h(url('assets/js/app.js'))?>"></script></body></html>
<?php }
function titlebar(string $eyebrow,string $title,string $subtitle=''):void {?>
<div class="page-heading"><div><div class="eyebrow"><?=h($eyebrow)?></div><h1><?=h($title)?></h1><?php if($subtitle):?><p><?=h($subtitle)?></p><?php endif;?></div></div>
<?php }
function submit_button(string $label='Enregistrer'):void { ?><button class="btn btn-primary px-4"><i class="bi bi-check2-circle me-2"></i><?=h($label)?></button><?php }
