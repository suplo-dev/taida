<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Kiểm tra mỗi phút xem nội dung có thay đổi cần sinh lại site tĩnh không.
 * Lệnh tự quyết định có chạy thật hay không, nên gọi thường xuyên rất rẻ.
 *
 * `withoutOverlapping` phòng trường hợp một lần gọi API GitHub bị treo: thiếu
 * nó thì mỗi phút lại chồng thêm một tiến trình nữa.
 */
Schedule::command('site:publish')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
