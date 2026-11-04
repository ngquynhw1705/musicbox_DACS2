<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if (getenv('APP_ENV')!=='test' || !str_ends_with(getenv('DB_DATABASE') ?: '', '_test')) {
    fwrite(STDERR,"Tests require APP_ENV=test and a dedicated database ending in _test.\n"); exit(2);
}
$count=0;
function check(bool $ok, string $message): void {
    global $count;
    if (!$ok) throw new RuntimeException('FAIL: '.$message);
    $count++; echo "PASS $message\n";
}
function rejects(callable $fn, int $code, string $message): void {
    try { $fn(); } catch (AppError $e) { check($e->getCode()===$code,$message); return; }
    throw new RuntimeException('FAIL (did not reject): '.$message);
}

// Explicit disposable test database only. No application or demo data is touched.
db()->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['point_ledger','payments','booking_services','bookings','maintenance','rooms','services','settings','users','login_attempts'] as $table) db()->exec("DROP TABLE IF EXISTS $table");
db()->exec('SET FOREIGN_KEY_CHECKS=1');
db()->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
run('INSERT INTO settings (id) VALUES (1)');
foreach ([['Admin','admin','admin'],['Staff','staff','staff'],['Alice','alice','customer'],['Bob','bob','customer']] as [$name,$email,$role]) run('INSERT INTO users (name,email,phone,password_hash,role,points) VALUES (?,?,?,?,?,100)',[$name,$email.'@test.local','0901234567',password_hash('TestMusicBox!123',PASSWORD_DEFAULT),$role]);
$admin=one("SELECT * FROM users WHERE role='admin'"); $staff=one("SELECT * FROM users WHERE role='staff'");
$alice=one("SELECT * FROM users WHERE email='alice@test.local'"); $bob=one("SELECT * FROM users WHERE email='bob@test.local'");
$room=Management::room(['name'=>'Test room','description'=>'Integration suite','capacity'=>6,'hourly_rate'=>120000,'opens_at'=>'00:00','closes_at'=>'23:59']);
$tomorrow=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$input=['room_id'=>$room['id'],'starts_at'=>"$tomorrow 10:00:00",'ends_at'=>"$tomorrow 11:00:00",'points'=>20];
$b=Bookings::create($input,$alice);
check($b['total']===100000 && $b['due']===100000,'money calculated on server, points discount applied');
check((int)one('SELECT points FROM users WHERE id=?',[$alice['id']])['points']===80,'points reserved atomically');
rejects(fn()=>Bookings::create($input,$bob),409,'overlapping reservation rejected');
$adjacent=Bookings::create(array_replace($input,['starts_at'=>"$tomorrow 11:00:00",'ends_at'=>"$tomorrow 12:00:00",'points'=>0]),$bob);
check((int)$adjacent['id']>0,'adjacent reservations allowed');
rejects(fn()=>Bookings::detail((int)$b['id'],$bob),403,'booking ownership enforced');
rejects(fn()=>Bookings::action((int)$b['id'],'confirm',[],$alice),403,'customer cannot confirm booking');
rejects(fn()=>Bookings::create(array_replace($input,['starts_at'=>"$tomorrow 14:00:00",'ends_at'=>"$tomorrow 15:00:00",'points'=>61]),$alice),422,'discount capped at 50 percent');
rejects(fn()=>Bookings::create(array_replace($input,['points'=>101]),$alice),409,'room collision detected before financial mutation');
$b=Bookings::action((int)$b['id'],'deposit',['method'=>'cash'],$alice);
check($b['paid']===30000 && $b['status']==='confirmed','30 percent deposit confirms booking');
check($b['payments'][0]['method']==='demo','customer cannot forge a manual cash receipt');
rejects(fn()=>Bookings::action((int)$b['id'],'deposit',[],$alice),409,'repeated deposit does not double charge');
rejects(fn()=>Bookings::action((int)$b['id'],'checkin',[],$staff),422,'check-in too early rejected');
rejects(fn()=>Bookings::action((int)$b['id'],'extend',['ends_at'=>"$tomorrow 11:30:00"],$staff),409,'extension into next reservation rejected');
rejects(fn()=>Management::maintenance(['room_id'=>$room['id'],'starts_at'=>"$tomorrow 10:30:00",'ends_at'=>"$tomorrow 12:30:00",'reason'=>'Conflict']),409,'maintenance cannot overlap bookings');
$b=Bookings::action((int)$b['id'],'cancel',[],$alice);
check($b['paid']===0 && (int)one('SELECT points FROM users WHERE id=?',[$alice['id']])['points']===100,'cancellation refunds deposit and restores points');
rejects(fn()=>Bookings::action((int)$b['id'],'cancel',[],$alice),409,'repeated cancellation cannot restore points twice');
Management::maintenance(['room_id'=>$room['id'],'starts_at'=>"$tomorrow 14:00:00",'ends_at'=>"$tomorrow 16:00:00",'reason'=>'Service']);
rejects(fn()=>Bookings::create(array_replace($input,['starts_at'=>"$tomorrow 14:00:00",'ends_at'=>"$tomorrow 15:00:00",'points'=>0]),$alice),409,'reservation during maintenance rejected');
rejects(fn()=>Bookings::create(array_replace($input,['starts_at'=>"$tomorrow 22:00:00",'ends_at'=>dayPlusTest($tomorrow).' 01:00:00','points'=>0]),$alice),422,'cross-day booking outside opening hours rejected');
rejects(fn()=>Bookings::create(array_replace($input,['starts_at'=>'2020-01-01 10:00:00','ends_at'=>'2020-01-01 11:00:00']),$alice),422,'past booking rejected');

// Place a live reservation using the trusted test fixture clock.
$live=Bookings::create(array_replace($input,['starts_at'=>"$tomorrow 18:00:00",'ends_at'=>"$tomorrow 19:00:00"]),$alice);
$now=new DateTimeImmutable();
run("UPDATE bookings SET starts_at=?,ends_at=? WHERE id=?",[$now->modify('-5 minutes')->format('Y-m-d H:i:00'),$now->modify('+55 minutes')->format('Y-m-d H:i:00'),$live['id']]);
Bookings::action((int)$live['id'],'confirm',[],$staff);
$live=Bookings::action((int)$live['id'],'checkin',[],$staff);
check($live['status']==='checked_in' && $live['checked_in_at']!==null,'staff check-in records visit time');
rejects(fn()=>Bookings::action((int)$live['id'],'cancel',[],$staff),409,'cannot cancel occupied room');
$s=Management::service(['name'=>'Tea','price'=>35000,'active'=>1]);
$live=Bookings::action((int)$live['id'],'service',['service_id'=>$s['id'],'quantity'=>2],$staff);
Management::service(['id'=>$s['id'],'name'=>'Tea new','price'=>50000,'active'=>1]);
check($live['service_amount']==70000,'service price snapshotted before catalogue changes');
rejects(fn()=>Bookings::action((int)$live['id'],'checkout',['method'=>'cash'],$staff),422,'unresolved service prevents checkout');
Bookings::action((int)$live['id'],'service_status',['line_id'=>$live['services'][0]['id'],'status'=>'served'],$staff);
$live=Bookings::action((int)$live['id'],'checkout',['method'=>'cash'],$staff);
check($live['status']==='completed' && $live['due']===0 && $live['paid']===170000,'checkout settles exact room and service total');
check((int)$live['earned_points']===17,'points awarded on net eligible payment');
$balance=(int)one('SELECT points FROM users WHERE id=?',[$alice['id']])['points'];
check($balance===97,'loyalty balance matches debit and earned points');
rejects(fn()=>Bookings::action((int)$live['id'],'checkout',['method'=>'cash'],$staff),409,'checkout replay rejected');
check((int)one("SELECT COUNT(*) n FROM point_ledger WHERE booking_id=? AND kind='earn'",[$live['id']])['n']===1,'exactly one earning entry persisted');
$reports=Management::reports(['from'=>date('Y-m-d'),'to'=>$tomorrow]);
check((int)$reports['revenue']===170000 && (int)$reports['refunds']===30000,'revenue subtracts refunds and does not double count service joins');
check((int)$reports['visits']===1 && (int)$reports['customers']===1,'reports count actual check-ins');
$late=Bookings::create(array_replace($input,['starts_at'=>"$tomorrow 17:00:00",'ends_at'=>"$tomorrow 18:00:00",'points'=>0]),$alice);
run("UPDATE bookings SET status='checked_in',starts_at=?,ends_at=?,checked_in_at=? WHERE id=?",[$now->modify('-65 minutes')->format('Y-m-d H:i:00'),$now->modify('-5 minutes')->format('Y-m-d H:i:00'),$now->modify('-65 minutes')->format('Y-m-d H:i:00'),$late['id']]);
$preview=Bookings::detail((int)$late['id'],$staff);
check($preview['estimated_room_amount']>120000 && $preview['overtime_minutes']>=5,'live invoice previews overdue time and charges');
rejects(fn()=>Bookings::action((int)$late['id'],'checkout',['method'=>'invalid'],$staff),422,'invalid payment method rolls back checkout');
check(Bookings::detail((int)$late['id'],$staff)['paid']===0 && one('SELECT status FROM bookings WHERE id=?',[$late['id']])['status']==='checked_in','failed checkout leaves status and receipts unchanged');
$late=Bookings::action((int)$late['id'],'checkout',['method'=>'cash'],$staff);
check($late['room_amount']>=130000 && $late['room_amount']<=134000 && $late['due']===0,'overdue checkout bills extra minutes at frozen hourly rate');
Management::settings(['earn_per'=>20000,'point_value'=>2000]);
check((int)one('SELECT earn_per FROM bookings WHERE id=?',[$live['id']])['earn_per']===10000,'settings changes preserve booking earning ratio');
rejects(fn()=>Management::account(['id'=>$admin['id'],'role'=>'customer','active'=>0],$admin),422,'admin cannot lock or demote own account');
Management::account(['id'=>$bob['id'],'role'=>'staff','active'=>1],$admin);
check(one('SELECT role FROM users WHERE id=?',[$bob['id']])['role']==='staff','admin can assign staff role');

// Two separate PHP processes and DB connections race for the same room.
$raceStart="$tomorrow 20:00:00"; $raceEnd="$tomorrow 21:00:00"; $gate=microtime(true)+.5;
$workers=[];
for($i=0;$i<2;$i++) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/race.php',(string)$room['id'],(string)$alice['id'],$raceStart,$raceEnd,(string)$gate],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $workers[]=[$proc,$pipes];
}
$results=[];
foreach($workers as [$proc,$pipes]) {
    $output=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($proc)===0 && !$err,'race worker completed');
    $results[]=json_decode($output,true,512,JSON_THROW_ON_ERROR);
}
check(count(array_filter($results,fn($r)=>isset($r['created'])))===1 && count(array_filter($results,fn($r)=>($r['error']??0)===409))===1,'concurrent reservations: exactly one winner and one conflict');

// Exercise real HTTP middleware, cookies, CSRF and permission checks.
$server=proc_open([PHP_BINARY,'-S','127.0.0.1:8099','-t',__DIR__.'/../public',__DIR__.'/../public/router.php'],[1=>['file','/tmp/musicbox-test-server.log','w'],2=>['file','/tmp/musicbox-test-server.log','a']],$pipes,__DIR__.'/..');
try {
    for($i=0;$i<50;$i++) { $fp=@fsockopen('127.0.0.1',8099,$errno,$errstr,.1);if($fp){fclose($fp);break;}usleep(100000); }
    $customer=new Client(); $anon=new Client(); $operator=new Client();
    [$code,$boot]=$customer->call('bootstrap'); check($code===200 && isset($boot['data']['csrf']),'HTTP bootstrap issues session and CSRF');
    $customer->csrf=$boot['data']['csrf'];
    check($anon->call('book',['room_id'=>1])[0]===419,'POST without CSRF rejected');
    check($customer->call('book',[])[0]===401,'anonymous booking rejected');
    check($customer->call('login',['email'=>'alice@test.local','password'=>'wrongpassword'])[0]===401,'invalid password rejected');
    check($customer->call('login',['email'=>'alice@test.local','password'=>'TestMusicBox!123'])[0]===200,'HTTP login with session rotation succeeds');
    check($customer->call('room_save',[])[0]===403,'customer denied admin catalogue writes');
    check($customer->call('reports')[0]===403,'customer denied revenue reports');
    check($customer->call('booking',null,['id'=>$adjacent['id']])[0]===403,'HTTP cross-account invoice read rejected');
    check($customer->call('register',null)[0]===405,'GET cannot mutate accounts');
    check($customer->call('book',['room_id'=>['bad']])[0]===400,'nested untrusted input rejected cleanly');
    [$code,$p]=$operator->call('bootstrap');$operator->csrf=$p['data']['csrf'];
    $operator->call('login',['email'=>'staff@test.local','password'=>'TestMusicBox!123']);
    check($operator->call('customers')[0]===200,'staff can search customer records');
    check($operator->call('settings_save',['earn_per'=>1,'point_value'=>1])[0]===403,'staff cannot change loyalty settings');
    check($customer->call('profile',['name'=>'Alice Updated','phone'=>'0909999999','current_password'=>'wrong','password'=>'AnotherGood!123'])[0]===422,'password change requires correct current password');
    check($customer->call('profile',['name'=>'Alice Updated','phone'=>'0909999999','current_password'=>'TestMusicBox!123','password'=>'AnotherGood!123'])[0]===200,'profile and password can be updated');
    check($customer->call('login',['email'=>'alice@test.local','password'=>'AnotherGood!123'])[0]===200,'new password works after change');
    [$code,$p]=$anon->call('bootstrap');$anon->csrf=$p['data']['csrf'];
    for ($i=0;$i<10;$i++) $anon->call('login',['email'=>'throttle@test.local','password'=>'BadPassword!123']);
    check($anon->call('login',['email'=>'throttle@test.local','password'=>'BadPassword!123'])[0]===429,'login rate limiter persists failed attempts');
    Management::account(['id'=>$staff['id'],'role'=>'staff','active'=>0],$admin);
    check($operator->call('customers')[0]===401,'locking an account revokes an existing session');
    Management::account(['id'=>$staff['id'],'role'=>'staff','active'=>1],$admin);
    $new=new Client();[$code,$p]=$new->call('bootstrap');$new->csrf=$p['data']['csrf'];
    [$code,$r]=$new->call('register',['name'=>'New user','phone'=>'0901234567','email'=>'new@test.local','password'=>'TestMusicBox!123','role'=>'admin']);
    check($code===200 && $r['data']['role']==='customer','registration cannot elevate requested role');
    check($new->call('logout',[])[0]===200 && $new->call('bookings')[0]===401,'logout revokes authenticated session');
    [$code,$available]=$anon->call('availability',null,['room_id'=>$room['id'],'date'=>$tomorrow]);
    check($code===200 && !str_contains(json_encode($available),'customer_email'),'public availability discloses no customer data');
    $http=file_get_contents('http://127.0.0.1:8099/');
    check(str_contains($http,'lang="vi"') && str_contains($http,'app.js'),'Vietnamese application shell served');
    check(@file_get_contents('http://127.0.0.1:8099/../app/bootstrap.php')===false,'private source unavailable through web root');
} finally { proc_terminate($server);proc_close($server); }
// Time-controlled fixture for the optional browser lifecycle test. This is
// isolated test data, not a way for application users to bypass check-in time.
$browserRoom=Management::room(['name'=>'Browser Live Studio','description'=>'Live lifecycle fixture','capacity'=>4,'hourly_rate'=>120000,'opens_at'=>'00:00','closes_at'=>'23:59']);
$browserBooking=Bookings::create(['room_id'=>$browserRoom['id'],'starts_at'=>"$tomorrow 10:00:00",'ends_at'=>"$tomorrow 11:00:00"],$bob);
$fixtureNow=new DateTimeImmutable();
run("UPDATE bookings SET status='confirmed',starts_at=?,ends_at=? WHERE id=?",[$fixtureNow->modify('-5 minutes')->format('Y-m-d H:i:00'),$fixtureNow->modify('+55 minutes')->format('Y-m-d H:i:00'),$browserBooking['id']]);
echo "\n$count checks passed on MariaDB + PHP ".PHP_VERSION.".\n";

function dayPlusTest(string $date): string { return (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d'); }
class Client {
    public string $cookie=''; public string $csrf='';
    public function call(string $action, ?array $data=null, array $query=[]): array {
        $headers=['Cookie: '.$this->cookie,'X-CSRF-Token: '.$this->csrf,'Content-Type: application/json'];
        $context=stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>implode("\r\n",$headers),'content'=>$data===null?'':json_encode($data),'ignore_errors'=>true,'timeout'=>5]]);
        $body=file_get_contents('http://127.0.0.1:8099/api?'.http_build_query(['action'=>$action]+$query),false,$context);
        foreach($http_response_header as $h) if(preg_match('/^Set-Cookie: (musicbox_session=[^;]+)/i',$h,$m)) $this->cookie=$m[1];
        preg_match('/\s(\d{3})\s/',$http_response_header[0],$m);
        return [(int)$m[1],json_decode($body,true,512,JSON_THROW_ON_ERROR)];
    }
}
