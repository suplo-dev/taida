<?php

namespace App\Console\Commands;

use App\Support\SitePublisher;
use Illuminate\Console\Command;

/**
 * Rebuilds the public site when the content behind it has changed.
 *
 * Run every minute by the scheduler; it decides for itself whether anything is
 * due, so running it often is cheap and running it late costs only a delay.
 */
class PublishSite extends Command
{
    protected $signature = 'site:publish
                            {--force : Xuất bản ngay, bỏ qua thời gian chờ và khoảng nghỉ}';

    protected $description = 'Sinh lại site tĩnh nếu nội dung đã thay đổi kể từ lần xuất bản trước';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! SitePublisher::isDue()) {
            $this->line($this->reasonForSkipping());

            return self::SUCCESS;
        }

        if (! SitePublisher::trigger()) {
            $this->error('Không kích hoạt được workflow — xem storage/logs/laravel.log.');

            return self::FAILURE;
        }

        $this->info('Đã yêu cầu GitHub Actions sinh lại site.');

        return self::SUCCESS;
    }

    /**
     * Says which of the three gates stopped it, so a "nothing happened" run is
     * diagnosable from the log without reading the code.
     */
    private function reasonForSkipping(): string
    {
        if (! config('publish.enabled')) {
            return 'Tự xuất bản đang tắt (PUBLISH_ENABLED=false).';
        }

        if (! SitePublisher::isStale() && ! SitePublisher::scheduledContentWentLive()) {
            return 'Không có thay đổi nào kể từ lần xuất bản trước.';
        }

        $lastRun = SitePublisher::lastPublishedAt();

        if ($lastRun !== null && $lastRun->diffInSeconds(now()) < config('publish.cooldown')) {
            return 'Vừa xuất bản xong, đang trong khoảng nghỉ.';
        }

        return 'Nội dung vẫn đang được sửa, chờ ngừng thay đổi.';
    }
}
