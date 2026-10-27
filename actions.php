<?php
require __DIR__.'/app/core.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Méthode non autorisée'); }
verify_csrf();
$action=(string)($_POST['action']??'');
$back='dashboard.php';
try {
    if ($action==='login') {
        $back='index.php';
        $login=trim((string)($_POST['login']??'')); $pass=(string)($_POST['password']??'');
        $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'local').'|'.mb_strtolower($login));
        db()->prepare('DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)')->execute();
        if(scalar('SELECT COUNT(*) FROM login_attempts WHERE attempt_key=?',[$key])>=5) {
            throw new DomainException('Trop de tentatives. Réessayez dans 15 minutes.');
        }
        $u=ctype_digit($login) ? one_row('SELECT * FROM users WHERE id=?',[(int)$login]) : one_row('SELECT * FROM users WHERE email=?',[$login]);
        if (!$u || !(int)$u['active'] || !password_verify($pass,$u['password_hash'])) {
            db()->prepare('INSERT INTO login_attempts (attempt_key) VALUES (?)')->execute([$key]);
            throw new DomainException('Identifiant ou mot de passe incorrect.');
        }
        db()->prepare('DELETE FROM login_attempts WHERE attempt_key=?')->execute([$key]);
        if(password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass,PASSWORD_DEFAULT),$u['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id']=(int)$u['id'];
        flash('Bienvenue, '.$u['name'].' !'); go('dashboard.php');
    }
    $user=must_login();
    if($action==='reservation_create' || $action==='reservation_update') {
        $back=$action==='reservation_update'?'mesreservations.php':'reservation.php';
        $r=reservation_data($_POST);
        $id=(int)($_POST['id']??0);
        if($action==='reservation_update') {
            $existing=one_row('SELECT * FROM reservations WHERE id=?',[$id]);
            if(!$existing) throw new DomainException('Réservation introuvable.');
            if(!is_admin() && (int)$existing['user_id']!==(int)$user['id']) throw new DomainException('Action non autorisée.');
            if(!can_change($existing)) throw new DomainException('Cette réservation ne peut plus être modifiée.');
        }
        $pdo=db(); $pdo->beginTransaction();
        try {
            locked_room($r['room_id']);
            if($action==='reservation_update') {
                $current=one_row('SELECT * FROM reservations WHERE id=? FOR UPDATE',[$id]);
                if(!$current || $current['status']!==$existing['status']) throw new DomainException('Réservation modifiée entre-temps, recommencez.');
            }
            assert_room_available($r,$action==='reservation_update'?$id:0);
            if($action==='reservation_create') {
                $status=option('approval_required','0')==='1'?'pending':'approved';
                $pdo->prepare('INSERT INTO reservations (user_id,room_id,title,description,booking_date,start_time,end_time,attendees,status,remarks) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$user['id'],$r['room_id'],$r['title'],$r['description'],$r['booking_date'],$r['start_time'],$r['end_time'],$r['attendees'],$status,$r['remarks']]);
                $id=(int)$pdo->lastInsertId();
                notify((int)$user['id'],"Réservation #$id créée (".status_text($status).').');
            } else {
                $pdo->prepare('UPDATE reservations SET room_id=?,title=?,description=?,booking_date=?,start_time=?,end_time=?,attendees=?,remarks=? WHERE id=?')
                    ->execute([$r['room_id'],$r['title'],$r['description'],$r['booking_date'],$r['start_time'],$r['end_time'],$r['attendees'],$r['remarks'],$id]);
                notify((int)$existing['user_id'],"Réservation #$id modifiée.");
            }
            log_action($action,"Réservation #$id");
            $pdo->commit();
        } catch(Throwable $e){$pdo->rollBack();throw $e;}
        flash('Réservation enregistrée avec succès.'); go('mesreservations.php');
    }
    if($action==='reservation_cancel' || $action==='reservation_review') {
        $back=is_admin()?'reservations.php':'mesreservations.php';
        $id=(int)($_POST['id']??0);
        $pdo=db();$pdo->beginTransaction();
        try {
            $res=one_row('SELECT * FROM reservations WHERE id=? FOR UPDATE',[$id]);
            if(!$res) throw new DomainException('Réservation introuvable.');
            if($action==='reservation_cancel') {
                if(!is_admin() && (int)$res['user_id']!==(int)$user['id']) throw new DomainException('Action non autorisée.');
                if(!can_change($res)) throw new DomainException('Cette réservation ne peut plus être annulée.');
                $new='cancelled';
            } else {
                must_admin();
                if($res['status']!=='pending') throw new DomainException('Seules les demandes en attente peuvent être traitées.');
                $new=(string)($_POST['decision']??'');
                if(!in_array($new,['approved','rejected'],true)) throw new DomainException('Décision invalide.');
                if($new==='approved') {
                    locked_room((int)$res['room_id']);
                    assert_room_available(['room_id'=>(int)$res['room_id'],'booking_date'=>$res['booking_date'],
                    'start_time'=>substr($res['start_time'],0,5),'end_time'=>substr($res['end_time'],0,5),'attendees'=>(int)$res['attendees']],$id);
                }
            }
            $pdo->prepare('UPDATE reservations SET status=? WHERE id=?')->execute([$new,$id]);
            notify((int)$res['user_id'],"Réservation #$id : ".status_text($new).'.');
            log_action('reservation_status',"#$id → $new");
            $pdo->commit();
        } catch(Throwable $e){$pdo->rollBack();throw $e;}
        flash('Statut de réservation mis à jour.');go($back);
    }
    if($action==='report_create') {
        $back='material.php';
        $room=(int)($_POST['room_id']??0);
        $item=trim((string)($_POST['missing_item']??''));
        $desc=trim((string)($_POST['description']??''));
        if(!$room || mb_strlen($item)<2 || mb_strlen($item)>190 || mb_strlen($desc)>3000 || !one_row('SELECT id FROM rooms WHERE id=?',[$room])) throw new DomainException('Vérifiez la salle et le matériel déclaré.');
        db()->prepare('INSERT INTO material_reports (user_id,room_id,missing_item,description) VALUES (?,?,?,?)')->execute([$user['id'],$room,$item,$desc]);
        log_action('material_report',$item);flash('Déclaration de matériel enregistrée.');go($back);
    }
    if($action==='profile_save') {
        $back='profil.php';
        $name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));
        if(mb_strlen($name)<2 || mb_strlen($name)>150 || ($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))) throw new DomainException('Nom ou email invalide.');
        $old=(string)($_POST['old_password']??'');$new=(string)($_POST['new_password']??'');
        if($new!=='') {
            $hashed=one_row('SELECT password_hash FROM users WHERE id=?',[$user['id']]);
            if(!password_verify($old,$hashed['password_hash']) || strlen($new)<10) throw new DomainException('Ancien mot de passe incorrect ou nouveau mot de passe trop court (10 caractères minimum).');
            db()->prepare('UPDATE users SET name=?,email=?,password_hash=? WHERE id=?')->execute([$name,$email?:null,password_hash($new,PASSWORD_DEFAULT),$user['id']]);
        } else db()->prepare('UPDATE users SET name=?,email=? WHERE id=?')->execute([$name,$email?:null,$user['id']]);
        flash('Profil mis à jour.');go($back);
    }
    if($action==='notice_read') {
        $back='notifications.php';
        if(isset($_POST['all'])) db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]);
        else db()->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([(int)($_POST['id']??0),$user['id']]);
        go($back);
    }
    // Ci-dessous : actions réservées aux administrateurs.
    must_admin();
    if($action==='room_save') {
        $back='gestion.php';$id=(int)($_POST['id']??0);
        $name=trim((string)($_POST['name']??''));
        $desc=trim((string)($_POST['description']??''));
        $location=trim((string)($_POST['location']??''));$equipment=trim((string)($_POST['equipment']??''));
        $cap=filter_var($_POST['capacity']??null,FILTER_VALIDATE_INT);
        $status=(string)($_POST['status']??'active');
        if(mb_strlen($name)<2||mb_strlen($name)>150||mb_strlen($location)>150||mb_strlen($desc)>5000||mb_strlen($equipment)>2000||!$cap||$cap<1||$cap>100000||!in_array($status,['active','maintenance','archived'],true)) throw new DomainException('Complétez le nom, la capacité (au moins 1) et le statut de la salle.');
        $old=$id?one_row('SELECT * FROM rooms WHERE id=?',[$id]):null;
        if($id&&!$old) throw new DomainException('Salle introuvable.');
        $image=safe_image_upload($_FILES['image']??[],$old['image']??null);
        if($id) {
            db()->prepare('UPDATE rooms SET name=?,description=?,capacity=?,location=?,equipment=?,status=?,image=? WHERE id=?')
            ->execute([$name,$desc,$cap,$location,$equipment,$status,$image,$id]);
        } else {
            db()->prepare('INSERT INTO rooms (name,description,capacity,location,equipment,status,image) VALUES (?,?,?,?,?,?,?)')
            ->execute([$name,$desc,$cap,$location,$equipment,$status,$image]);
            $id=(int)db()->lastInsertId();
        }
        log_action('room_save',"Salle #$id");flash('Salle enregistrée.');go($back);
    }
    if($action==='room_archive') {
        $back='gestion.php'; $id=(int)($_POST['id']??0);
        $room=one_row('SELECT id FROM rooms WHERE id=?',[$id]);if(!$room) throw new DomainException('Salle introuvable.');
        db()->prepare("UPDATE rooms SET status='archived' WHERE id=?")->execute([$id]);
        log_action('room_archive',"Salle #$id");flash('Salle archivée (historique conservé).');go($back);
    }
    if($action==='user_save') {
        $back='utilisateurs.php';$id=(int)($_POST['id']??0);
        $name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));
        $role=(string)($_POST['role']??'user');$pass=(string)($_POST['password']??'');
        if(mb_strlen($name)<2||mb_strlen($name)>150||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))||!in_array($role,['admin','user'],true)) throw new DomainException('Nom, email ou rôle invalide.');
        if($id) {
            if($id===(int)$user['id'] && $role!=='admin') throw new DomainException('Vous ne pouvez pas retirer votre propre rôle administrateur.');
            if(!one_row('SELECT id FROM users WHERE id=?',[$id])) throw new DomainException('Utilisateur introuvable.');
            if($pass!=='' && strlen($pass)<10) throw new DomainException('Mot de passe : 10 caractères minimum.');
            if($pass!=='') db()->prepare('UPDATE users SET name=?,email=?,role=?,password_hash=? WHERE id=?')->execute([$name,$email?:null,$role,password_hash($pass,PASSWORD_DEFAULT),$id]);
            else db()->prepare('UPDATE users SET name=?,email=?,role=? WHERE id=?')->execute([$name,$email?:null,$role,$id]);
        } else {
            if(strlen($pass)<10) throw new DomainException('Mot de passe requis (10 caractères minimum).');
            db()->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)')->execute([$name,$email?:null,password_hash($pass,PASSWORD_DEFAULT),$role]);
            $id=(int)db()->lastInsertId();
        }
        log_action('user_save',"Utilisateur #$id");flash('Utilisateur enregistré.');go($back);
    }
    if($action==='user_toggle') {
        $back='utilisateurs.php';$id=(int)($_POST['id']??0);
        if($id===(int)$user['id']) throw new DomainException('Impossible de désactiver votre propre compte.');
        if(!one_row('SELECT id FROM users WHERE id=?',[$id])) throw new DomainException('Utilisateur introuvable.');
        db()->prepare('UPDATE users SET active=1-active WHERE id=?')->execute([$id]);
        log_action('user_toggle',"Utilisateur #$id");flash('État utilisateur mis à jour.');go($back);
    }
    if($action==='settings_save') {
        $back='parametres.php';
        $approval=isset($_POST['approval_required'])?'1':'0';
        $cutoff=filter_var($_POST['edit_cutoff_hours']??0,FILTER_VALIDATE_INT);
        if($cutoff===false||$cutoff<0||$cutoff>168) throw new DomainException('Délai de modification invalide (0 à 168 h).');
        $st=db()->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        $st->execute(['approval_required',$approval]);$st->execute(['edit_cutoff_hours',(string)$cutoff]);
        log_action('settings_save');flash('Paramètres enregistrés.');go($back);
    }
    if($action==='reminders_send') {
        $back='notifications.php';
        // Rappels seulement pour les réservations prochaines, doublons évités via marqueur log.
        $rows=all_rows("SELECT r.id,r.user_id,r.title FROM reservations r WHERE r.status='approved' AND TIMESTAMP(r.booking_date,r.start_time) > NOW() AND TIMESTAMP(r.booking_date,r.start_time) <= DATE_ADD(NOW(),INTERVAL 24 HOUR) AND NOT EXISTS (SELECT 1 FROM activity_logs l WHERE l.action='reminder_sent' AND l.details=CONCAT('reservation:',r.id))");
        foreach($rows as $row) { notify((int)$row['user_id'],'Rappel : votre réunion « '.$row['title'].' » commence dans moins de 24 h.');
            db()->prepare('INSERT INTO activity_logs (action,details) VALUES (?,?)')->execute(['reminder_sent','reservation:'.$row['id']]); }
        flash(count($rows).' rappel(s) créé(s).');go($back);
    }
    throw new DomainException('Action inconnue.');
} catch (DomainException $e) {
    flash($e->getMessage(),'danger');go($back);
} catch (PDOException $e) {
    error_log('[EMS SQL] '.$e->getMessage());
    flash($e->getCode()==='23000'?'Une valeur existe déjà (email ou relation).':'Erreur de base de données, voir le journal PHP.','danger');go($back);
} catch (Throwable $e) {
    error_log('[EMS] '.$e->getMessage());
    flash('Une erreur est survenue, vérifiez les journaux PHP.','danger');go($back);
}
