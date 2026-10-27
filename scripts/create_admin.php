<?php
// Executer avec C:\xampp\php\php.exe scripts\create_admin.php
if(PHP_SAPI!=='cli') {http_response_code(403);exit('Exécution terminal uniquement.');}
require __DIR__.'/../app/core.php';
if(scalar('SELECT COUNT(*) FROM users')>0){fwrite(STDERR,"Un utilisateur existe déjà. Utilisez Gestion des utilisateurs.\n");exit(1);}
echo "Nom administrateur : ";$name=trim((string)fgets(STDIN));
echo "Mot de passe (min. 10 caractères, saisie visible dans ce terminal) : ";$pass=rtrim((string)fgets(STDIN),"\r\n");
if(strlen($name)<2||strlen($pass)<10){fwrite(STDERR,"Nom ou mot de passe invalide.\n");exit(1);}
$st=db()->prepare("INSERT INTO users (name,password_hash,role) VALUES (?,?,'admin')");
$st->execute([$name,password_hash($pass,PASSWORD_DEFAULT)]);
echo "Compte créé. Identifiant numérique : ".db()->lastInsertId()."\n";
