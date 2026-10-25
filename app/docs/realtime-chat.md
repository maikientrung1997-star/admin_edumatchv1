# Chat realtime EduMatch

Triển khai ngày 25/10/2026 cho Laravel 10 và React 19. Giao diện `/tin-nhan` giữ bố cục ba cột, hỗ trợ mobile. Migration bổ sung `2026_10_25_000002_enhance_chat_system_features.php` đã được chạy trên database của môi trường hiện tại.

## Cấu hình và chạy

BE dùng các biến đã có: `BROADCAST_DRIVER=pusher`, `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER`. Không đưa secret vào React. Sau khi sửa env: `php artisan config:clear`. Chat sử dụng `ShouldBroadcastNow` và chỉ publish sau khi transaction commit, không cần queue worker cho các sự kiện chat này.

FE dùng `VITE_API_BASE_URL`, `VITE_PUSHER_APP_KEY`, `VITE_PUSHER_APP_CLUSTER` và tùy chọn `VITE_PUSHER_AUTH_ENDPOINT` (mặc định bỏ `/api` khỏi API base rồi thêm `/broadcasting/auth`). Khởi động lại Vite sau khi sửa env. CORS phải cho phép origin thực của FE.

Để nhận đúng giới hạn **3 tệp × 10 MB**, PHP cần `upload_max_filesize >= 10M`, `post_max_size >= 32M`; reverse proxy cũng cần cho phép request ít nhất 32 MB. Xem cấu hình của PHP phục vụ HTTP, không chỉ PHP CLI.

## Luồng realtime, không polling

- `presence-online`: danh sách ID đang kết nối, sự kiện member added/removed. Không phát email, số điện thoại hay thông tin riêng trong presence.
- `private-user.{id}`: dùng chung kết nối/kênh với thông báo hiện có; `conversation.updated` mang inbox, preview, unread và revision riêng của người nhận.
- `private-chat.conversation.{id}`: `message.sent`, `message.read`, `message.reacted`, typing. Chỉ thành viên có tài khoản active được cấp quyền qua `/broadcasting/auth`.
- Một Pusher client theo phiên đăng nhập, quản lý channel bằng reference count; rời chat không làm mất kênh thông báo. Logout/đổi token đóng client cũ. Chỉ bật transport WebSocket, không có XHR streaming/fallback polling.
- API GET dùng lúc vào trang, đổi bộ lọc, tải trang trước, nhận sự kiện yêu cầu dữ liệu lớn, focus lại hoặc subscribe/reconnect. Không có interval gọi API chat/online/unread. Reconnect tải bù tin sau cursor tới snapshot hiện tại.
- Tin nhắn optimistic có UUID `client_id`; HTTP và socket hợp nhất theo UUID/ID, gửi lại cùng UUID không tạo tin trùng. Reaction, inbox có revision để phản hồi HTTP cũ không ghi đè sự kiện mới.
- Đã xem dựa trên ID tin nhắn tăng dần; chỉ đánh dấu khi khung chat đang hiển thị, có focus và ở cuối lịch sử. Không dùng thời gian máy khách để suy ra đã đọc.

Typing mặc định là một POST `/typing` khi người dùng gõ, tối đa khoảng 1 lần/2,1 giây; server xác thực rồi push `message.typing`, chỉ báo tự hết sau 5 giây. Đây là sự kiện do thao tác gõ, không phải polling. Nếu muốn dùng đúng client event `client-typing`, bật **Client Events** trong Pusher Dashboard và đặt `CHAT_CLIENT_TYPING=true` ở BE. Client vẫn kiểm tra ID thành viên; không dùng sự kiện typing để quyết định quyền truy cập hay trạng thái đã xem.

## API (Bearer Sanctum + tài khoản active)

Prefix `/api/chat`. Mọi ID đều là ID database thật.

| Method | Path | Mục đích |
|---|---|---|
| GET | `/config` | Khả năng typing, giới hạn tệp |
| GET | `/users/search?q=...` | Tìm tên, điện thoại, email chính xác, tối đa 20 |
| GET | `/conversations?q=&type=direct/group&unread=1&page=1` | Inbox, phân trang 30, counts và revision |
| POST | `/conversations` | `{recipient_id}`; tái sử dụng cặp direct đã có |
| POST | `/groups` | `{title,member_ids:[]}`; 2–20 thành viên kể cả người tạo |
| GET | `/conversations/{id}` | Chi tiết, thành viên, mốc đọc, ghi chú của chính người xem |
| GET | `/conversations/{id}/messages?before_id=...` | Lịch sử 30 tin/trang; có thể dùng `after_id` để tải bù |
| POST | `/conversations/{id}/messages` | Multipart: UUID `client_id`, `body`, JSON `metadata`, `files[]` |
| GET | `/messages/{id}` | Lấy một tin khi payload socket vượt ngưỡng |
| POST | `/conversations/{id}/read` | `{last_message_id}` thuộc hội thoại; watermark không lùi |
| POST | `/messages/{id}/reactions` | `{emoji}`; thêm/đổi/bỏ một trong 6 cảm xúc |
| PUT | `/conversations/{id}/notes` | `{notes}` tối đa 10.000 ký tự; riêng người lưu |
| GET | `/conversations/{id}/media?type=image/file&before_id=...` | 30 tệp/trang |
| GET | `/attachments/{id}?inline=1` | Tải blob có xác thực, inline chỉ cho ảnh |
| POST | `/conversations/{id}/typing` | Push chỉ báo gõ đã xác thực |

Văn bản và công thức tối đa 4.000 ký tự. Metadata chỉ nhận `math`, `formula`, `reply_to_id`. Công thức hiển thị bằng KaTeX lazy-load với `trust=false`, không chạy HTML/JS từ người gửi. Cho phép JPG/JPEG/PNG/WebP/GIF/PDF/DOC/DOCX/XLS/XLSX/ZIP/TXT; kiểm tra dung lượng, MIME, extension và kích thước ảnh ở server.

## Bảo mật và tính riêng tư

Tệp ở **storage/app/chat_attachments**, disk `chat` private, không có public storage URL. Chỉ thành viên tải/xem được qua API có Bearer; response no-store, nosniff, CSP sandbox. FE tạo blob URL tạm rồi thu hồi. Không chuyển tệp qua Pusher; sự kiện chỉ mang ID/metadata được whitelist. Payload lớn chuyển sang `requires_fetch` để tránh vượt 10 KB của Pusher.

Ghi chú lưu vào `chat_participants.notes`, autosave 800 ms và tuần tự hóa các lần lưu; hủy hàng đợi nếu đổi tài khoản. Không đưa ghi chú vào broadcast/inbox. Thông tin hồ sơ lấy từ DB; không suy diễn trường học, vai trò hoặc số điện thoại từ dữ liệu mẫu. Số điện thoại chỉ hiện nếu chủ hồ sơ cho công khai hoặc tìm đúng số đã biết; không trả email trong kết quả tìm kiếm.

PHP CLI hiện tại đã có `upload_max_filesize=40M`, `post_max_size=40M`, đủ giới hạn gửi tệp nêu trên.

API và broadcast auth kiểm tra thành viên/tài khoản active. API có throttle gửi/tìm kiếm/tạo/typing; tin nhắn được khóa transaction, lưu bền trước khi publish. Nếu Pusher lỗi, tin vẫn lưu và có thể tải bù khi kết nối lại hoặc bấm Làm mới; không tự bật polling.

## Kiểm tra

BE: `php artisan test --filter=RealtimeChatTest`. FE: `npm run test:chat`, `npm run test:auth`, `npm run test:phase2`, `npm run lint`, `npm run build`.

Đã thử với hai tài khoản trong hai origin độc lập và database QA riêng, dùng Pusher thật: tìm người, tạo direct, nhắn hai chiều không F5, online, unread, đã xem, reaction, ảnh/tệp, tải tệp, lightbox, công thức, lưu ghi chú sau reload và không lộ ghi chú cho người kia; tạo nhóm, tin nhóm/unread, typing và chuyển offline khi đóng phiên đối phương. Ảnh kiểm tra ở FE `output/qa/realtime-chat-teacher.png`.

## Giới hạn vận hành

Presence channel của Pusher giới hạn 100 thành viên; khi triển khai vượt quy mô này cần chia presence theo phạm vi hoặc đổi cơ chế presence. Tham khảo [Pusher Presence](https://pusher.com/docs/channels/using_channels/presence-channels/). Online thể hiện kết nối WebSocket, phát hiện offline theo timeout của Pusher. Chat nhóm hiện hỗ trợ tạo, nhắn, cảm xúc, thành viên và đọc; chưa có quản trị thêm/xóa thành viên sau khi tạo.

Optimistic UI xuất hiện ngay; thời gian mạng, vùng Pusher, upload và các HTTP publish đồng bộ quyết định độ trễ nhận, không bảo đảm dưới 100 ms. Client events phải bật trên Dashboard: [Pusher Events](https://pusher.com/docs/channels/using_channels/events/).
