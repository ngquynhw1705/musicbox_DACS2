# Hướng dẫn sử dụng

## Khách hàng

1. **Tham gia Music Box**: điền họ tên, điện thoại, email, mật khẩu (10–72 byte). Tài khoản mới là customer, có 0 điểm.
2. **Khám phá phòng**: chọn ngày/giờ/thời lượng/số khách, **Tìm phòng trống**; **Xem & đặt phòng** hiển thị giờ bận và bảo trì. Kết quả chỉ tham khảo tới khi tạo lịch thành công.
3. Chọn thông tin đặt, số điểm và ghi chú. Tổng tiền/cọc được hiển thị; **Tạo lịch đặt** sẽ kiểm tra lại lịch. Nếu có người khác đặt trước, chọn khung khác.
4. **Lịch đặt của tôi** → **Chi tiết** → **Đặt cọc** để tự xác nhận. Đây là mô phỏng, không nhập thông tin thẻ/ngân hàng thật.
5. Trước giờ đặt có thể **Hủy lịch đặt**: ghi hoàn cọc mô phỏng và trả điểm. Không tự hủy khi đã nhận phòng.
6. Nhân viên check-in lúc đến, check-out/thu tiền lúc về; xem lịch sử, dịch vụ và hóa đơn trong chi tiết. Lịch hoàn tất có **In hóa đơn**.
7. **Điểm thưởng** hiển thị số dư và ledger. Dùng điểm cho lần đặt sau, giảm tối đa 50% tiền phòng.
8. **Tài khoản cá nhân** sửa tên/điện thoại, đổi mật khẩu bằng mật khẩu cũ. Email chưa hỗ trợ đổi. Đăng xuất trong hồ sơ hoặc sidebar.

## Nhân viên

1. **Tổng quan**: chỉ số tháng đến hôm nay, lịch hôm nay, phòng đang có khách. Thực thu theo ngày nhận tiền, không phải tổng tiền dự kiến của mọi lịch.
2. **Quản lý lịch đặt**: lọc trạng thái/ngày/tên/email/điện thoại, xem chi tiết và xác nhận lịch pending hoặc ghi cọc theo phương thức đã nhận.
3. **Check-in** lịch confirmed từ 30 phút trước giờ đặt tới trước giờ kết thúc. Không nhận nếu phòng trước chưa trả hoặc đang bảo trì.
4. Khi đang sử dụng, thêm dịch vụ và số lượng; đánh dấu **Đã phục vụ** hoặc **Hủy** khi chưa phục vụ. Không sửa lùi served.
5. **Gia hạn** tới giờ mới nếu còn trống, trong giờ hoạt động. Không tự đổi phòng hoặc bỏ qua lịch khác.
6. Xử lý hết dịch vụ ordered, chọn phương thức và **Check-out & thu tiền**. Xác nhận nghĩa là nhân viên đã thu đủ số còn thiếu. Hệ thống chốt tiền, lưu khoản thu và cộng điểm một lần. Trả sớm vẫn tính đủ giờ đặt; quá giờ tính thêm từng phút.
7. **Khách hàng** tra thông tin/lịch sử. **Báo cáo** chọn khoảng ngày tối đa 366 ngày; xem thực thu, lượt đặt/ghé, tỷ lệ hủy, theo phòng và xuất CSV.

## Quản trị viên

Có quyền nhân viên và:

- **Quản lý phòng**: thêm/sửa tên,mô tả,sức chứa,giá,URL ảnh,giờ,trạng thái. Ảnh trống dùng SVG mặc định. Không đổi trạng thái/thu hẹp giờ làm ảnh hưởng lịch đang hoạt động.
- **Lịch bảo trì**: thêm khoảng chặn lịch; nếu trùng lịch đang hoạt động, cần xử lý lịch khách trước. Xóa bảo trì mở lại khung giờ nếu phòng vẫn nhận đặt.
- **Dịch vụ bổ sung**: thêm/sửa/ngừng bán; giá cũ vẫn giữ trên hóa đơn.
- **Khách hàng → Phân quyền**: đổi role hoặc khóa tài khoản; không tự hạ quyền/khóa mình. Không xóa lịch sử.
- **Cài đặt hệ thống**: tỷ lệ tiền/điểm và giá trị quy đổi. Tỷ lệ tích mới chỉ áp dụng lịch tạo sau. Giới hạn giảm 50% là cố định.

## Kịch bản trình diễn

1. Migrate/seed, login customer mẫu; tạo lịch hôm nay trong 15–20 phút tới, trong giờ hoạt động, thời lượng 60 phút. Ngoài giờ thì tạo ngày sau và demo nhận phòng khi đến giờ; không có nút bỏ qua đồng hồ.
2. Cọc, đăng xuất, login staff, check-in lịch đó.
3. Thêm 2 trà đào, đánh dấu served, checkout tiền mặt mô phỏng. Tổng = tiền giờ phòng + 70.000₫.
4. Login khách, xem hóa đơn/điểm; dùng điểm đặt lần sau. Thử trùng lịch để thấy từ chối, rồi hủy để thấy hoàn tiền/điểm.
5. Login admin, báo cáo ngày; đổi giá phòng và thấy hóa đơn cũ không đổi; thêm bảo trì ngày khác và kiểm tra giờ bị chặn.

## Xử lý thường gặp

| Thông báo | Cách xử lý |
| --- | --- |
| Phiên không hợp lệ | Tải lại trang lấy CSRF/session mới, login nếu cần |
| Khung giờ vừa được đặt | Chọn giờ/phòng khác |
| Thiếu điểm / quá 50% | Giảm số điểm, kiểm tra số dư |
| Chưa tới giờ check-in | Kiểm tra ngày, giờ Việt Nam và trạng thái confirmed |
| Dịch vụ chờ xử lý | Served hoặc cancelled trước checkout |
| Login quá nhiều | Đợi hết cửa sổ 15 phút |
| Lỗi DB/server | Người vận hành xem logs, health, migrate; không công khai log nhạy cảm |
| Mất mạng sau thao tác | Tải lại chi tiết và khoản thu trước khi thử lại |
