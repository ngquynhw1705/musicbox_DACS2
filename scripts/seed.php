<?php
require __DIR__ . '/../app/bootstrap.php';
if (getenv('APP_ENV') === 'production') { fwrite(STDERR, "Demo seed is disabled in production.\n"); exit(1); }
transaction(function () {
    run('INSERT IGNORE INTO settings (id) VALUES (1)');
    foreach ([['Quản trị viên','admin@musicbox.local','admin'], ['Nhân viên Music Box','staff@musicbox.local','staff'], ['Minh Anh','customer@musicbox.local','customer']] as [$name,$email,$role]) {
        run('INSERT IGNORE INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,?)', [$name,$email,'0901234567',password_hash('MusicBox@123',PASSWORD_DEFAULT),$role]);
    }
    // Seed only empty catalogues; never overwrite operator changes.
    if (!(int)one('SELECT COUNT(*) n FROM rooms')['n']) {
        foreach ([['Velvet Studio','Không gian tím ấm áp, âm thanh trong trẻo và ánh đèn dịu. Lý tưởng cho buổi hẹn cùng âm nhạc.',4,120000,'violet'],['Neon Lounge','Đèn neon, micro không dây và màn hình lớn. Sẵn sàng cho những bản hit cùng hội bạn.',8,200000,'blue'],['Sunset Room','Sắc cam hoàng hôn, sofa thoải mái và playlist không giới hạn. Một góc riêng để thả mình.',6,160000,'orange'],['The Grand Box','Phòng tiệc rộng với âm thanh sống động, màn hình lớn và sân khấu mini cho cả nhóm.',12,300000,'pink']] as $r) {
            run('INSERT INTO rooms (name,description,capacity,hourly_rate,theme) VALUES (?,?,?,?,?)',$r);
        }
    }
    if (!(int)one('SELECT COUNT(*) n FROM services')['n']) {
        foreach ([['Nước suối',15000],['Trà đào',35000],['Coca-Cola',25000],['Khoai tây chiên',45000],['Combo hội bạn',120000]] as $s) run('INSERT INTO services (name,price) VALUES (?,?)',$s);
    }
});
echo "Demo catalog and accounts ready. Password: MusicBox@123 (local development only).\n";
