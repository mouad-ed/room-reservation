<?php
require __DIR__.'/app/core.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405);exit('Méthode non autorisée.'); }
verify_csrf();
$_SESSION=[];
session_regenerate_id(true);
session_destroy();
header('Location: '.url('index.php'),true,303);exit;
