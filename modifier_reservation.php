<?php require __DIR__.'/app/core.php';must_login();go('reservation.php?id='.(int)($_GET['id']??0));
