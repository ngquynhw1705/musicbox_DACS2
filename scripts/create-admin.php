<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
try {
    $d=['name'=>getenv('ADMIN_NAME') ?: 'Quản trị viên','email'=>getenv('ADMIN_EMAIL') ?: '', 'phone'=>getenv('ADMIN_PHONE') ?: '', 'password'=>getenv('ADMIN_PASSWORD') ?: ''];
    $name=text_field($d,'name',100);$email=strtolower(text_field($d,'email',190));$phone=Auth::phone($d);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($d['password'])<10 || strlen($d['password'])>72) fail('Email hoặc mật khẩu không hợp lệ. Mật khẩu cần 10–72 byte.');
    run("INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,'admin')",[$name,$email,$phone,password_hash($d['password'],PASSWORD_DEFAULT)]);
    echo "Administrator created.\n";
} catch (AppError $e) { fwrite(STDERR,$e->getMessage()."\n");exit(1); }
catch (PDOException $e) { fwrite(STDERR,"Cannot create administrator. Check database and whether the email already exists.\n");exit(1); }
