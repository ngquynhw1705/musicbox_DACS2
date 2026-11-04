# Phân tích yêu cầu Music Box

## Bối cảnh khảo sát

Tài liệu dựa trên đề bài người dùng cung cấp. Đây là **mô hình nghiệp vụ giả định cho đồ án**, chưa phải kết quả phỏng vấn một doanh nghiệp thực tế. Khi áp dụng thực tế cần xác nhận: chính sách hủy/hoàn, ca làm, mức cọc, giữ chỗ, tiền quá giờ, thuế, giá cao điểm và cách thống kê số người đi cùng.

Luồng giả định: tìm phòng → đặt lịch → cọc / nhân viên xác nhận → nhận khách → phục vụ dịch vụ → trả phòng, thu đủ tiền → cộng điểm → dùng điểm ở lần sau.

## Tác nhân và phân quyền

| Chức năng | Vãng lai | Customer | Staff | Admin |
| --- | --- | --- | --- | --- |
| Xem phòng, lịch bận không có thông tin cá nhân | Có | Có | Có | Có |
| Đăng ký tài khoản khách | Có | Có | Có | Có |
| Hồ sơ, mật khẩu, lịch đặt, điểm của mình | — | Có | Có | Có |
| Đặt / hủy / cọc lịch của mình | — | Có | Có | Có |
| Xem và thao tác mọi lịch đặt, hóa đơn | — | — | Có | Có |
| Xác nhận, check-in/out, gia hạn, dịch vụ | — | — | Có | Có |
| Tra cứu khách hàng, báo cáo | — | — | Có | Có |
| Sửa phòng, dịch vụ, bảo trì | — | — | — | Có |
| Phân quyền, khóa tài khoản, cấu hình điểm | — | — | — | Có |

Quyền được kiểm tra ở server trong mỗi yêu cầu. Đăng ký luôn tạo customer, bỏ qua role do client gửi. Tài khoản bị khóa mất quyền ở yêu cầu tiếp theo. Không tự khóa/hạ quyền mình; giữ ít nhất một admin hoạt động.

## Yêu cầu và tiêu chí chấp nhận

| Mã | Yêu cầu | Tiêu chí |
| --- | --- | --- |
| F01 | Tài khoản | Email duy nhất, mật khẩu hash, login/logout, cập nhật hồ sơ/mật khẩu có kiểm tra mật khẩu cũ |
| F02 | Phòng | Tên, mô tả, ảnh URL, sức chứa, giá, giờ, trạng thái; đổi giá không đổi lịch cũ |
| F03 | Lịch trống | Lọc ngày/giờ/thời lượng/sức chứa; không tiết lộ khách đặt |
| F04 | Đặt phòng | Tạo/xác nhận/hủy; hai yêu cầu trùng chỉ một yêu cầu thành công |
| F05 | Khách hàng | Tìm tên/email/điện thoại, xem lịch sử và số lượt hoàn tất |
| F06 | Vận hành | Check-in/out có thời gian, không nhận hai khách cùng phòng; gia hạn phải còn trống |
| F07 | Dịch vụ | Chốt tên/giá lúc thêm; ordered → served/cancelled; tính theo số lượng |
| F08 | Thanh toán | Cọc 30%, thu còn lại khi trả phòng, lịch thu/hoàn, in hóa đơn |
| F09 | Điểm | Giữ khi đặt, hoàn khi hủy, chỉ tích một lần lúc thu đủ; ledger khớp số dư |
| F10 | Lịch sử | Khách xem lịch của mình; nhân viên tra lịch sử khách và hóa đơn |
| F11 | Báo cáo | Thực thu, lượt đặt/ghé, tài khoản khách riêng biệt, tỷ lệ hủy, CSV |
| F12 | Bảo trì | Chặn lịch; không được trùng lịch đặt đang hoạt động |

## Quy tắc nghiệp vụ

1. VND lưu nguyên. Múi giờ Việt Nam cho PHP, DB và hiển thị. Lịch cùng ngày; giờ đóng sau giờ mở.
2. Đặt 30 phút–12 giờ, theo phút tròn, trong giờ hoạt động và 90 ngày tới. Khoảng `[bắt đầu, kết thúc)` cho phép 10–11 liền 11–12.
3. Pending, confirmed, checked_in chặn lịch. **Pending chưa tự hết hạn**: khách/nhân viên phải hủy. Khi tạo lịch luôn kiểm tra lại dưới khóa phòng.
4. Giá phòng chốt lúc đặt; dịch vụ chốt lúc thêm. Tiền phòng = làm tròn lên `(phút × giá giờ / 60)`.
5. Cọc = làm tròn lên 30% tổng hiện tại sau giảm điểm. Cọc tự confirmed; nhân viên có thể xác nhận không cọc. Không tích điểm từ cọc riêng lẻ.
6. Check-in từ 30 phút trước giờ đặt đến trước giờ kết thúc; phải confirmed, không có khách khác chưa trả hoặc bảo trì chưa xong. Nhận sớm miễn phí. Trả sớm vẫn tính đủ giờ đặt; quá giờ tính thêm từng phút theo giá đã chốt. Gia hạn không trùng lịch/bảo trì.
7. Chỉ thêm dịch vụ khi checked_in. Ordered phải served/cancelled trước checkout. Cancelled không tính tiền; served không sửa lùi.
8. Checkout thu toàn bộ còn thiếu và hoàn tất trong một transaction. Đây là xác nhận thủ công, không phải bằng chứng ngân hàng.
9. Mặc định 10.000₫ thanh toán hợp lệ = 1 điểm; 1 điểm = 1.000₫. Giảm tối đa 50% tiền phòng, không giảm dịch vụ. Điểm giữ ngay lúc đặt, không dùng điểm của chính lịch chưa hoàn tất.
10. Tỷ lệ tích chốt lúc đặt; điểm cộng = phần nguyên `(phòng + dịch vụ − giảm giá) / tỷ lệ`. Không tích trên phần đã giảm hoặc hoàn.
11. Khách hủy pending/confirmed trước giờ đặt; nhân viên xử lý được lịch quá giờ chưa nhận. Hủy trả điểm và hoàn toàn bộ tiền đã thu bằng giao dịch mô phỏng. Không hủy checked_in/completed.
12. Completed/cancelled bất biến. Gửi lại cọc/checkout/hủy không làm thu tiền hoặc cộng/trả điểm hai lần. Không có API sửa ledger tiền/điểm.

## Định nghĩa báo cáo

- **Thực thu**: deposit + settlement − refund theo `payments.created_at`; cọc lịch tháng sau vẫn là tiền thu hôm nay.
- **Lượt đặt / tỷ lệ hủy**: lịch có `starts_at` trong kỳ; cancelled / toàn bộ lịch trong kỳ.
- **Lượt ghé**: lịch có `checked_in_at` trong kỳ. Khách riêng biệt: số user_id khác nhau đã check-in. Không suy ra số người đi cùng từ sức chứa.
- **Theo phòng**: lượt đặt/hoàn tất theo ngày bắt đầu. Không join cộng tiền với từng dòng dịch vụ làm nhân đôi doanh thu.

## Phi chức năng và giới hạn

Prepared statements, password_hash, cookie HttpOnly/SameSite, CSRF mọi POST, giới hạn payload, validation, escape HTML, CSP, hết session sau 2 giờ không hoạt động. Login giới hạn 10 lần sai/15 phút theo cặp IP–email, chưa thay thế WAF/rate limit toàn hệ thống. InnoDB transaction, khóa phòng → lịch → khách, khóa ngoại và unique ledger.

Giao diện có nhãn form, focus dialog, trạng thái rỗng/lỗi, mobile và bản in. Chưa chứng nhận bảo mật/WCAG. Chưa có cổng thanh toán thật, SMS/email, quên mật khẩu, đa cơ sở, thuế, hoàn từng phần, giá cao điểm, tự hết hạn pending, upload ảnh hay phân trang dữ liệu lớn.
