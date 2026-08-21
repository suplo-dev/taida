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

## Nội dung sửa xong lên site lúc nào

HTML được sinh lúc build, nên **lưu trong CMS không làm trang công khai đổi
ngay**. Cái đổi ngay là database, API, và những gì hiển thị trong chính CMS.

Việc sinh lại đã được tự động (mục 7): sửa xong, khoảng **90 giây sau** hệ
thống tự gọi GitHub Actions, thêm **2–3 phút** build là site đổi. Biên tập viên
không phải nhớ hay bấm gì.

Sửa liên tiếp nhiều thứ chỉ tạo **một** lần build — mỗi lần lưu lại đặt lại
đồng hồ chờ, nên hệ thống đợi tới khi ngừng sửa mới bắt đầu.

Nếu ngần đó vẫn là quá lâu — ví dụ khách muốn sửa chữ là thấy ngay — thì phải bỏ
SSG: đưa Nuxt lên chỗ chạy được Node, hoặc chuyển sang VPS theo
`deploy/README.md`.

## 1. Yêu cầu hosting

| | |
|---|---|
| PHP | **8.5** (`composer.json` chỉ yêu cầu `^8.3`, nhưng workflow cài `vendor/` bằng 8.5 rồi upload nguyên khối — hosting chạy bản khác là chạy trên cây thư viện giải cho bản khác. Hosting của bạn là 8.3/8.4 thì đổi `PHP_VERSION` trong cả `ci.yml` lẫn `deploy.yml`) |
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

Lần đầu, tạo file cấu hình cho bản production:

```bash
cd fe
cp .env.production.example .env.production   # sửa lại tên miền nếu khác
pnpm install --frozen-lockfile
```

Từ đó về sau chỉ cần:

```bash
pnpm generate
```

`pnpm generate` đọc `.env.production`, còn `pnpm dev` đọc `.env` — nên chạy dev
không bao giờ vô tình trỏ vào API production, và ngược lại.

Hai biến trong đó bắt buộc phải có **lúc build**: chúng được nướng vào từng file
HTML và quyết định danh sách domain `@nuxt/image` được phép xử lý. Sai thì site
vẫn build xong, vẫn chạy, chỉ là gọi nhầm API hoặc khai báo sai địa chỉ cho
Google — không có gì báo lỗi.

**API phải chạy được và có dữ liệu trong lúc build** — Nuxt gọi vào đó để dựng
từng trang. Build lần đầu thì trỏ vào API đã lên hosting.

Kết quả nằm ở `fe/.output/public/` (~13 MB): khoảng 130 trang HTML, ảnh chia sẻ
mạng xã hội, sitemap, và `.htaccess` — tất cả đều là file tĩnh, không cần tiến
trình nào chạy nền.

Site có **ba ngôn ngữ**: tiếng Việt ở gốc (`/`), tiếng Anh ở `/en`, tiếng Trung
giản thể ở `/zh`. Mỗi bản ghi trong CMS có một tab cho mỗi ngôn ngữ; tab nào bỏ
trống thì trang công khai hiển thị bản tiếng Việt và **không** vào sitemap (nó
mang `noindex` cho tới khi có bản dịch thật). Nhờ vậy mở thêm ngôn ngữ không phải
đợi dịch xong toàn bộ nội dung.

## 3. Đưa Laravel lên (lần đầu)

> Từ lần thứ hai trở đi backend **tự lên theo workflow** — xem [mục 6b](#6b-deploy-backend-tự-động).
> Mục này chỉ còn là những thứ workflow không làm hộ được: tạo subdomain, tạo
> database, và viết file `.env` trên hosting.

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

Môi trường staging (ví dụ `taida.crownsoftware.site` / `taida-api.crownsoftware.site`)
dùng cùng quy tắc — xem `deploy/env/api.env.staging.example`. Nếu `SESSION_DOMAIN`
vẫn là `localhost` thì cookie CSRF/session được set với `domain=localhost` và trình
duyệt **không lưu** — login sẽ báo *"This request did not arrive from a stateful
frontend"*. Sau khi sửa `.env` trên server, chạy `php artisan config:cache`.

`TRUSTED_PROXIES=*` ở đây là chấp nhận được vì chỉ hạ tầng của nhà cung cấp mới
tới được PHP; trên VPS tự quản thì nên liệt kê IP cụ thể.

**`APP_URL` quan trọng hơn vẻ ngoài của nó.** Mọi đường dẫn ảnh do admin tải lên
đều được Laravel dựng từ biến này. Để nguyên `http://localhost` thì API vẫn chạy
bình thường, admin vẫn thấy ảnh, nhưng site sinh ra sẽ nhúng
`http://localhost/storage/...` vào từng trang — logo và ảnh bài viết mất sạch với
người dùng thật, và không có lỗi nào báo.

Nó cũng phải **trùng khít** với `NUXT_PUBLIC_API_BASE` bên frontend. Lệch nhau
thì `@nuxt/image` không nhận ra domain, bỏ qua tối ưu và trả ảnh gốc nguyên kích
thước — site vẫn hiển thị đúng nên rất dễ bỏ sót.

## 4. Đưa site tĩnh lên

Copy **toàn bộ nội dung bên trong** `fe/.output/public/` vào `public_html/`
(nội dung bên trong, không phải cả thư mục).

Có SSH thì một lệnh là xong; không có thì upload bằng FTP (mục 6 tự động hoá
đúng việc này):

```bash
rsync -az --delete fe/.output/public/ user@host:~/domains/taida/fe/
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

Workflow upload bằng **FTPS**, không phải SSH — shared hosting thường không mở
SSH, còn FTP thì gói nào cũng có. Cần khai báo trước trên GitHub, trong
Settings → Environments → **production** (không phải mục secrets chung của repo):

| Loại | Tên | Ví dụ |
|---|---|---|
| Secret | `DEPLOY_FTP_HOST` | `ftp.taida.vn` hoặc IP máy chủ |
| Secret | `DEPLOY_FTP_USER` | tên tài khoản FTP trong cPanel |
| Secret | `DEPLOY_FTP_PASSWORD` | mật khẩu tài khoản đó |
| Variable | `DEPLOY_SITE_PATH` | đường dẫn **theo góc nhìn của FTP** tới thư mục chứa site tĩnh, không có `/` ở cuối |
| Variable | `DEPLOY_API_PATH` | đường dẫn FTP tới **gốc ứng dụng Laravel** — thư mục CHA của `public/`, ví dụ `/api` |
| Variable | `NUXT_PUBLIC_SITE_URL` | `https://www.taida.vn` |
| Variable | `NUXT_PUBLIC_API_BASE` | `https://api.taida.vn` |

`DEPLOY_SITE_PATH` là chỗ dễ sai nhất: nó là đường dẫn mà **FTP** nhìn thấy, chứ
không phải đường dẫn tuyệt đối trên đĩa. Tài khoản FTP chính của cPanel thường
vào thẳng thư mục home, nên giá trị là `/public_html`. Tài khoản FTP phụ lại
thường bị khoá vào đúng một thư mục, khi đó lại là `/`. Cách chắc chắn nhất: mở
FileZilla, đăng nhập bằng đúng tài khoản đó, và chép lại đường dẫn hiện trên
thanh "Remote site" khi đang đứng ở thư mục chứa `index.html`.

Tạo tài khoản FTP riêng cho việc deploy (cPanel → FTP Accounts), khoá vào đúng
thư mục site và đặt mật khẩu riêng — mật khẩu này nằm trong GitHub, không nên là
mật khẩu đăng nhập cPanel.

**Site đi qua FTP dưới dạng một file, không phải 813 file.** `compressPublicAssets`
sinh thêm bản `.gz` và `.br` cho mỗi trang nên số file gấp ba, mà hosting ở Việt
Nam còn runner của GitHub ở Mỹ — RTT khoảng 250ms. Đẩy từng file thì toàn bộ thời
gian nằm ở round-trip chứ không phải băng thông: 10 MB mất 8 phút, tức 20 KB/s.

Nên workflow nén cả site thành một `.zip` 5 MB, đẩy một lần, rồi gọi
`deploy/shared-hosting/unpack.php` giải nén ngay trên hosting. Toàn bộ bước upload
còn khoảng 15 giây.

Script giải nén **không** nằm sẵn trên hosting: nó được upload cùng bản build, nhận
một token sinh ngẫu nhiên cho đúng lần chạy đó, và tự xoá mình ở dòng cuối. Smoke
test kiểm tra lại rằng nó đã biến mất.

Nó cũng là thứ thay cho `rsync --delete`: file có trên hosting mà không có trong
zip thì bị xoá, nên trang bị xoá trong CMS sẽ biến mất ở lần build kế tiếp.
`.well-known/` và `cgi-bin/` được giữ lại vì do hosting quản chứ không do build
sinh ra — xoá `.well-known/` là mất gia hạn SSL.

Nếu hosting không chạy được PHP ở thư mục đó (hoặc thiếu cả `ZipArchive` lẫn
`PharData`), workflow tự quay về đẩy từng file bằng `lftp mirror` và ghi một
warning. Site vẫn lên, chỉ chậm — không cần sửa gì để nó chạy được.

Làm tay thì vẫn là:

```bash
cd fe
pnpm generate
# rồi upload toàn bộ nội dung trong .output/public/ vào public_html bằng FTP
```

Nhớ bật hiển thị file ẩn trong FileZilla để `.htaccess` đi cùng — workflow thì
tự đẩy dotfile nên không dính lỗi này.

## 6b. Deploy backend tự động

Cùng một workflow **Publish site** cũng đẩy Laravel lên `api.taida.vn`, ngay
trước khi build site tĩnh — thứ tự đó là bắt buộc: mỗi trang HTML được dựng bằng
cách gọi vào API, nên một migration thêm trường mới phải lên **trước** bản HTML
lẽ ra phải hiển thị trường đó.

Chỉ muốn build lại nội dung, không đụng tới backend: bấm *Run workflow* rồi **bỏ
tick** ô "Đẩy cả backend". Push vào `main` thì luôn đẩy cả hai.

Quy trình một lần chạy:

```
composer install --no-dev   →  zip cả cây Laravel (trừ .env, storage/, tests/)
   →  thăm dò FTP↔HTTP      →  đẩy 1 file zip + 2 script PHP
   →  _deploy-unpack.php    →  giải nén ra thư mục cha của public/
   →  _deploy-artisan.php   →  clear cache → migrate → config/route/event:cache
   →  dọn script            →  smoke test
```

**Bước thăm dò** ghi một file text vài byte qua FTP rồi đọc lại nó qua HTTP.
Nghe thừa, nhưng nó là thứ duy nhất phân biệt được "upload thành công" với
"upload đúng chỗ": nếu `DEPLOY_API_PATH` sai, lftp vẫn báo xanh — nó chỉ lặng lẽ
tạo một cây thư mục mới ở nhánh khác — và triệu chứng duy nhất là một trang 404
của hosting ở tận bước sau. Sai thì workflow in luôn cây thư mục theo góc nhìn
FTP để đối chiếu.

**`_deploy-unpack.php` là cùng một file với FE**, chỉ khác hai hằng số được thay
lúc upload:

| | FE | Backend |
|---|---|---|
| `ROOT` | `.` (script nằm ngay trong document root) | `..` (script ở `public/`, zip giải nén ra thư mục cha) |
| `PRUNE` | `1` — trang cũ còn sót là trang vẫn được phục vụ | `0` — `.env` và `storage/` nằm ngoài zip, quét xoá là mất dữ liệu thật |

**`_deploy-artisan.php`** là cách duy nhất chạy được `php artisan migrate` khi
hosting không mở SSH: nó nạp chính Laravel vừa giải nén rồi gọi lệnh qua API
console của framework. Nó là **lượt HTTP thứ hai**, tách hẳn khỏi lượt giải nén —
gọi chung một request thì Laravel bootstrap bằng autoloader mới trong khi PHP còn
giữ file cũ trong opcache, hỏng thất thường và rất khó chẩn đoán.

Cả hai script tự xoá mình ngay sau khi chạy, nhận một token sinh ngẫu nhiên cho
đúng lần deploy đó, và smoke test kiểm tra lại rằng chúng đã biến mất.

**Những gì workflow KHÔNG làm hộ**, vì chúng chỉ đúng một lần:

- tạo subdomain `api.taida.vn` và trỏ document root vào `<DEPLOY_API_PATH>/public`;
- tạo database MySQL;
- viết `.env` trên hosting (mục 3). File này **cố ý nằm ngoài zip** — nó phải
  sống sót qua mọi lần deploy. Thiếu nó thì `_deploy-artisan.php` dừng ngay và
  nói rõ, chứ không chạy migrate trên cấu hình rỗng;
- `php artisan storage:link` — có chạy, nhưng được phép hỏng: nhiều shared
  hosting chặn symlink. Mất nó thì ảnh upload không truy cập được qua URL, API
  vẫn sống.

Đổi phiên bản PHP trên hosting thì sửa `PHP_VERSION` ở **cả hai** file
`.github/workflows/ci.yml` và `deploy.yml`: `vendor/` được cài trên runner rồi
upload nguyên khối, nên composer giải phụ thuộc theo phiên bản của runner chứ
không theo phiên bản máy chủ.

## 7. Tự sinh lại site sau khi biên tập

Để biên tập viên không phải nhớ bấm gì: mỗi lần lưu nội dung, Laravel đánh dấu
site đã cũ; một lệnh chạy theo lịch thấy dấu đó thì gọi GitHub Actions build lại.

**Tạo token.** GitHub → Settings → Developer settings → Personal access tokens →
Fine-grained. Chọn đúng repo này, cấp **một** quyền duy nhất: *Actions:
Read and write*. Không cần quyền nào khác — token này chỉ được phép bấm nút build.

**Điền vào `.env` của API:**

```env
PUBLISH_ENABLED=true
PUBLISH_GITHUB_TOKEN=github_pat_...
PUBLISH_GITHUB_REPOSITORY=chu-so-huu/ten-repo
```

**Thêm cron.** Trong cPanel → Cron Jobs, chạy mỗi phút:

```
* * * * * cd /home/taida/api && php artisan schedule:run >> /dev/null 2>&1
```

Đây là cron duy nhất Laravel cần; mọi tác vụ định kỳ sau này đều đi qua nó.

**Kiểm tra:**

```bash
php artisan site:publish            # cho biết vì sao chạy hoặc vì sao bỏ qua
php artisan site:publish --force    # build ngay, bỏ qua thời gian chờ
```

Sau khi lưu một nội dung trong CMS, chạy `php artisan site:publish` sẽ báo
*"Nội dung vẫn đang được sửa, chờ ngừng thay đổi."* — đúng như thiết kế. Đợi quá
90 giây rồi chạy lại thì nó gọi GitHub thật.

**Chỉnh nhịp độ.** Cả hai mốc thời gian đều nằm trong `.env`, đổi xong chỉ cần
`php artisan config:clear`, không phải sửa code hay deploy lại:

| Biến | Mặc định | Để làm gì |
|---|---|---|
| `PUBLISH_QUIET_PERIOD` | `90` | Giây chờ kể từ lần sửa cuối. Sửa 5 thứ liên tiếp chỉ thành 1 lần build |
| `PUBLISH_COOLDOWN` | `300` | Giây tối thiểu giữa hai lần build, tránh xếp hàng |
| `PUBLISH_ENABLED` | `false` | Ở máy dev để `false` cho khỏi build nhầm |

Muốn nội dung lên nhanh hơn thì hạ `PUBLISH_QUIET_PERIOD` — đổi lại mỗi lần lưu
dễ thành một build riêng. Đừng hạ `PUBLISH_COOLDOWN` xuống dưới thời gian một
lần build chạy xong (hiện khoảng 2–3 phút), nếu không build sau sẽ chồng lên
build trước và cái chạy sau chưa chắc là cái mới nhất.

Gọi GitHub thất bại thì site **vẫn được đánh dấu là cũ**, lần chạy sau thử lại —
thay đổi không bị bỏ quên. Lỗi ghi vào `storage/logs/laravel.log`.

**Không phải cú bấm Lưu nào cũng thành một lần build.** Backend chỉ đánh dấu
site đã cũ khi thao tác đó làm bản HTML tĩnh khác đi, và nó kiểm hai điều: bản
ghi **có đang hiện trên trang không**, và cột vừa đổi **có đi ra HTML không**.

| Thao tác | Có build? |
|---|---|
| Tạo/sửa/xoá một **bản nháp** (kể cả sửa phần dịch của nó) | không — chưa ai ngoài kia nhìn thấy |
| Sửa bản ghi **hẹn giờ** chưa tới lượt đăng | không — cùng lý do |
| Bấm Lưu mà không sửa gì | không — Eloquent không ghi gì xuống database |
| Tải ảnh lên nhưng chưa gắn vào đâu | không — build thuộc về lúc gắn ảnh, không phải lúc tải lên |
| Đổi mật khẩu, email, vai trò | không — không thứ nào ra trang công khai |
| **Đăng** một bản nháp, hoặc **gỡ** một mục đang đăng | có |
| Sửa nội dung/phần dịch của mục **đang đăng** | có |
| Sửa menu, chuyên mục, thẻ, cài đặt | có — chúng có mặt trên mọi trang |
| Gắn/bỏ thẻ cho bài, nối dịch vụ với lĩnh vực | có |
| Lưu lại đúng bộ thẻ cũ | không — không gắn thêm cũng không gỡ đi cái nào |
| Đổi **tên** người đã ký tên một bài đang đăng | có |
| Đổi alt/đường dẫn của ảnh đang là bìa một mục đang đăng | có |
| Bản ghi hẹn giờ **tới lượt đăng** | có — xem bên dưới |

Cache đọc thì **luôn** bị xoá ở mọi lần ghi, không kèm điều kiện: chính CMS cũng
đọc qua cache đó nên biên tập viên phải thấy ngay bản nháp của mình.

Bảng nối (thẻ của bài, lĩnh vực của dịch vụ) **không phát sự kiện model nào** —
ghi một dòng pivot thì cả hai đầu đều im lặng. Nên các chỗ đó không gọi `sync()`
thẳng mà đi qua `syncPublicRelation()`, để việc báo tin dính liền với thao tác
ghi và một chỗ gọi thêm sau này không thể quên.

**Bản ghi hẹn giờ.** Đặt bài đăng lúc 8h sáng mai thì đúng 8h sáng mai *không có
câu lệnh ghi database nào chạy* — không sự kiện model nào nổ. `site:publish` vì
vậy còn hỏi thẳng database mỗi phút: "có mục nào tới lượt đăng kể từ lần dựng
gần nhất không?". Vế này **không** phải chờ hết `PUBLISH_QUIET_PERIOD` — không có
ai đang gõ phím để mà chờ — nhưng vẫn qua `PUBLISH_COOLDOWN`. Lần chạy đầu tiên
chỉ *đặt mốc* rồi thôi: lúc đó chưa biết bản HTML đang chạy được dựng khi nào.

**Build sinh ra từ đây chỉ dựng lại frontend.** Lệnh gọi workflow kèm
`inputs: {api: false}`, đúng cái ô "Đẩy cả backend" bỏ tick khi chạy tay — nên
job **Backend** bị skip. Sửa nội dung là đổi dữ liệu, không đổi mã nguồn: không
có gì để cài lại `vendor/`, đẩy 28MB qua FTP rồi chạy migrate. Mã nguồn lên
hosting theo `push` vào `main` như thường lệ.

## Những chỗ hay hỏng

| Triệu chứng | Nguyên nhân |
|---|---|
| Site tải chậm, Lighthouse tụt ~10 điểm | thiếu `.htaccess` (file ẩn, FTP không hiện) |
| `/admin/...` 404 khi bấm F5 | như trên — fallback SPA nằm trong `.htaccess` |
| Đăng nhập admin OK, request sau 401 | `SESSION_DOMAIN` / `SANCTUM_STATEFUL_DOMAINS` / `FRONTEND_URLS` không khớp |
| Trình duyệt báo CORS | `FRONTEND_URLS` thiếu domain đang mở |
| Ảnh upload 404 | chưa chạy `php artisan storage:link` |
| Site thật hiện ảnh vỡ, xem mã nguồn thấy `localhost` | `APP_URL` trong `.env` của API chưa sửa thành tên miền thật |
| Ảnh tải chậm, không thấy `/_ipx/` trong mã nguồn | `APP_URL` và `NUXT_PUBLIC_API_BASE` không trùng nhau |
| Build ra nội dung cũ / nội dung dev | `.env.production` trỏ sai API, hoặc API production không truy cập được |
| Sửa nội dung không lên site sau vài phút | thiếu cron `schedule:run`, hoặc `PUBLISH_ENABLED=false`, hoặc token sai — chạy `php artisan site:publish` để nó nói lý do |
| `pnpm generate` báo 404 giữa chừng | API không truy cập được, hoặc có bản ghi thiếu bản dịch |
| Build lỗi lạ sau khi đổi cấu hình | xoá `fe/.nuxt` và `fe/.output` rồi build lại |
| Job **Backend** dừng ở "Ghi được file qua FTP nhưng không đọc lại được qua HTTP" | `DEPLOY_API_PATH` không phải thư mục mà `api.taida.vn` đang phục vụ. Workflow in sẵn cây thư mục theo góc nhìn FTP ngay dưới thông báo đó |
| Job **Backend** báo `_deploy-unpack.php` trả 404 dù upload xong | document root của API không phải `<DEPLOY_API_PATH>/public` |
| Job **Backend** báo "không thấy .env" | `.env` trên hosting bị xoá hoặc chưa từng tạo — nó cố ý nằm ngoài bản zip, xem mục 3 |
| Deploy backend xanh nhưng API trả 500 | `php artisan config:cache` đã chạy trên `.env` sai; sửa `.env` rồi chạy lại workflow |
