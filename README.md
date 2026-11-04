# Music Box — Quản lý & đặt phòng

Website đồ án bằng **PHP 8.3, JavaScript thuần và MySQL/MariaDB**. Giao diện tiếng Việt, responsive, dữ liệu thật lưu trong database; không cần framework hoặc CDN. Ảnh SVG minh họa đi kèm mã nguồn, có thể thay bằng URL ảnh trong trang quản trị.

## Chức năng

- Đăng ký, đăng nhập, đăng xuất, sửa hồ sơ, đổi mật khẩu; ba vai trò admin / staff / customer.
- Xem phòng, tìm lịch trống theo ngày, giờ, thời lượng, sức chứa. Chống trùng lịch bằng transaction và khóa dòng phòng, kể cả hai yêu cầu đồng thời.
- Đặt, xác nhận, hủy; check-in, gia hạn, dịch vụ bổ sung, check-out và in hóa đơn.
- Cọc 30%, ghi nhận thu tiền / hoàn tiền, tiền thuê theo phút; đơn giá được chốt tại thời điểm đặt / thêm dịch vụ.
- Tích điểm khi hoàn tất thanh toán, dùng điểm giảm tối đa 50% tiền phòng, sổ cộng/trừ/hoàn điểm.
- Quản lý phòng, hình ảnh, giờ mở cửa, bảo trì, dịch vụ, tài khoản và tỷ lệ điểm.
- Tra cứu khách hàng / lịch sử, báo cáo thực thu, lượt đặt, lượt ghé, tỷ lệ hủy; xuất thực thu CSV.

**Thanh toán là mô phỏng và nhân viên ghi nhận thủ công**, chưa kết nối cổng thanh toán hoặc ngân hàng. Số lượt khách là số lượt check-in của tài khoản đặt phòng, không phải số người thực tế đi cùng. Báo cáo không phải chứng từ thuế.

## Chạy nhanh bằng Docker

Yêu cầu Docker Engine + Docker Compose v2; cổng 8080 còn trống. Dùng checkout hiện có, không cần tạo Git worktree.

```sh
cd /workspace/musicbox_DACS2
# Tùy chọn: sao chép .env.example thành .env nếu chưa có; không ghi đè .env đang dùng.
sh scripts/setup.sh --demo
```

Mở ứng dụng ở cổng **8080** của máy chạy Docker (địa chỉ cục bộ `http://127.0.0.1:8080`). Trong môi trường cloud, sử dụng cơ chế truy cập cổng do nền tảng cung cấp. MySQL không được mở cổng ra máy chủ. Cổng web được bind `0.0.0.0:8080` để công cụ preview truy cập; có thể đặt `APP_BIND=127.0.0.1` nếu chỉ muốn truy cập trên máy cá nhân. Compose bật `APP_PREVIEW=1` cho demo để cho phép nhúng iframe; production luôn chặn nhúng.

Các lệnh tương đương:

```sh
docker compose up -d --build
docker compose exec -T app php scripts/migrate.php
docker compose exec -T app php scripts/seed.php
docker compose exec -T app php scripts/health.php
```

| Vai trò demo | Email | Mật khẩu (chỉ phát triển) |
| --- | --- | --- |
| Quản trị viên | `admin@musicbox.local` | `MusicBox@123` |
| Nhân viên | `staff@musicbox.local` | `MusicBox@123` |
| Khách hàng | `customer@musicbox.local` | `MusicBox@123` |

Seed chỉ tạo tài khoản chưa tồn tại, thêm danh mục khi bảng trống; không ghi đè dữ liệu đã chỉnh sửa. Database ban đầu có 4 phòng và 5 dịch vụ, **không tạo lịch đặt hoặc doanh thu giả**. Tài khoản mới có 0 điểm. Migrate / seed có thể chạy lại.

```sh
docker compose logs --tail=60 app db
docker compose stop                # Dừng, giữ nguyên dữ liệu
docker compose up -d               # Khởi động lại
```

Database được giữ trong volume `musicbox_data`. **Không chạy `docker compose down -v` nếu cần giữ dữ liệu.** Sao lưu database trước khi thay schema hoặc nâng phiên bản server.

## Kiểm thử

Kiểm thử PHP và HTTP dùng **database riêng `musicbox_test`**. Runner xóa và tạo lại các bảng chỉ trong database test, không sửa database ứng dụng.

```sh
sh scripts/test.sh
```

Bao gồm tính tiền, snapshot giá, giới hạn và hoàn điểm, cọc / hoàn cọc, check-in / check-out, bảo trì, gia hạn, race hai tiến trình PHP, middleware CSRF, session, quyền và truy cập chéo hóa đơn. Không cần PHPUnit / Composer.

Đã xác thực 62 kiểm tra backend và 14 kiểm tra trình duyệt trong môi trường phát triển; xem [báo cáo](docs/REPORT.md) để biết phạm vi và giới hạn.

Kiểm thử trình duyệt (tùy chọn, cần Node.js 20+):

```sh
npm ci
npx playwright install chromium     # Không cần nếu dùng Chromium hệ thống
# Chạy test.sh trước để chuẩn bị database / tài khoản test.
docker compose run -d --name musicbox-e2e -p 127.0.0.1:8081:8080 \
  -e APP_ENV=test -e DB_DATABASE=musicbox_test app
npm run test:browser
# Hoặc: CHROMIUM_PATH=/usr/bin/chromium npm run test:browser
docker rm -f musicbox-e2e
```

Test trình duyệt mặc định dùng cổng **8081**, tạo dữ liệu trong database test; không trỏ `TEST_BASE_URL` vào hệ thống thật. Ảnh chụp lưu tại `test-results/` (được Git bỏ qua). Test không tự tải Chromium khi chạy.

## Chạy với PHP & MySQL đã cài sẵn

Cần PHP 8.2+ với `PDO` / `pdo_mysql`, MySQL 8.0.16+ hoặc MariaDB 10.6+ (đã kiểm thử MariaDB 11.4). Tạo database `utf8mb4` và tài khoản database riêng có quyền trên database đó. Cung cấp các biến môi trường:

| Biến | Ý nghĩa |
| --- | --- |
| `DB_HOST`, `DB_PORT` | Địa chỉ database, mặc định `127.0.0.1:3306` |
| `DB_DATABASE` | Tên database, mặc định `musicbox` |
| `DB_USERNAME`, `DB_PASSWORD` | Tài khoản ứng dụng; cung cấp qua môi trường |
| `APP_ENV` | `development`, `test` hoặc `production` |

```sh
php scripts/migrate.php
php scripts/seed.php               # Chỉ ở development
php -S 127.0.0.1:8080 -t public public/router.php
```

PHP thuần không tự đọc `.env`; file này chỉ được Docker Compose đọc. Với PHP native, dùng biến môi trường của shell / web server. Document root luôn là **`public/`**, tuyệt đối không phục vụ thư mục gốc repository. Router đi kèm dành cho development; khi dùng Apache/Nginx cần route `/api` tới `public/api.php`, `/` tới `public/index.php`, chỉ phục vụ tài nguyên tĩnh trong `public/assets/`.

Để không dùng tài khoản mẫu: chạy setup **không có `--demo`**, rồi gọi `scripts/create-admin.php` với các biến `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PHONE`, `ADMIN_PASSWORD` được cung cấp an toàn trong môi trường của tiến trình PHP. Script không in mật khẩu. `APP_ENV=production` chặn seed và ẩn nút đăng nhập mẫu. Không dùng tài khoản/mật khẩu demo cho triển khai thật.

## Cấu trúc & tài liệu

```text
app/                 PDO, xác thực, nghiệp vụ đặt phòng và quản trị
public/              Web root, API, giao diện và tài nguyên tĩnh
database/schema.sql  Schema InnoDB, khóa ngoại và chỉ mục
scripts/             Setup, migrate, seed, tạo admin, health, test
tests/               Test nghiệp vụ/HTTP, race và trình duyệt
docs/                Phân tích, thiết kế, hướng dẫn, báo cáo
```

- [Yêu cầu và quy tắc nghiệp vụ](docs/REQUIREMENTS.md)
- [Kiến trúc, ERD, luồng xử lý và API](docs/DESIGN.md)
- [Hướng dẫn sử dụng](docs/USER_GUIDE.md)
- [Báo cáo tổng kết bản triển khai](docs/REPORT.md)

## Phạm vi thử nghiệm

Ứng dụng phục vụ một cơ sở, múi giờ `Asia/Ho_Chi_Minh`, tiền VND nguyên. Chỉ nhận lịch trong cùng ngày hoạt động, tối đa trước 90 ngày; chưa có lịch giá cao điểm, email/SMS, quên mật khẩu, đa chi nhánh, hóa đơn điện tử hoặc cổng thanh toán thật. Danh sách lịch/khách hàng/điểm giới hạn 300 bản ghi mới nhất, báo cáo tối đa 366 ngày. Tài khoản và danh mục được khóa/ngừng hoạt động thay vì xóa để giữ lịch sử.

Trước khi triển khai thật cần web server PHP-FPM có HTTPS, cấu hình cookie secure ở lớp kết thúc TLS, bí mật riêng, backup/restore, giám sát, phân trang dữ liệu lớn và luồng thanh toán có đối soát / webhook. Không dùng PHP development server cho tải production.
