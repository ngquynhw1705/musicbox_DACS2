<?php
declare(strict_types=1);

class Management
{
    public static function rooms(): array
    {
        return rows("SELECT r.*,EXISTS(SELECT 1 FROM bookings b WHERE b.room_id=r.id AND b.status='checked_in') occupied FROM rooms r ORDER BY r.id");
    }

    public static function room(array $d): array
    {
        $id=number_field($d+['id'=>0],'id',0,PHP_INT_MAX);
        $name=text_field($d,'name',100); $description=text_field($d,'description',4000);
        $capacity=number_field($d,'capacity',1,50); $rate=number_field($d,'hourly_rate',1000,10000000);
        $open=text_field($d,'opens_at',8); $close=text_field($d,'closes_at',8);
        foreach ([$open,$close] as $t) if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:00)?$/',$t)) fail('Giờ hoạt động không hợp lệ.');
        if (strlen($open)===5) $open.=':00'; if (strlen($close)===5) $close.=':00';
        if ($close<=$open) fail('Giờ đóng cửa phải sau giờ mở cửa, trong cùng ngày.');
        $status=$d['status'] ?? 'available'; $theme=$d['theme'] ?? 'violet';
        if (!in_array($status,['available','maintenance','inactive'],true) || !in_array($theme,['violet','blue','orange','pink'],true)) fail('Trạng thái hoặc màu phòng không hợp lệ.');
        $image=text_field($d,'image_url',500,false);
        if ($image!=='' && !(filter_var($image,FILTER_VALIDATE_URL) && str_starts_with($image,'https://')) && !preg_match('#^/assets/[A-Za-z0-9_.-]+\.(svg|png|jpg|webp)$#',$image)) fail('Hình ảnh cần là URL HTTPS hoặc ảnh trong /assets.');
        return transaction(function () use ($id,$name,$description,$capacity,$rate,$image,$open,$close,$status,$theme) {
            if ($id) {
                if (!one('SELECT id FROM rooms WHERE id=? FOR UPDATE',[$id])) fail('Không tìm thấy phòng.',404);
                if (one("SELECT id FROM bookings WHERE room_id=? AND status IN ('pending','confirmed','checked_in') AND (ends_at>NOW() OR status='checked_in') AND (?<>'available' OR TIME(starts_at)<? OR TIME(ends_at)>?) LIMIT 1 FOR UPDATE",[$id,$status,$open,$close])) fail('Cần xử lý các lịch đặt đang hoạt động trước khi đổi trạng thái hoặc thu hẹp giờ mở cửa.',409);
                run('UPDATE rooms SET name=?,description=?,capacity=?,hourly_rate=?,image_url=?,opens_at=?,closes_at=?,status=?,theme=? WHERE id=?',[$name,$description,$capacity,$rate,$image,$open,$close,$status,$theme,$id]);
            } else {
                run('INSERT INTO rooms (name,description,capacity,hourly_rate,image_url,opens_at,closes_at,status,theme) VALUES (?,?,?,?,?,?,?,?,?)',[$name,$description,$capacity,$rate,$image,$open,$close,$status,$theme]);
                $id=(int)db()->lastInsertId();
            }
            return one('SELECT * FROM rooms WHERE id=?',[$id]);
        });
    }

    public static function maintenance(array $d): array
    {
        $room=number_field($d,'room_id',1,PHP_INT_MAX);
        $start=local_time(text_field($d,'starts_at',19)); $end=local_time(text_field($d,'ends_at',19));
        if ($end<=$start || $end->getTimestamp()<=time()) fail('Thời gian bảo trì không hợp lệ.');
        $reason=text_field($d,'reason',255);
        return transaction(function () use ($room,$start,$end,$reason) {
            if (!one('SELECT id FROM rooms WHERE id=? FOR UPDATE',[$room])) fail('Không tìm thấy phòng.',404);
            if (one("SELECT id FROM bookings WHERE room_id=? AND status IN ('pending','confirmed','checked_in') AND starts_at<? AND ends_at>? LIMIT 1 FOR UPDATE",[$room,$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')])) fail('Lịch bảo trì trùng lịch đặt phòng.',409);
            run('INSERT INTO maintenance (room_id,starts_at,ends_at,reason) VALUES (?,?,?,?)',[$room,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$reason]);
            return ['id'=>(int)db()->lastInsertId()];
        });
    }

    public static function service(array $d): array
    {
        $id=number_field($d+['id'=>0],'id',0,PHP_INT_MAX); $name=text_field($d,'name',100);
        $price=number_field($d,'price',0,10000000); $active=number_field($d,'active',0,1);
        if ($id) {
            if (!one('SELECT id FROM services WHERE id=?',[$id])) fail('Không tìm thấy dịch vụ.',404);
            run('UPDATE services SET name=?,price=?,active=? WHERE id=?',[$name,$price,$active,$id]);
        } else {
            run('INSERT INTO services (name,price,active) VALUES (?,?,?)',[$name,$price,$active]); $id=(int)db()->lastInsertId();
        }
        return one('SELECT * FROM services WHERE id=?',[$id]);
    }

    public static function customers(string $q): array
    {
        $term='%'.substr($q,0,100).'%';
        return rows("SELECT u.id,u.name,u.email,u.phone,u.role,u.active,u.points,u.created_at,(SELECT COUNT(*) FROM bookings b WHERE b.user_id=u.id AND b.status='completed') visits FROM users u WHERE u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? ORDER BY u.id DESC LIMIT 300",[$term,$term,$term]);
    }

    public static function account(array $d, array $actor): array
    {
        $id=number_field($d,'id',1,PHP_INT_MAX); $active=number_field($d,'active',0,1); $role=$d['role'] ?? '';
        if (!in_array($role,['admin','staff','customer'],true)) fail('Vai trò không hợp lệ.');
        if ($id===(int)$actor['id']) fail('Không thể thay đổi quyền hoặc khóa tài khoản của chính bạn.');
        return transaction(function () use ($id,$role,$active) {
            // Serialize account administration and protect the final administrator.
            one('SELECT id FROM settings WHERE id=1 FOR UPDATE');
            $target=one('SELECT * FROM users WHERE id=? FOR UPDATE',[$id]);
            if (!$target) fail('Không tìm thấy tài khoản.',404);
            if ($target['role']==='admin' && (!$active || $role!=='admin') && (int)one("SELECT COUNT(*) n FROM users WHERE role='admin' AND active=1")['n']<=1) fail('Phải giữ lại ít nhất một quản trị viên.');
            run('UPDATE users SET role=?,active=? WHERE id=?',[$role,$active,$id]);
            return ['id'=>$id];
        });
    }

    public static function settings(array $d): array
    {
        $earn=number_field($d,'earn_per',1000,10000000); $value=number_field($d,'point_value',1,100000);
        run('UPDATE settings SET earn_per=?,point_value=? WHERE id=1',[$earn,$value]);
        return one('SELECT * FROM settings WHERE id=1');
    }

    public static function reports(array $d): array
    {
        $from=$d['from'] ?? date('Y-m-01'); $to=$d['to'] ?? date('Y-m-d');
        $s=local_time($from.' 00:00:00'); $e=local_time($to.' 00:00:00')->modify('+1 day');
        if ($e<=$s || $e->getTimestamp()-$s->getTimestamp()>366*86400) fail('Khoảng báo cáo phải từ 1 đến 366 ngày.');
        $range=[$s->format('Y-m-d H:i:s'),$e->format('Y-m-d H:i:s')];
        $money=one("SELECT COALESCE(SUM(CASE WHEN type='refund' THEN -amount ELSE amount END),0) revenue,COALESCE(SUM(CASE WHEN type='refund' THEN amount ELSE 0 END),0) refunds FROM payments WHERE created_at>=? AND created_at<?",$range);
        $counts=one("SELECT COUNT(*) bookings,COALESCE(SUM(status='cancelled'),0) cancelled FROM bookings WHERE starts_at>=? AND starts_at<?",$range);
        $visits=one('SELECT COUNT(*) visits,COUNT(DISTINCT user_id) customers FROM bookings WHERE checked_in_at>=? AND checked_in_at<?',$range);
        $daily=rows("SELECT DATE(created_at) day,SUM(CASE WHEN type='refund' THEN -amount ELSE amount END) revenue FROM payments WHERE created_at>=? AND created_at<? GROUP BY DATE(created_at) ORDER BY day",$range);
        $roomStats=rows("SELECT r.name,COUNT(b.id) bookings,COALESCE(SUM(b.status='completed'),0) completed FROM rooms r LEFT JOIN bookings b ON b.room_id=r.id AND b.starts_at>=? AND b.starts_at<? GROUP BY r.id,r.name ORDER BY bookings DESC",$range);
        return $money+$counts+$visits+['daily'=>$daily,'rooms'=>$roomStats,'from'=>$from,'to'=>$to,'cancellation_rate'=>(int)$counts['bookings'] ? round(100*(int)$counts['cancelled']/(int)$counts['bookings'],1) : 0];
    }
}
