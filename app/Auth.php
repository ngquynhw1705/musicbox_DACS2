<?php
declare(strict_types=1);

class Auth
{
    public static function start(): void
    {
        ini_set('session.use_strict_mode', '1');
        session_name('musicbox_session');
        session_set_cookie_params(['httponly'=>true, 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite'=>'Lax', 'path'=>'/']);
        session_start();
        if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 7200) $_SESSION = [];
        $_SESSION['last_seen'] = time();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) return null;
        return one('SELECT id,name,email,phone,role,points,created_at FROM users WHERE id=? AND active=1', [$_SESSION['user_id']]);
    }

    public static function requireUser(array $roles = []): array
    {
        $u = self::user();
        if (!$u) fail('Vui lòng đăng nhập để tiếp tục.',401);
        if ($roles && !in_array($u['role'],$roles,true)) fail('Bạn không có quyền thực hiện thao tác này.',403);
        return $u;
    }

    public static function csrf(): void
    {
        if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('Phiên làm việc không hợp lệ. Hãy tải lại trang.',419);
    }

    private static function password(array $d): string
    {
        $p = $d['password'] ?? '';
        if (!is_string($p) || strlen($p) < 10 || strlen($p) > 72) fail('Mật khẩu cần từ 10 đến 72 byte.');
        return $p;
    }

    public static function register(array $d): array
    {
        $name=text_field($d,'name',100); $email=strtolower(text_field($d,'email',190)); $phone=self::phone($d);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Email không hợp lệ.');
        $password=self::password($d);
        try {
            run('INSERT INTO users (name,email,phone,password_hash) VALUES (?,?,?,?)',[$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT)]);
        } catch (PDOException $e) {
            if ($e->getCode()==='23000') fail('Email đã được đăng ký.');
            throw $e;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']=(int)db()->lastInsertId();
        return self::user();
    }

    public static function login(array $d): array
    {
        $email=strtolower(text_field($d,'email',190)); $password=text_field($d,'password',72);
        $identity=hash('sha256',($_SERVER['REMOTE_ADDR'] ?? 'cli').'|'.$email);
        $result=transaction(function () use ($email,$password,$identity) {
            run('INSERT IGNORE INTO login_attempts (identity_hash,window_start) VALUES (?,NOW())',[$identity]);
            $attempt=one('SELECT * FROM login_attempts WHERE identity_hash=? FOR UPDATE',[$identity]);
            if (strtotime($attempt['window_start']) < time()-900) {
                run('UPDATE login_attempts SET failures=0,window_start=NOW() WHERE identity_hash=?',[$identity]);
                $attempt['failures']=0;
            }
            if ((int)$attempt['failures'] >= 10) return ['error'=>'Thử đăng nhập quá nhiều lần. Vui lòng đợi 15 phút.','code'=>429];
            $u=one('SELECT * FROM users WHERE email=?',[$email]);
            $valid=password_verify($password,$u['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            if (!$u || !$valid || !$u['active']) {
                run('UPDATE login_attempts SET failures=failures+1 WHERE identity_hash=?',[$identity]);
                return ['error'=>'Email hoặc mật khẩu không chính xác.','code'=>401];
            }
            run('DELETE FROM login_attempts WHERE identity_hash=?',[$identity]);
            return $u;
        });
        if (isset($result['error'])) fail($result['error'],$result['code']);
        session_regenerate_id(true);
        $_SESSION['user_id']=$result['id'];
        return self::user();
    }

    public static function phone(array $d): string
    {
        $phone=text_field($d,'phone',20);
        if (!preg_match('/^\+?[0-9 ()-]{8,20}$/',$phone)) fail('Số điện thoại không hợp lệ.');
        return $phone;
    }

    public static function profile(array $d, array $u): array
    {
        $name=text_field($d,'name',100); $phone=self::phone($d);
        if (!empty($d['password'])) {
            $hash=one('SELECT password_hash FROM users WHERE id=?',[$u['id']])['password_hash'];
            if (!password_verify($d['current_password'] ?? '',$hash)) fail('Mật khẩu hiện tại chưa đúng.');
            run('UPDATE users SET name=?,phone=?,password_hash=? WHERE id=?',[$name,$phone,password_hash(self::password($d),PASSWORD_DEFAULT),$u['id']]);
            session_regenerate_id(true);
        } else run('UPDATE users SET name=?,phone=? WHERE id=?',[$name,$phone,$u['id']]);
        return self::user();
    }
}
