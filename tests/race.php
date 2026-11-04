<?php
require __DIR__.'/../app/bootstrap.php';
if (getenv('APP_ENV')!=='test' || !str_ends_with(getenv('DB_DATABASE') ?: '', '_test')) exit(2);
[$script,$room,$user,$start,$end,$gate]=$argv;
while (microtime(true)<(float)$gate) usleep(1000);
try {
    $b=Bookings::create(['room_id'=>$room,'starts_at'=>$start,'ends_at'=>$end],['id'=>(int)$user,'role'=>'customer']);
    echo json_encode(['created'=>$b['id']]);
} catch (AppError $e) {
    echo json_encode(['error'=>$e->getCode()]);
}
