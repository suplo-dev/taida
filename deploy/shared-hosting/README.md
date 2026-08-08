# Triển khai lên shared hosting (cPanel / DirectAdmin)

Shared hosting không chạy được tiến trình Node thường trực, nên bản SSR trong
`deploy/` (nginx + systemd) **không dùng được ở đây**. Thay vào đó site được
sinh sẵn thành HTML tĩnh, còn Laravel chạy như một ứng dụng PHP bình thường —
đúng thứ shared hosting sinh ra để làm.

```
   taida.vn        →  public_html/          82 file HTML tĩnh (Nuxt generate)
   api.taida.vn    →  api/public/           Laravel (PHP + MySQL)
```

Trang quản trị `/admin` vẫn chạy được: nó vốn là SPA phía trình duyệt, gọi thẳng
sang `api.taida.vn`. Sửa nội dung vẫn lưu vào database ngay.

## Điều đánh đổi, cần thống nhất trước

**Nội dung sửa xong sẽ không tự lên site.** HTML được sinh lúc build, nên sau
mỗi lần biên tập phải chạy lại `pnpm generate` và tải bản mới lên. Đây không
phải lỗi cấu hình mà là bản chất của việc bỏ tiến trình Node.

Nếu khách cần sửa nội dung là thấy ngay, chỉ có hai đường: đưa Nuxt lên một chỗ
chạy được Node (giữ nguyên Laravel ở hosting này), hoặc chuyển hẳn sang VPS
theo `deploy/README.md`.

## 1. Yêu cầu hosting

| | |
|---|---|
| PHP | 8.3 trở lên (`composer.json` yêu cầu `^8.3`) |
| MySQL | 8.0 trở lên |
| Subdomain | tạo được `api.taida.vn`, trỏ document root vào thư mục `public` của Laravel |
| Apache | bật `mod_rewrite`, `mod_headers`, `mod_expires`, `mod_mime` |
| SSH | không bắt buộc, nhưng thiếu thì phải chạy `composer install` ở máy rồi upload cả `vendor/` |

Kiểm tra trước khi làm tiếp — thiếu quyền tạo subdomain là hỏng cả kiến trúc:

```bash
php -v
```

## 2. Build ở máy

Nuxt phải build trên máy có Node 22. Khác với bản SSR, bản tĩnh **không** cần
build trên Linux — output chỉ là HTML/CSS/JS, không kèm binary `sharp`.

```bash
cd fe
pnpm install --frozen-lockfile
NUXT_PUBLIC_API_BASE=https://api.taida.vn \
NUXT_PUBLIC_SITE_URL=https://www.taida.vn \
pnpm generate
```

Hai biến này bắt buộc phải có **lúc build**. Chúng được nướng vào từng file HTML
và quyết định danh sách domain `@nuxt/image` được phép xử lý. Sai thì site trỏ
về `localhost` và không có gì báo lỗi.

**API phải chạy được và có dữ liệu trong lúc build** — Nuxt gọi vào đó để dựng
từng trang. Build lần đầu thì trỏ vào API đã lên hosting.

Kết quả nằm ở `fe/.output/public/` (~9 MB): 82 trang HTML, 70 ảnh chia sẻ mạng
xã hội, sitemap, và `.htaccess` — tất cả đều là file tĩnh, không cần tiến trình
nào chạy nền.

## 3. Đưa Laravel lên

Upload toàn bộ `be/` (trừ `node_modules`, `tests`) vào một thư mục **ngoài**
`public_html`, ví dụ `~/api`. Trỏ document root của subdomain `api.taida.vn`
vào `~/api/public`.

```bash
cd ~/api
composer install --no-dev --optimize-autoloader
cp .env.example .env       # rồi sửa theo bảng dưới
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # chỉ lần đầu: tạo tài khoản admin + dữ liệu mẫu
php artisan storage:link
php artisan config:cache && php artisan route:cache
```

Không có SSH thì chạy `composer install` ở máy rồi upload luôn `vendor/`, và
dùng một script PHP tạm để gọi các lệnh artisan — hoặc chạy migration bằng
phpMyAdmin từ file SQL xuất ở máy.

Các giá trị `.env` phải khớp nhau, sai một cái là đăng nhập admin xong request
kế tiếp trả 401:

| Biến | Giá trị |
|---|---|
| `APP_URL` | `https://api.taida.vn` |
| `SESSION_DOMAIN` | `.taida.vn` |
| `SESSION_SECURE_COOKIE` | `true` |
| `SANCTUM_STATEFUL_DOMAINS` | `www.taida.vn,taida.vn` |
| `FRONTEND_URLS` | `https://www.taida.vn,https://taida.vn` |
| `TRUSTED_PROXIES` | `*` |

`TRUSTED_PROXIES=*` ở đây là chấp nhận được vì chỉ hạ tầng của nhà cung cấp mới
tới được PHP; trên VPS tự quản thì nên liệt kê IP cụ thể.

## 4. Đưa site tĩnh lên

Copy **toàn bộ nội dung bên trong** `fe/.output/public/` vào `public_html/`
(nội dung bên trong, không phải cả thư mục).

```bash
rsync -az --delete fe/.output/public/ user@host:~/public_html/
```

Dùng FTP thì nhớ bật hiển thị file ẩn — **`.htaccess` là file ẩn**, thiếu nó thì
mất nén, mất cache và `/admin` hỏng khi tải lại trang, mà không có thông báo gì.

Cũng nhớ tải cả các file `.gz` và `.br` đi kèm; đó chính là các bản đã nén sẵn.

## 5. Kiểm tra sau khi lên

```bash
curl -sI -H 'Accept-Encoding: br' https://www.taida.vn/ | grep -i content-encoding   # phải là br hoặc gzip
curl -s -o /dev/null -w '%{http_code}\n' https://www.taida.vn/admin/services/1       # phải 200, không phải 404
curl -s -o /dev/null -w '%{http_code}\n' https://www.taida.vn/khong-ton-tai          # phải 404
curl -s https://api.taida.vn/api/v1/settings | head -c 200
```

Rồi mở `https://www.taida.vn/admin`, đăng nhập, sửa thử một dịch vụ và lưu —
API phải trả 200. (Nội dung trên site chỉ đổi sau khi build lại, xem mục 6.)

## 6. Cập nhật nội dung

Cách nhanh nhất: vào tab **Actions** trên GitHub, chọn workflow **Publish site**
rồi bấm *Run workflow*. Nó chạy test, build lại từ API thật, upload, và kiểm tra
lại site sau khi lên. Biên tập viên tự bấm được, không cần lập trình viên.

Cần khai báo trước trên GitHub:

| Loại | Tên | Ví dụ |
|---|---|---|
| Secret | `DEPLOY_HOST` / `DEPLOY_USER` / `DEPLOY_SSH_KEY` | tài khoản SSH của hosting |
| Secret | `DEPLOY_KNOWN_HOSTS` | kết quả `ssh-keyscan <host>` |
| Variable | `DEPLOY_SITE_PATH` | `/home/taida/public_html` |
| Variable | `NUXT_PUBLIC_SITE_URL` | `https://www.taida.vn` |
| Variable | `NUXT_PUBLIC_API_BASE` | `https://api.taida.vn` |

Làm tay thì vẫn là hai lệnh:

```bash
cd fe
NUXT_PUBLIC_API_BASE=https://api.taida.vn \
NUXT_PUBLIC_SITE_URL=https://www.taida.vn \
pnpm generate
rsync -az --delete .output/public/ user@host:~/public_html/
```

Hosting không cho SSH thì bỏ bước rsync và upload `fe/.output/public/` bằng FTP
— nhớ bật hiển thị file ẩn để `.htaccess` đi cùng.

## Những chỗ hay hỏng

| Triệu chứng | Nguyên nhân |
|---|---|
| Site tải chậm, Lighthouse tụt ~10 điểm | thiếu `.htaccess` (file ẩn, FTP không hiện) |
| `/admin/...` 404 khi bấm F5 | như trên — fallback SPA nằm trong `.htaccess` |
| Đăng nhập admin OK, request sau 401 | `SESSION_DOMAIN` / `SANCTUM_STATEFUL_DOMAINS` / `FRONTEND_URLS` không khớp |
| Trình duyệt báo CORS | `FRONTEND_URLS` thiếu domain đang mở |
| Ảnh upload 404 | chưa chạy `php artisan storage:link` |
| Sửa nội dung không lên site | đúng như thiết kế — phải build lại (mục 6) |
| `pnpm generate` báo 404 giữa chừng | API không truy cập được, hoặc có bản ghi thiếu bản dịch |
| Build lỗi lạ sau khi đổi cấu hình | xoá `fe/.nuxt` và `fe/.output` rồi build lại |
