<?php
declare(strict_types=1);
// Compatibilite avec installations PHP sans extension mbstring.
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return strlen($s); } }
if (!function_exists('mb_substr')) { function mb_substr(string $s,int $start,?int $len=null): string { return $len===null?substr($s,$start):substr($s,$start,$len); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s): string { return strtolower($s); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $s): string { return strtoupper($s); } }
if (!defined('EMS_BOOTSTRAPPED')) {
    define('EMS_BOOTSTRAPPED', true);
    $config = require __DIR__ . '/../config/config.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_name('ems_meeting_session');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_start();
    }
}
function cfg(string $key): string { global $config; return (string)($config[$key] ?? ''); }
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $dsn = 'mysql:host='.cfg('host').';port='.cfg('port').';dbname='.cfg('database').';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, cfg('username'), cfg('password'), [
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
            // Aligner NOW()/CURDATE() de MySQL sur le fuseau de PHP.
            $pdo->exec('SET time_zone = '.$pdo->quote(date('P')));
        } catch (PDOException $e) {
            error_log('[EMS database] '.$e->getMessage());
            http_response_code(503);
            exit('Connexion MySQL impossible. Vérifiez Apache/MySQL, config/local.php et importez database/install.sql.');
        }
    }
    return $pdo;
}
function url(string $path=''): string {
    $folder = str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $prefix = $folder === '/' || $folder === '.' ? '' : rtrim($folder,'/');
    return $prefix.'/'.ltrim($path,'/');
}
function h($val): string { return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function go(string $path): void { header('Location: '.url($path), true, 303); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.h(csrf()).'">'; }
function verify_csrf(): void {
    $given = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($given) || !hash_equals(csrf(), $given)) {
        http_response_code(419); exit('Jeton CSRF invalide. Rechargez la page.');
    }
}
function me(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = false;
    if ($user === false) {
        $st = db()->prepare('SELECT id,name,email,role,legacy_post,active FROM users WHERE id=?');
        $st->execute([(int)$_SESSION['user_id']]);
        $user = $st->fetch() ?: null;
        if (!$user || !(int)$user['active']) { unset($_SESSION['user_id']); $user=null; }
    }
    return $user;
}
function must_login(): array { $u=me(); if (!$u) go('index.php'); return $u; }
function is_admin(): bool { return me() !== null && me()['role'] === 'admin'; }
function must_admin(): void { must_login(); if (!is_admin()) { http_response_code(403); exit('Accès réservé aux administrateurs.'); } }
function flash(string $message, string $type='success'): void { $_SESSION['flash'] = [$message, $type]; }
function show_flash(): void {
    if (!isset($_SESSION['flash'])) return;
    [$m,$kind] = $_SESSION['flash']; unset($_SESSION['flash']);
    $kind = in_array($kind,['success','danger','warning','info'],true) ? $kind : 'info';
    echo '<div class="alert alert-'.h($kind).' alert-dismissible fade show" role="alert">'.h($m).'<button class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>';
}
function all_rows(string $sql, array $params=[]): array { $st=db()->prepare($sql);$st->execute($params);return $st->fetchAll(); }
function one_row(string $sql,array $params=[]): ?array { $st=db()->prepare($sql);$st->execute($params);return $st->fetch() ?: null; }
function scalar(string $sql,array $params=[]): int { $st=db()->prepare($sql);$st->execute($params);return (int)$st->fetchColumn(); }
function option(string $name,string $default=''): string {
    $st=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$st->execute([$name]);
    $val=$st->fetchColumn();return $val===false?$default:(string)$val;
}
function notify(int $userId,string $message): void {
    $st=db()->prepare('INSERT INTO notifications (user_id,message) VALUES (?,?)');
    $st->execute([$userId, mb_substr($message,0,255)]);
}
function log_action(string $action,string $detail=''): void {
    db()->prepare('INSERT INTO activity_logs (user_id,action,details) VALUES (?,?,?)')
        ->execute([me()['id'] ?? null, $action, mb_substr($detail,0,255)]);
}
function status_text(string $status): string {
    return ['pending'=>'En attente','approved'=>'Confirmée','cancelled'=>'Annulée','rejected'=>'Refusée',
        'active'=>'Disponible','maintenance'=>'Maintenance','archived'=>'Archivée'][$status] ?? $status;
}
function pill(string $status): string {
    $map=['approved'=>'success','active'=>'success','pending'=>'warning','maintenance'=>'warning','cancelled'=>'secondary','rejected'=>'danger','archived'=>'secondary'];
    return '<span class="badge rounded-pill text-bg-'.($map[$status]??'secondary').'">'.h(status_text($status)).'</span>';
}
function valid_date(string $str): bool {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$str);
    return $d !== false && $d->format('Y-m-d') === $str;
}
function valid_time(string $str): bool { return (bool)preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$str); }
function reservation_data(array $source): array {
    $roomId=filter_var($source['room_id']??null,FILTER_VALIDATE_INT);
    $date=trim((string)($source['booking_date']??''));
    $start=substr((string)($source['start_time']??''),0,5);
    $end=substr((string)($source['end_time']??''),0,5);
    $people=filter_var($source['attendees']??null,FILTER_VALIDATE_INT);
    $title=trim((string)($source['title']??''));
    if (!$roomId || $roomId<1 || !valid_date($date) || !valid_time($start) || !valid_time($end) || $start >= $end || !$people || $people < 1 || $people > 100000 || $title==='' || mb_strlen($title)>190) {
        throw new DomainException('Vérifiez la salle, le titre, la date, les horaires et le nombre de participants.');
    }
    if ($date < date('Y-m-d') || ($date === date('Y-m-d') && $start <= date('H:i'))) {
        throw new DomainException('La réservation doit commencer dans le futur.');
    }
    $description=trim((string)($source['description']??''));
    $remarks=trim((string)($source['remarks']??''));
    if(mb_strlen($description)>5000 || mb_strlen($remarks)>2000) throw new DomainException('Texte trop long.');
    return ['room_id'=>(int)$roomId,'booking_date'=>$date,'start_time'=>$start,'end_time'=>$end,'attendees'=>(int)$people,'title'=>$title,'description'=>$description,'remarks'=>$remarks];
}
// Doit etre appelee a l'interieur d'une transaction ayant verrouille rooms.id FOR UPDATE.
function assert_room_available(array $r, int $excludeId=0): void {
    $room=one_row('SELECT id,capacity,status FROM rooms WHERE id=?',[$r['room_id']]);
    if(!$room || $room['status']!=='active') throw new DomainException('Salle indisponible ou en maintenance.');
    if ($room['capacity'] === null || (int)$room['capacity'] < 1) throw new DomainException('La capacité de cette salle doit être renseignée par un administrateur avant réservation.');
    if((int)$r['attendees']>(int)$room['capacity']) throw new DomainException('Le nombre de participants dépasse la capacité de la salle.');
    $overlap=scalar("SELECT COUNT(*) FROM reservations WHERE room_id=? AND booking_date=? AND start_time < ? AND end_time > ? AND status IN ('pending','approved') AND id<>?",[
        $r['room_id'],$r['booking_date'],$r['end_time'],$r['start_time'],$excludeId]);
    if($overlap) throw new DomainException('Cette salle est déjà réservée pendant le créneau choisi.');
}
function locked_room(int $roomId): void {
    $st=db()->prepare('SELECT id FROM rooms WHERE id=? FOR UPDATE');$st->execute([$roomId]);
    if(!$st->fetch()) throw new DomainException('Salle introuvable.');
}
function can_change(array $r): bool {
    if ($r['status']==='cancelled' || $r['status']==='rejected') return false;
    $date=$r['booking_date'].' '.substr($r['start_time'],0,5);
    $cutoff=max(0,(int)option('edit_cutoff_hours','0'));
    return strtotime($date) > time()+3600*$cutoff;
}
function safe_image_upload(array $file, ?string $old=null): ?string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return $old;
    if($file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024 || $file['size']<1) throw new DomainException('Image invalide ou supérieure à 5 Mo.');
    if(!is_uploaded_file($file['tmp_name'])) throw new DomainException('Envoi de fichier invalide.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime])) throw new DomainException('Format autorisé : JPEG, PNG ou WebP.');
    $filename=bin2hex(random_bytes(16)).'.'.$allowed[$mime];
    if(!move_uploaded_file($file['tmp_name'],__DIR__.'/../uploads/'.$filename)) throw new RuntimeException('Impossible de sauvegarder l’image.');
    return $filename;
}
function room_image(?string $image): string {
    if (!$image || basename($image)!==$image || !is_file(__DIR__.'/../uploads/'.$image)) return url('assets/placeholder.svg');
    return url('uploads/'.rawurlencode($image));
}
function textclip(string $s,int $max=70):string { return mb_strlen($s)>$max ? mb_substr($s,0,$max).'…' : $s; }
