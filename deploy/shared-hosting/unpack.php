<?php

/**
 * Giải nén bản build ngay trên hosting, thay cho việc đẩy từng file qua FTP.
 *
 * Vì sao cần: hosting đặt ở Việt Nam còn runner của GitHub Actions ở Mỹ, RTT
 * khoảng 250ms. Site tĩnh có 813 file (mỗi trang sinh thêm bản `.br` và `.gz`
 * nên số file gấp ba), trung bình 12 KB — nhỏ tới mức thời gian truyền không
 * đáng kể, toàn bộ chi phí nằm ở round-trip: mỗi file là một lượt CWD, DELE,
 * PASV, mở kết nối data, bắt tay TLS riêng, STOR, đóng. Đo trên run
 * 32129297097: 8 phút 9 giây cho 10 MB, tức 20 KB/s. Chạy 5 luồng song song
 * vẫn là 3 giây cho một file 12 KB.
 *
 * Cách chữa duy nhất có hiệu quả là bỏ hẳn 813 lượt round-trip: nén cả site
 * thành một file zip 5 MB, đẩy đúng một lần, rồi giải nén tại chỗ. Phần chậm
 * biến mất chứ không được chia nhỏ.
 *
 * File này KHÔNG nằm sẵn trên hosting. Workflow upload nó cùng bản build, gọi
 * một lần, và nó tự xoá mình ở dòng cuối. Token được sinh ngẫu nhiên mỗi lần
 * chạy và ghi đè vào hằng số dưới đây lúc upload, nên không có trong git và
 * cũng không dùng lại được cho lần sau.
 *
 * Bước dọn file cũ thay cho `mirror --delete`: thứ gì có trên hosting mà không
 * có trong zip thì bị xoá. Hai thư mục được giữ lại vì do hosting quản chứ
 * không do build sinh ra — xoá `.well-known/` là mất gia hạn SSL.
 */

// Workflow thay chuỗi này bằng token thật lúc upload. Còn nguyên nghĩa là file
// bị lộ ra ngoài quy trình deploy, khi đó không được phép chạy bất cứ thứ gì.
const TOKEN = '__DEPLOY_TOKEN__';

const ARCHIVE = '.deploy-site.zip';
const TMPDIR  = '.deploy-tmp';
const KEEP    = ['.well-known', 'cgi-bin'];

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

$given = $_POST['token'] ?? $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';

// 404 chứ không phải 403: không xác nhận là script có tồn tại. Cửa sổ tồn tại
// của nó chỉ vài giây, nhưng địa chỉ thì đoán được.
if (!is_string($given) || TOKEN === '__DEPLOY' . '_TOKEN__' || !hash_equals(TOKEN, $given)) {
    http_response_code(404);
    exit;
}

@set_time_limit(0);
@ignore_user_abort(true);

$root    = __DIR__;
$archive = $root . '/' . ARCHIVE;
$tmp     = $root . '/' . TMPDIR;
$self    = basename(__FILE__);

if (!is_file($archive)) {
    fail('không thấy ' . ARCHIVE . ' — bước upload chưa chạy xong?');
}

rrmdir($tmp);
if (!@mkdir($tmp, 0755) && !is_dir($tmp)) {
    fail('không tạo được thư mục tạm ' . TMPDIR);
}

extractTo($archive, $tmp);

// Danh sách này vừa là thứ cần chép vào, vừa là thước đo để biết cái gì trên
// hosting đã thừa. Lấy trước khi chép đi vì sau đó $tmp rỗng.
$want = listFiles($tmp);
if ($want === []) {
    rrmdir($tmp);
    fail('archive rỗng — không ghi đè site bằng nội dung trống');
}
$wanted = array_fill_keys($want, true);

foreach ($want as $rel) {
    $dst = $root . '/' . $rel;
    $dir = dirname($dst);

    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        fail('không tạo được thư mục ' . dirname($rel));
    }

    // rename() cùng filesystem là thao tác nguyên tử: người đang xem site thấy
    // bản cũ hoặc bản mới, không bao giờ thấy file viết dở.
    if (!@rename($tmp . '/' . $rel, $dst) && !@copy($tmp . '/' . $rel, $dst)) {
        fail('không ghi được ' . $rel);
    }

    // Quyền do umask của PHP quyết định, mà umask trên shared hosting thì không
    // ai hứa gì. Đặt tường minh, nếu không một hosting đặt umask 077 sẽ làm cả
    // site trả 403 — trong khi deploy vẫn báo thành công.
    @chmod($dst, 0644);
    @chmod($dir, 0755);
}

$removed = 0;
foreach (listFiles($root, '', array_merge(KEEP, [TMPDIR])) as $rel) {
    if (isset($wanted[$rel]) || $rel === ARCHIVE || $rel === $self) {
        continue;
    }
    if (@unlink($root . '/' . $rel)) {
        $removed++;
    }
}

// Thư mục rỗng còn lại sau khi xoá: rmdir bỏ qua thư mục còn nội dung, nên chỉ
// cần đi từ sâu ra nông một lượt.
$dirs = listDirs($root, '', array_merge(KEEP, [TMPDIR]));
usort($dirs, fn($a, $b) => substr_count($b, '/') <=> substr_count($a, '/'));
foreach ($dirs as $rel) {
    @rmdir($root . '/' . $rel);
}

rrmdir($tmp);
@unlink($archive);

printf("OK %d file, xoá %d file cũ\n", count($want), $removed);

// Dòng cuối cùng, sau khi mọi thứ đã xong: script chỉ sống trong đúng một lần
// deploy. Xoá file đang chạy là hợp lệ trên Linux — PHP đã đọc xong nó rồi.
@unlink(__FILE__);

function fail(string $message): void
{
    http_response_code(500);
    echo 'ERROR: ' . $message . "\n";
    exit(1);
}

function extractTo(string $archive, string $dest): void
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            fail('không mở được archive');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !safeName($name)) {
                $zip->close();
                fail('đường dẫn không hợp lệ trong archive: ' . var_export($name, true));
            }
        }
        if (!$zip->extractTo($dest)) {
            $zip->close();
            fail('giải nén thất bại (hết dung lượng?)');
        }
        $zip->close();

        return;
    }

    // PharData đọc được zip và nằm trong core PHP, nên là đường lui khi hosting
    // không biên dịch ext-zip.
    if (class_exists('PharData')) {
        try {
            (new PharData($archive))->extractTo($dest, null, true);
        } catch (Throwable $e) {
            fail('giải nén thất bại: ' . $e->getMessage());
        }

        return;
    }

    fail('hosting không có cả ZipArchive lẫn PharData');
}

/** Chặn `../`, đường dẫn tuyệt đối và null byte trước khi giải nén. */
function safeName(string $name): bool
{
    if ($name === '' || strpos($name, "\0") !== false) {
        return false;
    }
    if ($name[0] === '/' || strpos($name, '\\') !== false) {
        return false;
    }

    return !preg_match('#(^|/)\.\.(/|$)#', $name);
}

/** Đường dẫn tương đối của mọi file dưới $root; $skipTop chỉ xét ở tầng gốc. */
function listFiles(string $root, string $rel = '', array $skipTop = []): array
{
    $out = [];
    $dir = $rel === '' ? $root : $root . '/' . $rel;
    $handle = @opendir($dir);
    if ($handle === false) {
        return $out;
    }

    while (($name = readdir($handle)) !== false) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if ($rel === '' && in_array($name, $skipTop, true)) {
            continue;
        }
        $child = $rel === '' ? $name : $rel . '/' . $name;
        $path = $root . '/' . $child;

        if (is_dir($path) && !is_link($path)) {
            foreach (listFiles($root, $child, $skipTop) as $nested) {
                $out[] = $nested;
            }
        } else {
            $out[] = $child;
        }
    }
    closedir($handle);

    return $out;
}

/** Như listFiles nhưng trả về thư mục. */
function listDirs(string $root, string $rel = '', array $skipTop = []): array
{
    $out = [];
    $dir = $rel === '' ? $root : $root . '/' . $rel;
    $handle = @opendir($dir);
    if ($handle === false) {
        return $out;
    }

    while (($name = readdir($handle)) !== false) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if ($rel === '' && in_array($name, $skipTop, true)) {
            continue;
        }
        $child = $rel === '' ? $name : $rel . '/' . $name;
        $path = $root . '/' . $child;

        if (is_dir($path) && !is_link($path)) {
            $out[] = $child;
            foreach (listDirs($root, $child, $skipTop) as $nested) {
                $out[] = $nested;
            }
        }
    }
    closedir($handle);

    return $out;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (is_dir($path) && !is_link($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}
