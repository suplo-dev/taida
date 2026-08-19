<?php

/**
 * Chạy các lệnh artisan sau khi bản build của backend đã được giải nén.
 *
 * Shared hosting không mở SSH, nên không có cách nào khác để gọi `php artisan
 * migrate` trên máy chủ. Script này là cửa đó: nạp chính Laravel vừa upload rồi
 * gọi lệnh qua API console của framework.
 *
 * Vì sao tách khỏi `unpack.php` thành một lượt gọi HTTP riêng: unpack ghi đè cả
 * `vendor/`. Bootstrap Laravel ngay trong cùng request đó là nạp autoloader mới
 * trong khi PHP đã giữ sẵn một phần file cũ trong opcache — hỏng thất thường và
 * rất khó chẩn đoán. Gọi hai lượt thì lượt sau chạy hoàn toàn trên code mới.
 *
 * File này nằm trong `public/` (phần duy nhất gọi được qua HTTP) nhưng gốc ứng
 * dụng là thư mục cha. Nó tự xoá mình ở dòng cuối, và token được sinh ngẫu nhiên
 * mỗi lần deploy nên không dùng lại được.
 */

// Workflow thay chuỗi này bằng token thật lúc upload. Còn nguyên nghĩa là file
// bị lộ ra ngoài quy trình deploy, khi đó không được phép chạy bất cứ thứ gì.
const TOKEN = '__DEPLOY_TOKEN__';

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

$given = $_POST['token'] ?? $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';

// 404 chứ không phải 403: không xác nhận là script có tồn tại.
if (!is_string($given) || TOKEN === '__DEPLOY' . '_TOKEN__' || !hash_equals(TOKEN, $given)) {
    http_response_code(404);
    exit;
}

@set_time_limit(0);
@ignore_user_abort(true);

$base = dirname(__DIR__);

// Xoá mình TRƯỚC khi chạy bất cứ thứ gì: từ đây trở đi có thể fatal error, hết
// bộ nhớ hay bị hosting cắt giữa chừng, và khi đó một script nhận lệnh qua HTTP
// nằm lại trong document root là thứ tệ nhất có thể để lại. PHP đã đọc xong file
// nên xoá file đang chạy là hợp lệ trên Linux.
@unlink(__FILE__);

if (!is_file($base . '/vendor/autoload.php')) {
    fail('không thấy vendor/autoload.php — bước giải nén chưa chạy xong?');
}
if (!is_file($base . '/.env')) {
    fail('không thấy .env — xem deploy/shared-hosting/README.md, file này phải tạo tay một lần');
}

/**
 * Dọn cache trong `bootstrap/cache` TRƯỚC KHI nạp Laravel.
 *
 * Đây là thứ duy nhất của bản cũ còn sống sót qua một lần deploy: `PRUNE=0` cho backend
 * (để không xoá `.env` và `storage/`) nên unpack không dọn gì, mà zip lại cố ý không
 * chứa `bootstrap/cache/*` để khỏi mang cache của máy build lên hosting. Kết quả là
 * manifest cũ nằm lại vĩnh viễn.
 *
 * Nó hỏng theo kiểu tệ nhất. `packages.php` là kết quả package discovery của lần cài
 * TRƯỚC — nếu lần đó cài kèm dev dependency thì nó liệt kê cả `BoostServiceProvider`,
 * `PailServiceProvider`… Bản phát hành cài `--no-dev` không có những class đó, và
 * Laravel chết ngay lúc bootstrap với "Class ... not found", trước cả khi chạm tới lệnh
 * artisan nào — nên không lệnh `*:clear` nào cứu được.
 *
 * Xoá được vô tư: mọi file ở đây đều tái sinh được. Thiếu chúng thì Laravel tự dò lại
 * từ `vendor/composer/installed.json` ngay trong request này.
 */
foreach (glob($base . '/bootstrap/cache/*.php') ?: [] as $stale) {
    @unlink($stale);
}

/**
 * Bọc cả phần bootstrap chứ không chỉ các lệnh artisan bên dưới.
 *
 * Không bọc thì một exception ở đây rơi vào tay error handler của Laravel/Symfony và
 * trả về trang HTML "Oops! An Error Occurred" — với APP_DEBUG=false nó KHÔNG nói lỗi
 * gì, nên phía CI chỉ thấy một khối HTML và không cách nào biết chuyện gì xảy ra.
 *
 * Những thứ hay hỏng đúng ở đoạn này đều là chuyện của môi trường chứ không phải code:
 * thiếu thư mục `bootstrap/cache`, `storage/` không ghi được, APP_KEY sai định dạng.
 * Chúng chỉ xuất hiện trên hosting, nên thông điệp lỗi là thứ duy nhất chẩn đoán được.
 *
 * Lộ thông điệp lỗi ở đây chấp nhận được: endpoint đã qua cửa token dùng một lần, và
 * chính nó đã tự xoá mình ở trên.
 */
try {
    require $base . '/vendor/autoload.php';

    /** @var \Illuminate\Foundation\Application $app */
    $app = require $base . '/bootstrap/app.php';

    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
} catch (Throwable $e) {
    fail('bootstrap thất bại — ' . get_class($e) . ': ' . $e->getMessage());
}

/**
 * Thứ tự có chủ ý, và cố ý KHÔNG dùng `optimize:clear`.
 *
 * `optimize:clear` gộp cả `cache:clear` vào, mà cache store của dự án này là DATABASE.
 * Ở lần deploy đầu tiên bảng `cache` chưa tồn tại — migration chưa chạy — nên nó ném
 * QueryException và làm hỏng cả lượt deploy, đúng lúc không có gì để dọn cả. Tách ra
 * thì mỗi lệnh chịu trách nhiệm một thứ và hỏng đúng chỗ đáng hỏng:
 *
 *  - Bốn lệnh `*:clear` đầu chỉ đụng file trong `bootstrap/cache`, không chạm database.
 *    Chúng phải chạy TRƯỚC `migrate`: cache config của BẢN CŨ còn nằm đó, và migrate
 *    đọc nhầm nó nghĩa là chạy trên thông số database cũ.
 *  - `migrate` trước khi hâm cache. Migration cộng thêm (thêm bảng/cột) chạy được trên
 *    database đang phục vụ bản cũ mà không làm gián đoạn.
 *  - `cache:clear` SAU migrate, lúc bảng `cache` chắc chắn đã có. Vẫn để best-effort:
 *    cache ứng dụng cũ còn sót chỉ là dữ liệu cũ, không đáng để chặn cả bản phát hành.
 *  - `config:cache` / `route:cache` / `event:cache` sau cùng — thiếu chúng thì API vẫn
 *    chạy, chỉ chậm hơn đáng kể vì mỗi request phải đọc lại toàn bộ config.
 *
 * `storage:link` để cuối và được phép hỏng: nhiều shared hosting chặn symlink. Mất nó
 * chỉ là ảnh upload không truy cập được qua URL, không phải API chết.
 *
 * Cột thứ ba: lệnh có BẮT BUỘC thành công hay không.
 */
$steps = [
    ['config:clear', [], true],
    ['route:clear', [], true],
    ['view:clear', [], true],
    ['event:clear', [], true],
    ['migrate', ['--force' => true], true],
    ['cache:clear', [], false],
    ['config:cache', [], true],
    ['route:cache', [], true],
    ['event:cache', [], true],
    ['storage:link', [], false],
];

$failed = [];

foreach ($steps as [$command, $arguments, $required]) {
    try {
        $status = $kernel->call($command, $arguments);
        $output = trim($kernel->output());
    } catch (Throwable $e) {
        $status = 1;
        $output = get_class($e) . ': ' . $e->getMessage();
    }

    printf("--- %s (exit %d)\n%s\n", $command, $status, $output === '' ? '(không có output)' : $output);

    if ($status !== 0 && $required) {
        $failed[] = $command;
    }
}

if ($failed !== []) {
    fail('lệnh thất bại: ' . implode(', ', $failed));
}

echo "OK\n";

function fail(string $message): void
{
    http_response_code(500);
    echo 'ERROR: ' . $message . "\n";
    exit(1);
}
