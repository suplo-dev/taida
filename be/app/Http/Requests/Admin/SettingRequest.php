<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['settings' => ['required', 'array']];

        foreach (Setting::MEDIA_KEYS as $key) {
            $rules["settings.$key"] = ['nullable', 'integer', 'exists:media,id'];
        }

        // Trình duyệt nạp URL này thẳng vào thẻ <video> trên mọi trang chủ, nên nó
        // phải là địa chỉ thật và phải là https: một URL http sẽ bị chặn dưới dạng
        // mixed content và hero mất nền mà không báo gì.
        foreach (Setting::URL_KEYS as $key) {
            $rules["settings.$key"] = ['nullable', 'string', 'max:2048', 'url:https'];
        }

        // Địa chỉ trang mạng xã hội phải là URL đầy đủ: một ô ghi "facebook.com/taida"
        // rơi thẳng vào href và trình duyệt hiểu nó là đường dẫn tương đối của
        // chính site, nên nút ở chân trang dẫn về một trang 404 của mình.
        $rules['settings.social'] = ['sometimes', 'array'];

        foreach (Setting::SOCIAL_NETWORKS as $network) {
            $rules["settings.social.$network"] = ['sometimes', 'array'];
            $rules["settings.social.$network.url"] = ['nullable', 'string', 'max:2048', 'url'];
            $rules["settings.social.$network.enabled"] = ['boolean'];
        }

        $rules['settings.contactQr'] = ['sometimes', 'array', 'max:12'];
        $rules['settings.contactQr.*.label'] = ['required', 'string', 'max:60'];
        $rules['settings.contactQr.*.media'] = ['required', 'integer', 'exists:media,id'];
        $rules['settings.contactQr.*.enabled'] = ['boolean'];

        return $rules;
    }

    /**
     * Lỗi validation của trang này hiện lên dưới dạng một dòng toast, không gắn
     * vào ô nhập, nên câu chữ mặc định của Laravel ("The settings.hero video
     * field must be a valid URL") vừa là tiếng Anh vừa nhắc tên khoá nội bộ.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.heroVideo.url' => 'Địa chỉ video nền phải bắt đầu bằng https:// và trỏ tới một file video (ví dụ https://cdn.example.com/hero.mp4).',
            'settings.heroVideo.max' => 'Địa chỉ video nền quá dài (tối đa 2048 ký tự).',
            'settings.heroImage.exists' => 'Ảnh nền vừa chọn không còn trong thư viện ảnh.',
            'settings.logo.exists' => 'Logo vừa chọn không còn trong thư viện ảnh.',
            'settings.social.*.url.url' => 'Địa chỉ mạng xã hội phải là đường dẫn đầy đủ, bắt đầu bằng https:// (ví dụ https://www.tiktok.com/@taida).',
            'settings.contactQr.*.label.required' => 'Mỗi mã QR cần một tên gọi (ví dụ Zalo, WeChat).',
            'settings.contactQr.*.media.required' => 'Mỗi mã QR cần một ảnh.',
            'settings.contactQr.*.media.exists' => 'Ảnh mã QR vừa chọn không còn trong thư viện ảnh.',
        ];
    }

    /**
     * The read endpoint hands media settings back as full objects. Accept that
     * shape here too, so a client can round-trip what it was given instead of
     * having to remember which keys need unpacking first.
     */
    protected function prepareForValidation(): void
    {
        $settings = (array) $this->input('settings', []);

        foreach (Setting::MEDIA_KEYS as $key) {
            if (is_array($settings[$key] ?? null)) {
                $settings[$key] = $settings[$key]['id'] ?? null;
            }
        }

        // Ô trống trong form gửi lên chuỗi rỗng, mà `url` không chấp nhận chuỗi
        // rỗng — biên tập viên xoá địa chỉ video sẽ nhận lỗi validation thay vì
        // xoá được nó.
        foreach (Setting::URL_KEYS as $key) {
            if (($settings[$key] ?? null) === '') {
                $settings[$key] = null;
            }
        }

        // Bộ chọn ảnh gửi lại nguyên bản ghi media nó vừa nhận, giống hệt logo.
        if (is_array($settings['contactQr'] ?? null)) {
            $settings['contactQr'] = array_values(array_map(function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                if (is_array($item['media'] ?? null)) {
                    $item['media'] = $item['media']['id'] ?? null;
                }

                return $item;
            }, $settings['contactQr']));
        }

        $this->merge(['settings' => $settings]);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $settings = (array) $this->input('settings', []);

        // Quy về đúng một dạng trước khi ghi: hàng cũ của `social` là chuỗi địa
        // chỉ trần, và cả hai khoá đều nhận thêm phím lạ từ client nếu không cắt
        // ở đây. Đọc lên đằng nào cũng chuẩn hoá, nhưng lưu bản chuẩn thì hàng
        // trong database còn đọc được bằng mắt.
        if (array_key_exists('social', $settings)) {
            // Trộn lên trên hàng đang lưu chứ không dựng lại từ đầu: endpoint này
            // nhận cấu hình từng phần — mọi khoá khác đều thế — nên một lượt PUT
            // chỉ gửi TikTok mà lại xoá sạch địa chỉ Facebook thì quá bất ngờ.
            $stored = Setting::socialLinks(['social' => Setting::query()->find('social')?->value]);

            $settings['social'] = Setting::socialLinks([
                'social' => array_replace($stored, array_filter((array) $settings['social'], 'is_array')),
            ]);
        }

        if (array_key_exists('contactQr', $settings)) {
            $settings['contactQr'] = array_values(array_map(fn (array $item): array => [
                'label' => trim((string) $item['label']),
                'media' => (int) $item['media'],
                'enabled' => (bool) ($item['enabled'] ?? false),
            ], (array) $settings['contactQr']));
        }

        return $settings;
    }
}
