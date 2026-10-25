# Đăng ký và đăng nhập Google

## Cấu hình sau khi triển khai

Tạo OAuth Client loại **Web application** trong Google Cloud. Điền cùng một Client ID vào hai file đã có sẵn biến trống:

```dotenv
# C:\laragon\www\admin_edumatchv1\.env
GOOGLE_CLIENT_ID=YOUR_WEB_CLIENT_ID.apps.googleusercontent.com

# D:\Production\Edumatchv1\.env
VITE_GOOGLE_CLIENT_ID=YOUR_WEB_CLIENT_ID.apps.googleusercontent.com
```

Trong **Authorized JavaScript origins**, thêm đúng origin mở FE: `http://localhost:5173` và/hoặc `http://127.0.0.1:5173`; bổ sung origin HTTPS thật khi triển khai. Nếu OAuth app đang ở Testing, thêm email thử nghiệm vào Test users theo cấu hình consent screen của Google. Không thêm `/login` hoặc `/register` vào origin. Popup GIS trả ID token về JavaScript callback nên không cần Client Secret hoặc API redirect/callback URI cho luồng này.

Chạy `php artisan config:clear` tại BE, khởi động lại Vite (hoặc build lại FE khi chạy production). Migration `2026_10_25_000001_add_google_id_to_users_table` cần được chạy ở mỗi môi trường. Không điền Client ID giả để kiểm thử thật.

## Luồng hoạt động

Đăng nhập và đăng ký đều dùng `SocialAuthButtons`. Hai trang hiển thị Google, Facebook và Apple; chỉ Google được tích hợp trong đợt này, hai nút còn lại thông báo đang được chuẩn bị. Nút Google thực tế được GIS `renderButton` tạo với `ux_mode: popup`, không dùng `prompt()`/One Tap để giả lập popup.

FE lấy nonce từ `GET /api/auth/google/challenge` (hiệu lực 10 phút), đưa vào cấu hình GIS rồi gửi `{credential, nonce, role}` đến `POST /api/auth/google`. SDK và việc chuẩn bị nonce được chia sẻ để tránh request trùng do StrictMode. Chỉ token Sanctum và dữ liệu user của EduMatch được lưu vào phiên, không lưu credential Google hoặc mật khẩu xác nhận vào storage.

BE gọi HTTPS TokenInfo theo PLAN, timeout 5 giây; kiểm tra audience bắt buộc khớp cấu hình, issuer, hạn token, email verified, subject và nonce trong JWT đã được Google xác thực. Nonce được khóa và dùng một lần sau khi transaction tạo/liên kết tài khoản và cấp token thành công. Lỗi Google hoặc thiếu cấu hình không tạo phiên, không trả chi tiết request/credential.

Google `sub` là định danh chính. Khi trùng email, tài khoản/hồ sơ/vai trò/mật khẩu đã có được giữ nguyên. Gmail và Workspace có thể tự liên kết; với email ngoài Google không có `hd`, hoặc khi liên kết tài khoản quản trị lần đầu, FE yêu cầu mật khẩu EduMatch để xác nhận chủ tài khoản. Đây là biện pháp theo hướng dẫn xác minh email của Google, tránh chiếm tài khoản qua một email bên thứ ba từng thuộc người khác. Token `role` từ FE chỉ quyết định vai trò cho tài khoản mới, không cấp lại quyền cho tài khoản cũ.

Tài khoản mới mặc định học viên khi bấm Google ở màn đăng nhập. Ở màn đăng ký, vai trò được lấy tại thời điểm bấm nút Google. Hồ sơ tối thiểu được tạo đúng bảng: `student_profiles`, `teacher_profiles`, hoặc `centers`; tên/email/avatar lấy từ Google, các thông tin Google không cung cấp như điện thoại, ngày sinh, chuyên môn/pháp lý vẫn cần người dùng cập nhật. Hồ sơ giáo viên/trung tâm luôn bắt đầu `unverified`, không tự nhận huy hiệu.

Tài khoản blocked/deleted không được đăng nhập. Xóa tài khoản qua Admin giải phóng cả `google_id` để lần đăng ký sau tạo tài khoản mới. User Google chưa có mật khẩu do họ đặt có thể dùng luồng Quên mật khẩu hiện có để thiết lập mật khẩu EduMatch bằng OTP email.

## Kiểm thử

- BE: `php artisan test --filter=GoogleAuthTest` (database MySQL riêng, phản hồi Google được giả lập, không truy cập tài khoản thật).
- FE: `npm run test:auth`, `npm run test:google`, `npm run lint`, `npm run build`.
- Sau khi điền Client ID: kiểm tra popup trên cả đăng nhập và ba tab đăng ký, tài khoản mới/trùng email, hủy popup, tải lại trang và đăng xuất.
- Google khuyến nghị thư viện JWT/Google API Client cho production; triển khai này dùng TokenInfo qua HTTPS theo PLAN và phụ thuộc khả dụng endpoint đó. Khi chuyển sang xác minh chữ ký cục bộ, vẫn phải giữ các kiểm tra claims/nonce và luồng tài khoản.

Tài liệu tham khảo: [GIS button](https://developers.google.com/identity/gsi/web/guides/display-button), [xác minh token/độ tin cậy email](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token).
