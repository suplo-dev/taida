# Triển khai Taida — phương án VPS (SSR)

> **Đây không còn là phương án mặc định.** Site đã chuyển sang sinh tĩnh (SSG):
> xem **[`shared-hosting/README.md`](shared-hosting/README.md)**.
>
> Giữ lại tài liệu và cấu hình ở đây vì chúng vẫn chạy được và là đường quay lui
> nếu sau này cần nội dung tự lên site ngay sau khi biên tập, không phải build
> lại. Muốn dùng lại thì chạy `pnpm build` (thay cho `pnpm generate`) và bỏ
> `routeRules` chỉ dành cho SSG trong `fe/nuxt.config.ts`.

Runbook cho production. Hai tiến trình, một máy chủ:

```
                     ┌── nginx (443) ──────────────────────────────────┐
                     │                                                 │
Trình duyệt ───────▶ │  www.taida.vn  → 127.0.0.1:3000  (Nitro/Node)   │
                     │  api.taida.vn  → php-fpm socket  (Laravel)      │
                     └────────────────────┬────────────────────────────┘
                                          ▼
                                    MySQL 8.4 (localhost)
```

`www` và `api` cùng nằm dưới `taida.vn` để một cookie phiên Sanctum dùng chung
được cho cả hai — đây là điều kiện bắt buộc để admin SPA đăng nhập bằng cookie.

---

## 1. Chuẩn bị máy chủ (làm một lần)

Ubuntu 24.04 trở lên, đã trỏ DNS `taida.vn`, `www.taida.vn`, `api.taida.vn` về IP máy chủ.

```bash
sudo apt update
sudo apt install -y nginx mysql-server \
    php8.5-fpm php8.5-mysql php8.5-mbstring php8.5-xml php8.5-curl \
    php8.5-gd php8.5-zip php8.5-intl \
    libnginx-mod-http-brotli-filter libnginx-mod-http-brotli-static \
    certbot python3-certbot-nginx unzip git

# Node 22 cho Nitro
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

sudo adduser --system --group --home /var/www/taida taida
sudo mkdir -p /var/www/taida/{api/{releases,shared},web/{releases,shared},backups,deploy}
sudo mkdir -p /var/www/taida/api/shared/storage
sudo chown -R taida:taida /var/www/taida
```

PHP-FPM phải cho phép upload đúng bằng giới hạn của `MediaRequest` (8 MB) —
`/etc/php/8.5/fpm/conf.d/99-taida.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 10M
memory_limit = 256M
; Deploy đổi symlink `current`, opcache phải nhận ra file mới.
opcache.enable = 1
opcache.validate_timestamps = 0
```

`validate_timestamps = 0` nhanh hơn nhưng **bắt buộc** phải
`systemctl reload php8.5-fpm` sau mỗi lần deploy — `deploy-api.sh` đã làm việc đó.

Tạo database:

```sql
CREATE DATABASE taida CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'taida'@'localhost' IDENTIFIED BY '<mật khẩu>';
GRANT ALL PRIVILEGES ON taida.* TO 'taida'@'localhost';
```

## 2. Cấu hình nginx

```bash
sudo cp deploy/nginx/snippets/compression.conf      /etc/nginx/snippets/taida-compression.conf
sudo cp deploy/nginx/snippets/brotli.conf           /etc/nginx/snippets/taida-brotli.conf
sudo cp deploy/nginx/snippets/security-headers.conf /etc/nginx/snippets/taida-security-headers.conf
sudo cp deploy/nginx/snippets/proxy.conf            /etc/nginx/snippets/taida-proxy.conf
sudo cp deploy/nginx/www.taida.vn.conf /etc/nginx/sites-available/
sudo cp deploy/nginx/api.taida.vn.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/www.taida.vn.conf /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/api.taida.vn.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Nếu `nginx -t` báo `unknown directive "brotli"` thì module brotli chưa được cài —
xoá hai dòng `include .../taida-brotli.conf;` trong hai file server. Gzip vẫn chạy
và đó mới là phần quan trọng.

Chứng chỉ (chạy sau khi block HTTP `:80` đã hoạt động):

```bash
sudo mkdir -p /var/www/certbot
sudo certbot --nginx -d taida.vn -d www.taida.vn -d api.taida.vn
sudo systemctl enable --now certbot.timer   # tự gia hạn
```

## 3. Biến môi trường

```bash
sudo -u taida cp deploy/env/api.env.production.example /var/www/taida/api/shared/.env
sudo -u taida cp deploy/env/web.env.production.example /var/www/taida/web/shared/.env
sudo -u taida chmod 600 /var/www/taida/{api,web}/shared/.env
# Điền DB_PASSWORD, rồi sinh APP_KEY và dán vào file
php artisan key:generate --show
```

Bốn giá trị dưới đây phải khớp nhau, sai một cái là đăng nhập admin thành công
nhưng request kế tiếp trả 401:

| Biến | Giá trị |
|---|---|
| `SESSION_DOMAIN` | `.taida.vn` |
| `SESSION_SECURE_COOKIE` | `true` |
| `SANCTUM_STATEFUL_DOMAINS` | `www.taida.vn,taida.vn` |
| `FRONTEND_URLS` | `https://www.taida.vn,https://taida.vn` |

Cộng thêm `TRUSTED_PROXIES=127.0.0.1,::1` — thiếu nó thì Laravel tưởng request là
HTTP thường và `SESSION_SECURE_COOKIE` âm thầm huỷ mọi cookie.

## 4. systemd

```bash
sudo cp deploy/systemd/*.service deploy/systemd/*.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now taida-web.service
sudo systemctl enable --now taida-backup.timer
```

Tài khoản `taida` cần quyền chạy hai lệnh reload/restart mà không hỏi mật khẩu
(`sudo visudo -f /etc/sudoers.d/taida`):

```
taida ALL=(root) NOPASSWD: /bin/systemctl reload php8.5-fpm, /bin/systemctl restart taida-web
```

## 5. Deploy

CI (`.github/workflows/deploy.yml`) chạy test → build Nuxt → rsync → gọi hai
script kích hoạt. Cần khai báo trên GitHub:

| Loại | Tên | Ví dụ |
|---|---|---|
| Secret | `DEPLOY_HOST` / `DEPLOY_USER` / `DEPLOY_SSH_KEY` | `taida` |
| Secret | `DEPLOY_KNOWN_HOSTS` | kết quả `ssh-keyscan taida.vn` |
| Variable | `NUXT_PUBLIC_SITE_URL` | `https://www.taida.vn` |
| Variable | `NUXT_PUBLIC_API_BASE` | `https://api.taida.vn` |

Deploy tay khi cần:

```bash
rsync -az --exclude vendor --exclude storage --exclude .env \
    be/ taida@taida.vn:/var/www/taida/api/releases/$(date +%Y%m%d-%H%M%S)/
ssh taida@taida.vn /var/www/taida/deploy/scripts/deploy-api.sh <tên-release>
ssh taida@taida.vn /var/www/taida/deploy/scripts/deploy-web.sh <tên-release>
```

Rollback là chạy lại chính script đó với tên release cũ (5 release gần nhất
luôn được giữ):

```bash
ls /var/www/taida/web/releases
ssh taida@taida.vn /var/www/taida/deploy/scripts/deploy-web.sh 20260805-120000
```

**Nuxt phải build trên Linux x64.** `@nuxt/image` đóng gói binary `sharp` theo
kiến trúc máy build; build trên máy Mac rồi copy lên server sẽ chết lúc xử lý ảnh.
CI đã chạy `ubuntu-latest` nên đúng sẵn — chỉ lưu ý khi build tay.

`NUXT_PUBLIC_API_BASE` **phải có mặt lúc build**, không chỉ lúc chạy: nó sinh ra
danh sách domain mà `@nuxt/image` được phép xử lý. Thiếu biến này site vẫn chạy
bình thường, chỉ là mọi ảnh do admin tải lên — kể cả logo ở đầu mỗi trang — được
trả nguyên kích thước gốc từ domain API thay vì bản WebP đã thu nhỏ.

Lần deploy đầu tiên, chạy thêm seeder để có tài khoản admin và dữ liệu mẫu:

```bash
ssh taida@taida.vn 'php /var/www/taida/api/current/artisan db:seed --force'
```

## 6. Backup

`taida-backup.timer` chạy 03:15 mỗi ngày: dump database + nén thư mục media,
giữ 14 ngày.

```bash
systemctl list-timers taida-backup      # lần chạy kế tiếp
journalctl -u taida-backup -n 50        # log lần gần nhất
```

Backup nằm cùng máy với thứ nó bảo vệ thì không phải backup. Đặt
`TAIDA_BACKUP_REMOTE` trong `/etc/systemd/system/taida-backup.service`
(`Environment=TAIDA_BACKUP_REMOTE=user@nas:/taida/`) để đẩy sang nơi khác.

Phục hồi — **hãy thử ít nhất một lần trên staging trước khi cần đến nó**:

```bash
/var/www/taida/deploy/scripts/restore.sh \
    /var/www/taida/backups/db-20260806-031500.sql.gz \
    /var/www/taida/backups/media-20260806-031500.tar.gz
```

## 7. Kiểm tra sau khi deploy

```bash
curl -sI https://www.taida.vn/ | grep -i content-encoding      # phải là gzip hoặc br
curl -s https://www.taida.vn/sitemap.xml | head -5
curl -s https://api.taida.vn/up
systemctl status taida-web
journalctl -u taida-web -f
```

`content-encoding` là thứ dễ mất nhất và không có gì khác báo động: HTML SSR
không nén là 41 KB thay vì 8 KB, đủ để rơi khoảng 10 điểm Lighthouse. CI có
kiểm tra header này ở bước smoke test.

## 8. Những chỗ hay hỏng

| Triệu chứng | Nguyên nhân thường gặp |
|---|---|
| Đăng nhập admin OK, request sau 401 | `SESSION_DOMAIN` / `SANCTUM_STATEFUL_DOMAINS` / `TRUSTED_PROXIES` không khớp |
| API trả 419 | thiếu `GET /sanctum/csrf-cookie` trước POST, hoặc `FRONTEND_URLS` sai |
| Sửa nội dung không lên site | cache SWR của Nitro (tối đa 1 giờ) — `systemctl restart taida-web` để xoá ngay |
| Ảnh upload 404 | chưa có symlink `public/storage` → `php artisan storage:link` |
| Site trắng sau deploy | `journalctl -u taida-web -n 100`; `deploy-web.sh` tự rollback nếu không lên trong 30s |
| 502 từ nginx | Nitro chết — `systemctl status taida-web` |
