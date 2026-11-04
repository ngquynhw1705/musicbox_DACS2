<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
Auth::start();
try {
    $action=$_GET['action'] ?? 'bootstrap';
    $method=$_SERVER['REQUEST_METHOD'];
    if (!in_array($method,['GET','POST'],true)) fail('Phương thức không được hỗ trợ.',405);
    $writes=['register','login','logout','profile','book','booking_action','room_save','maintenance_save','maintenance_delete','service_save','account_save','settings_save'];
    if (in_array($action,$writes,true)) {
        if ($method!=='POST') fail('Yêu cầu POST.',405);
        Auth::csrf();
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>16384) fail('Yêu cầu quá lớn.',413);
        try { $d=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR); }
        catch (JsonException) { fail('JSON không hợp lệ.',400); }
        if (!is_array($d) || array_is_list($d) && $d!==[]) fail('Nội dung yêu cầu không hợp lệ.',400);
    } else {
        if ($method!=='GET') fail('Yêu cầu GET.',405);
        $d=$_GET;
    }
    foreach ($d as $value) if (!is_scalar($value) && $value!==null) fail('Các trường dữ liệu phải là giá trị đơn.',400);
    $result=match ($action) {
        'bootstrap'=>['user'=>Auth::user(),'csrf'=>$_SESSION['csrf'],'rooms'=>Management::rooms(),'services'=>rows('SELECT * FROM services WHERE active=1 ORDER BY id'),'settings'=>one('SELECT * FROM settings WHERE id=1'),'today'=>date('Y-m-d'),'demo'=>getenv('APP_ENV')!=='production'],
        'register'=>Auth::register($d),
        'login'=>Auth::login($d),
        'logout'=>(function () { $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32)); return ['csrf'=>$_SESSION['csrf']]; })(),
        'profile'=>Auth::profile($d,Auth::requireUser()),
        'availability'=>Bookings::availability(number_field($d,'room_id',1,PHP_INT_MAX),text_field($d,'date',10)),
        'book'=>Bookings::create($d,Auth::requireUser()),
        'bookings'=>Bookings::listing(Auth::requireUser(),$d),
        'booking'=>Bookings::detail(number_field($d,'id',1,PHP_INT_MAX),Auth::requireUser()),
        'booking_action'=>Bookings::action(number_field($d,'id',1,PHP_INT_MAX),text_field($d,'operation',30),$d,Auth::requireUser()),
        'points'=>rows('SELECT p.*,r.name room_name FROM point_ledger p JOIN bookings b ON b.id=p.booking_id JOIN rooms r ON r.id=b.room_id WHERE p.user_id=? ORDER BY p.id DESC LIMIT 300',[Auth::requireUser()['id']]),
        'customers'=>(function () use ($d) { Auth::requireUser(['admin','staff']); return Management::customers($d['q'] ?? ''); })(),
        'customer_history'=>(function () use ($d) { Auth::requireUser(['admin','staff']); $id=number_field($d,'id',1,PHP_INT_MAX); return Bookings::listing(['id'=>$id,'role'=>'customer'],$d); })(),
        'reports'=>(function () use ($d) { Auth::requireUser(['admin','staff']); return Management::reports($d); })(),
        'admin_catalog'=>(function () { Auth::requireUser(['admin']); return ['services'=>rows('SELECT * FROM services ORDER BY id'),'maintenance'=>rows('SELECT m.*,r.name room_name FROM maintenance m JOIN rooms r ON r.id=m.room_id ORDER BY m.starts_at DESC LIMIT 300')]; })(),
        'room_save'=>(function () use ($d) { Auth::requireUser(['admin']); return Management::room($d); })(),
        'maintenance_save'=>(function () use ($d) { Auth::requireUser(['admin']); return Management::maintenance($d); })(),
        'maintenance_delete'=>(function () use ($d) { Auth::requireUser(['admin']); $id=number_field($d,'id',1,PHP_INT_MAX); transaction(function () use ($id) { $m=one('SELECT room_id FROM maintenance WHERE id=?',[$id]); if (!$m) fail('Không tìm thấy lịch bảo trì.',404); one('SELECT id FROM rooms WHERE id=? FOR UPDATE',[$m['room_id']]); run('DELETE FROM maintenance WHERE id=?',[$id]); }); return ['ok'=>true]; })(),
        'service_save'=>(function () use ($d) { Auth::requireUser(['admin']); return Management::service($d); })(),
        'account_save'=>Management::account($d,Auth::requireUser(['admin'])),
        'settings_save'=>(function () use ($d) { Auth::requireUser(['admin']); return Management::settings($d); })(),
        default=>throw new AppError('Không tìm thấy chức năng.',404)
    };
    echo json_encode(['data'=>$result],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (AppError $e) {
    http_response_code($e->getCode());
    echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    echo json_encode(['error'=>'Không thể xử lý yêu cầu. Vui lòng thử lại hoặc liên hệ nhân viên.'],JSON_UNESCAPED_UNICODE);
}
