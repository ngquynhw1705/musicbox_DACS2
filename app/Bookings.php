<?php
declare(strict_types=1);

class Bookings
{
    private const ACTIVE = "'pending','confirmed','checked_in'";

    private static function window(array $room, string $start, string $end, bool $future = true): array
    {
        $s=local_time($start); $e=local_time($end);
        $minutes=(int)(($e->getTimestamp()-$s->getTimestamp())/60);
        if ($future && $s->getTimestamp() < time()) fail('Vui lòng chọn thời gian trong tương lai.');
        if ($s->getTimestamp() > time()+90*86400) fail('Chỉ nhận đặt trước tối đa 90 ngày.');
        if ($minutes < 30 || $minutes > 720 || $s->format('s')!=='00' || $e->format('s')!=='00') fail('Thời lượng từ 30 phút đến 12 giờ, theo phút tròn.');
        if ($s->format('Y-m-d')!==$e->format('Y-m-d') || $s->format('H:i:s')<$room['opens_at'] || $e->format('H:i:s')>$room['closes_at']) fail('Khung giờ nằm ngoài giờ hoạt động của phòng.');
        if ($room['status']!=='available') fail('Phòng hiện không nhận đặt.');
        return [$s->format('Y-m-d H:i:s'),$e->format('Y-m-d H:i:s'),$minutes];
    }

    private static function assertFree(int $room, string $start, string $end, int $except=0): void
    {
        // The caller holds the room row lock. Locking reads also avoid stale
        // repeatable-read snapshots after waiting for another reservation.
        if (one('SELECT id FROM bookings WHERE room_id=? AND id<>? AND status IN ('.self::ACTIVE.') AND starts_at<? AND ends_at>? LIMIT 1 FOR UPDATE',[$room,$except,$end,$start])) fail('Khung giờ vừa được đặt. Vui lòng chọn khung giờ khác.',409);
        if (one('SELECT id FROM maintenance WHERE room_id=? AND starts_at<? AND ends_at>? LIMIT 1 FOR UPDATE',[$room,$end,$start])) fail('Phòng đang có lịch bảo trì trong khung giờ này.',409);
    }

    public static function create(array $d, array $u): array
    {
        $roomId=number_field($d,'room_id',1,PHP_INT_MAX);
        $points=number_field($d+['points'=>0],'points',0,100000000);
        $note=text_field($d,'note',500,false);
        $id=transaction(function () use ($d,$u,$roomId,$points,$note) {
            $room=one('SELECT * FROM rooms WHERE id=? FOR UPDATE',[$roomId]);
            if (!$room) fail('Không tìm thấy phòng.',404);
            [$start,$end,$minutes]=self::window($room,text_field($d,'starts_at',19),text_field($d,'ends_at',19));
            self::assertFree($roomId,$start,$end);
            $customer=one('SELECT * FROM users WHERE id=? FOR UPDATE',[$u['id']]);
            $settings=one('SELECT * FROM settings WHERE id=1');
            $amount=(int)ceil((int)$room['hourly_rate']*$minutes/60);
            $discount=$points*(int)$settings['point_value'];
            if ($points > (int)$customer['points']) fail('Số dư điểm không đủ.');
            if ($discount > intdiv($amount,2)) fail('Điểm chỉ được giảm tối đa 50% tiền thuê phòng.');
            run('INSERT INTO bookings (user_id,room_id,starts_at,ends_at,hourly_rate,room_amount,redeemed_points,discount_amount,earn_per,note) VALUES (?,?,?,?,?,?,?,?,?,?)',[$u['id'],$roomId,$start,$end,$room['hourly_rate'],$amount,$points,$discount,$settings['earn_per'],$note]);
            $id=(int)db()->lastInsertId();
            if ($points) self::points((int)$u['id'],$id,-$points,'redeem');
            return $id;
        });
        return self::detail($id,$u);
    }

    private static function points(int $user, int $booking, int $delta, string $kind): void
    {
        run('UPDATE users SET points=points+? WHERE id=?',[$delta,$user]);
        $balance=one('SELECT points FROM users WHERE id=?',[$user])['points'];
        run('INSERT INTO point_ledger (user_id,booking_id,kind,delta,balance_after) VALUES (?,?,?,?,?)',[$user,$booking,$kind,$delta,$balance]);
    }

    public static function paid(int $id): int
    {
        return (int)one("SELECT COALESCE(SUM(CASE WHEN type='refund' THEN -amount ELSE amount END),0) n FROM payments WHERE booking_id=?",[$id])['n'];
    }

    private static function decorate(array $b): array
    {
        $b['total']=(int)$b['room_amount']+(int)$b['service_amount']-(int)$b['discount_amount'];
        $b['paid']=self::paid((int)$b['id']);
        $b['due']=max(0,$b['total']-$b['paid']);
        $b['deposit_due']=max(0,(int)ceil($b['total']*0.3)-$b['paid']);
        $b['estimated_room_amount']=(int)$b['room_amount'];
        $b['overtime_minutes']=0;
        if ($b['status']==='checked_in') {
            $b['overtime_minutes']=(int)ceil(max(0,time()-strtotime($b['ends_at']))/60);
            $minutes=(int)ceil((max(time(),strtotime($b['ends_at']))-strtotime($b['starts_at']))/60);
            $b['estimated_room_amount']=(int)ceil($minutes*(int)$b['hourly_rate']/60);
        }
        $b['estimated_total']=$b['estimated_room_amount']+(int)$b['service_amount']-(int)$b['discount_amount'];
        $b['estimated_due']=max(0,$b['estimated_total']-$b['paid']);
        return $b;
    }

    public static function listing(array $u, array $filter): array
    {
        $where=[]; $params=[];
        if ($u['role']==='customer') { $where[]='b.user_id=?'; $params[]=$u['id']; }
        if (!empty($filter['status'])) {
            if (!in_array($filter['status'],['pending','confirmed','checked_in','completed','cancelled'],true)) fail('Trạng thái không hợp lệ.');
            $where[]='b.status=?'; $params[]=$filter['status'];
        }
        if (!empty($filter['date'])) { local_time($filter['date'].' 00:00:00'); $where[]='DATE(b.starts_at)=?'; $params[]=$filter['date']; }
        if (!empty($filter['q'])) { $q='%'.substr($filter['q'],0,100).'%'; $where[]='(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; array_push($params,$q,$q,$q); }
        $sql='SELECT b.*,r.name room_name,u.name customer_name,u.email customer_email,u.phone customer_phone FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN users u ON u.id=b.user_id';
        if ($where) $sql.=' WHERE '.implode(' AND ',$where);
        $sql.=' ORDER BY b.created_at DESC,b.id DESC LIMIT 300';
        return array_map([self::class,'decorate'],rows($sql,$params));
    }

    public static function detail(int $id, array $u): array
    {
        $b=one('SELECT b.*,r.name room_name,u.name customer_name,u.email customer_email,u.phone customer_phone FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN users u ON u.id=b.user_id WHERE b.id=?',[$id]);
        if (!$b) fail('Không tìm thấy lịch đặt.',404);
        if ($u['role']==='customer' && (int)$b['user_id']!==(int)$u['id']) fail('Bạn không có quyền xem lịch đặt này.',403);
        $b=self::decorate($b);
        $b['services']=rows('SELECT * FROM booking_services WHERE booking_id=? ORDER BY id',[$id]);
        $b['payments']=rows('SELECT id,type,method,amount,created_at FROM payments WHERE booking_id=? ORDER BY id',[$id]);
        return $b;
    }

    public static function action(int $id, string $action, array $d, array $u): array
    {
        $original=self::detail($id,$u);
        transaction(function () use ($id,$action,$d,$u,$original) {
            $room=one('SELECT * FROM rooms WHERE id=? FOR UPDATE',[$original['room_id']]);
            $b=one('SELECT * FROM bookings WHERE id=? FOR UPDATE',[$id]);
            one('SELECT id FROM users WHERE id=? FOR UPDATE',[$b['user_id']]);
            $b=self::decorate($b);
            $staff=in_array($u['role'],['admin','staff'],true);
            if (!$staff && !in_array($action,['cancel','deposit'],true)) fail('Thao tác chỉ dành cho nhân viên.',403);
            if (in_array($b['status'],['completed','cancelled'],true)) fail('Lịch đặt đã kết thúc, không thể thay đổi.',409);
            switch ($action) {
                case 'confirm':
                    if ($b['status']!=='pending') fail('Chỉ xác nhận lịch đang chờ.',409);
                    run("UPDATE bookings SET status='confirmed' WHERE id=?",[$id]);
                    break;
                case 'deposit':
                    if (!in_array($b['status'],['pending','confirmed'],true) || !$b['deposit_due']) fail('Không có khoản đặt cọc cần thanh toán.',409);
                    self::payment($id,(int)$u['id'],'deposit',$staff ? self::method($d) : 'demo',$b['deposit_due']);
                    run("UPDATE bookings SET status='confirmed' WHERE id=?",[$id]);
                    break;
                case 'cancel':
                    if (!in_array($b['status'],['pending','confirmed'],true)) fail('Không thể hủy phòng đang sử dụng.',409);
                    if (!$staff && strtotime($b['starts_at']) <= time()) fail('Lịch đã đến giờ nhận phòng. Vui lòng liên hệ nhân viên.');
                    if ($b['paid']>0) self::payment($id,(int)$u['id'],'refund','demo',$b['paid']);
                    if ($b['redeemed_points']) self::points((int)$b['user_id'],$id,(int)$b['redeemed_points'],'restore');
                    run("UPDATE bookings SET status='cancelled' WHERE id=?",[$id]);
                    break;
                case 'checkin':
                    if ($b['status']!=='confirmed') fail('Cần xác nhận lịch đặt trước khi nhận phòng.',409);
                    if (time()<strtotime($b['starts_at'])-1800 || time()>=strtotime($b['ends_at'])) fail('Chỉ nhận phòng từ 30 phút trước giờ đặt đến trước giờ kết thúc.');
                    if ($room['status']!=='available') fail('Phòng đang ngừng hoạt động.');
                    if (one('SELECT id FROM maintenance WHERE room_id=? AND starts_at<? AND ends_at>NOW() LIMIT 1 FOR UPDATE',[$b['room_id'],$b['ends_at']])) fail('Phòng chưa kết thúc lịch bảo trì.',409);
                    if (one("SELECT id FROM bookings WHERE room_id=? AND status='checked_in' AND id<>? FOR UPDATE",[$b['room_id'],$id])) fail('Phòng vẫn đang có khách sử dụng.',409);
                    run("UPDATE bookings SET status='checked_in',checked_in_at=NOW() WHERE id=?",[$id]);
                    break;
                case 'extend':
                    if (!in_array($b['status'],['confirmed','checked_in'],true)) fail('Chỉ gia hạn lịch đã xác nhận hoặc đang sử dụng.');
                    [$start,$end,$minutes]=self::window($room,$b['starts_at'],text_field($d,'ends_at',19),false);
                    if ($end <= $b['ends_at']) fail('Giờ gia hạn phải sau giờ kết thúc hiện tại.');
                    if (strtotime($end)<=time()) fail('Giờ gia hạn phải ở tương lai.');
                    self::assertFree((int)$b['room_id'],$start,$end,$id);
                    run('UPDATE bookings SET ends_at=?,room_amount=? WHERE id=?',[$end,(int)ceil($minutes*(int)$b['hourly_rate']/60),$id]);
                    break;
                case 'service':
                    if ($b['status']!=='checked_in') fail('Chỉ thêm dịch vụ khi đã nhận phòng.');
                    $service=one('SELECT * FROM services WHERE id=? AND active=1',[number_field($d,'service_id',1,PHP_INT_MAX)]);
                    if (!$service) fail('Dịch vụ không còn khả dụng.');
                    $quantity=number_field($d,'quantity',1,100);
                    run('INSERT INTO booking_services (booking_id,service_id,name,unit_price,quantity) VALUES (?,?,?,?,?)',[$id,$service['id'],$service['name'],$service['price'],$quantity]);
                    self::serviceTotal($id);
                    break;
                case 'service_status':
                    if ($b['status']!=='checked_in') fail('Chỉ cập nhật dịch vụ khi khách đang sử dụng phòng.');
                    $line=one('SELECT * FROM booking_services WHERE id=? AND booking_id=?',[number_field($d,'line_id',1,PHP_INT_MAX),$id]);
                    $status=$d['status'] ?? '';
                    if (!$line || $line['status']!=='ordered' || !in_array($status,['served','cancelled'],true)) fail('Dịch vụ không thể cập nhật trạng thái.');
                    run('UPDATE booking_services SET status=? WHERE id=?',[$status,$line['id']]);
                    self::serviceTotal($id);
                    break;
                case 'checkout':
                    if ($b['status']!=='checked_in') fail('Chỉ trả phòng khi đã nhận phòng.',409);
                    if (one("SELECT id FROM booking_services WHERE booking_id=? AND status='ordered'",[$id])) fail('Vui lòng xác nhận đã phục vụ hoặc hủy các dịch vụ đang chờ.');
                    $billUntil=max(strtotime($b['ends_at']),time());
                    // Early arrival is complimentary; early departure still pays the reserved duration.
                    $minutes=(int)ceil(($billUntil-strtotime($b['starts_at']))/60);
                    $roomAmount=(int)ceil($minutes*(int)$b['hourly_rate']/60);
                    $total=$roomAmount+(int)$b['service_amount']-(int)$b['discount_amount'];
                    $due=max(0,$total-$b['paid']);
                    if ($due) self::payment($id,(int)$u['id'],'settlement',self::method($d),$due);
                    $earned=intdiv($total,(int)$b['earn_per']);
                    if ($earned) self::points((int)$b['user_id'],$id,$earned,'earn');
                    run("UPDATE bookings SET status='completed',checked_out_at=NOW(),room_amount=?,earned_points=? WHERE id=?",[$roomAmount,$earned,$id]);
                    break;
                default: fail('Thao tác không hợp lệ.',404);
            }
        });
        return self::detail($id,$u);
    }

    private static function serviceTotal(int $id): void
    {
        $amount=one("SELECT COALESCE(SUM(unit_price*quantity),0) n FROM booking_services WHERE booking_id=? AND status<>'cancelled'",[$id])['n'];
        run('UPDATE bookings SET service_amount=? WHERE id=?',[$amount,$id]);
    }

    private static function payment(int $id, int $actor, string $type, string $method, int $amount): void
    {
        run('INSERT INTO payments (booking_id,recorded_by,type,method,amount) VALUES (?,?,?,?,?)',[$id,$actor,$type,$method,$amount]);
    }

    private static function method(array $d): string
    {
        $method=$d['method'] ?? 'cash';
        if (!in_array($method,['cash','transfer','demo'],true)) fail('Phương thức thanh toán không hợp lệ.');
        return $method;
    }

    public static function availability(int $room, string $date): array
    {
        local_time($date.' 00:00:00');
        if (!one('SELECT id FROM rooms WHERE id=?',[$room])) fail('Không tìm thấy phòng.',404);
        $booked=rows('SELECT starts_at,ends_at,status FROM bookings WHERE room_id=? AND status IN ('.self::ACTIVE.') AND starts_at>=? AND starts_at<? ORDER BY starts_at',[$room,$date.' 00:00:00',(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d').' 00:00:00']);
        $maintenance=rows("SELECT starts_at,ends_at,'maintenance' status FROM maintenance WHERE room_id=? AND starts_at<? AND ends_at>? ORDER BY starts_at",[$room,$date.' 23:59:59',$date.' 00:00:00']);
        return array_merge($booked,$maintenance);
    }
}
