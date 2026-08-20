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

        $this->merge(['settings' => $settings]);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return (array) $this->input('settings', []);
    }
}
