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

        return $rules;
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
