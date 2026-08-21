<?php

/*
|--------------------------------------------------------------------------
| Thông báo lỗi kiểm tra dữ liệu
|--------------------------------------------------------------------------
|
| APP_LOCALE của dự án là `vi` và Laravel chỉ đóng gói sẵn bản dịch tiếng Anh,
| nên nếu thiếu file này validator sẽ trả về nguyên khoá dịch (`validation.regex`)
| và admin nhìn thấy đúng chuỗi đó trên form.
|
| Laravel không viết hoa `:attribute`, vì vậy mọi câu ở đây đều mở đầu bằng một
| từ tiếng Việt ("Vui lòng nhập", "Định dạng", "Trường"…) thay vì đặt tên trường
| ngay đầu câu.
|
*/

return [
    'accepted' => 'Vui lòng chấp nhận :attribute.',
    'accepted_if' => 'Vui lòng chấp nhận :attribute khi :other là :value.',
    'active_url' => 'Trường :attribute không phải là một URL hợp lệ.',
    'after' => 'Trường :attribute phải là ngày sau :date.',
    'after_or_equal' => 'Trường :attribute phải là ngày sau hoặc bằng :date.',
    'alpha' => 'Trường :attribute chỉ được chứa chữ cái.',
    'alpha_dash' => 'Trường :attribute chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới.',
    'alpha_num' => 'Trường :attribute chỉ được chứa chữ cái và số.',
    'any_of' => 'Giá trị :attribute không hợp lệ.',
    'array' => 'Định dạng :attribute không hợp lệ.',
    'ascii' => 'Trường :attribute chỉ được chứa ký tự ASCII.',
    'base64' => 'Trường :attribute phải là chuỗi base64 hợp lệ.',
    'before' => 'Trường :attribute phải là ngày trước :date.',
    'before_or_equal' => 'Trường :attribute phải là ngày trước hoặc bằng :date.',
    'between' => [
        'array' => 'Trường :attribute phải có từ :min đến :max phần tử.',
        'file' => 'Dung lượng :attribute phải từ :min đến :max KB.',
        'numeric' => 'Giá trị :attribute phải nằm trong khoảng :min đến :max.',
        'string' => 'Độ dài :attribute phải từ :min đến :max ký tự.',
    ],
    'boolean' => 'Giá trị :attribute phải là true hoặc false.',
    'can' => 'Giá trị :attribute không được phép.',
    'confirmed' => 'Xác nhận :attribute không khớp.',
    'contains' => 'Trường :attribute còn thiếu một giá trị bắt buộc.',
    'current_password' => 'Mật khẩu không đúng.',
    'date' => 'Trường :attribute không phải là ngày hợp lệ.',
    'date_equals' => 'Trường :attribute phải là ngày bằng :date.',
    'date_format' => 'Trường :attribute không đúng định dạng :format.',
    'decimal' => 'Trường :attribute phải có :decimal chữ số thập phân.',
    'declined' => 'Trường :attribute phải bị từ chối.',
    'declined_if' => 'Trường :attribute phải bị từ chối khi :other là :value.',
    'different' => 'Trường :attribute và :other phải khác nhau.',
    'digits' => 'Trường :attribute phải có :digits chữ số.',
    'digits_between' => 'Trường :attribute phải có từ :min đến :max chữ số.',
    'dimensions' => 'Kích thước ảnh của :attribute không hợp lệ.',
    'distinct' => 'Trường :attribute có giá trị bị trùng lặp.',
    'doesnt_contain' => 'Trường :attribute không được chứa: :values.',
    'doesnt_end_with' => 'Trường :attribute không được kết thúc bằng một trong: :values.',
    'doesnt_start_with' => 'Trường :attribute không được bắt đầu bằng một trong: :values.',
    'email' => 'Trường :attribute phải là địa chỉ email hợp lệ.',
    'encoding' => 'Trường :attribute phải dùng bảng mã :encoding.',
    'ends_with' => 'Trường :attribute phải kết thúc bằng một trong: :values.',
    'enum' => 'Giá trị :attribute không hợp lệ.',
    'exists' => 'Giá trị :attribute không tồn tại.',
    'extensions' => 'Phần mở rộng của :attribute phải là: :values.',
    'file' => 'Trường :attribute phải là một tệp.',
    'filled' => 'Vui lòng nhập :attribute.',
    'gt' => [
        'array' => 'Trường :attribute phải có nhiều hơn :value phần tử.',
        'file' => 'Dung lượng :attribute phải lớn hơn :value KB.',
        'numeric' => 'Giá trị :attribute phải lớn hơn :value.',
        'string' => 'Độ dài :attribute phải nhiều hơn :value ký tự.',
    ],
    'gte' => [
        'array' => 'Trường :attribute phải có ít nhất :value phần tử.',
        'file' => 'Dung lượng :attribute phải từ :value KB trở lên.',
        'numeric' => 'Giá trị :attribute phải lớn hơn hoặc bằng :value.',
        'string' => 'Độ dài :attribute phải từ :value ký tự trở lên.',
    ],
    'hex_color' => 'Trường :attribute phải là mã màu hex hợp lệ.',
    'image' => 'Trường :attribute phải là một ảnh.',
    'in' => 'Giá trị :attribute không hợp lệ.',
    'in_array' => 'Giá trị :attribute không có trong :other.',
    'in_array_keys' => 'Trường :attribute phải chứa ít nhất một khoá trong: :values.',
    'integer' => 'Giá trị :attribute phải là số nguyên.',
    'ip' => 'Trường :attribute phải là địa chỉ IP hợp lệ.',
    'ipv4' => 'Trường :attribute phải là địa chỉ IPv4 hợp lệ.',
    'ipv6' => 'Trường :attribute phải là địa chỉ IPv6 hợp lệ.',
    'json' => 'Trường :attribute phải là chuỗi JSON hợp lệ.',
    'list' => 'Trường :attribute phải là một danh sách.',
    'lowercase' => 'Trường :attribute phải viết thường.',
    'lt' => [
        'array' => 'Trường :attribute phải có ít hơn :value phần tử.',
        'file' => 'Dung lượng :attribute phải nhỏ hơn :value KB.',
        'numeric' => 'Giá trị :attribute phải nhỏ hơn :value.',
        'string' => 'Độ dài :attribute phải ít hơn :value ký tự.',
    ],
    'lte' => [
        'array' => 'Trường :attribute không được có quá :value phần tử.',
        'file' => 'Dung lượng :attribute không được quá :value KB.',
        'numeric' => 'Giá trị :attribute không được lớn hơn :value.',
        'string' => 'Độ dài :attribute không được quá :value ký tự.',
    ],
    'mac_address' => 'Trường :attribute phải là địa chỉ MAC hợp lệ.',
    'max' => [
        'array' => 'Trường :attribute không được có quá :max phần tử.',
        'file' => 'Dung lượng :attribute không được quá :max KB.',
        'numeric' => 'Giá trị :attribute không được lớn hơn :max.',
        'string' => 'Độ dài :attribute không được quá :max ký tự.',
    ],
    'max_digits' => 'Trường :attribute không được có quá :max chữ số.',
    'mimes' => 'Định dạng :attribute phải là: :values.',
    'mimetypes' => 'Định dạng :attribute phải là: :values.',
    'min' => [
        'array' => 'Trường :attribute phải có ít nhất :min phần tử.',
        'file' => 'Dung lượng :attribute phải từ :min KB trở lên.',
        'numeric' => 'Giá trị :attribute phải lớn hơn hoặc bằng :min.',
        'string' => 'Độ dài :attribute phải từ :min ký tự trở lên.',
    ],
    'min_digits' => 'Trường :attribute phải có ít nhất :min chữ số.',
    'missing' => 'Trường :attribute không được gửi lên.',
    'missing_if' => 'Trường :attribute không được gửi lên khi :other là :value.',
    'missing_unless' => 'Trường :attribute không được gửi lên trừ khi :other là :value.',
    'missing_with' => 'Trường :attribute không được gửi lên khi đã có :values.',
    'missing_with_all' => 'Trường :attribute không được gửi lên khi đã có :values.',
    'multiple_of' => 'Giá trị :attribute phải là bội số của :value.',
    'not_in' => 'Giá trị :attribute không hợp lệ.',
    'not_regex' => 'Định dạng :attribute không hợp lệ.',
    'numeric' => 'Giá trị :attribute phải là một số.',
    'password' => [
        'letters' => 'Trường :attribute phải chứa ít nhất một chữ cái.',
        'mixed' => 'Trường :attribute phải chứa ít nhất một chữ hoa và một chữ thường.',
        'numbers' => 'Trường :attribute phải chứa ít nhất một chữ số.',
        'symbols' => 'Trường :attribute phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => 'Mật khẩu này đã lộ trong một vụ rò rỉ dữ liệu. Vui lòng chọn mật khẩu khác.',
    ],
    'present' => 'Trường :attribute phải được gửi lên.',
    'present_if' => 'Trường :attribute phải được gửi lên khi :other là :value.',
    'present_unless' => 'Trường :attribute phải được gửi lên trừ khi :other là :value.',
    'present_with' => 'Trường :attribute phải được gửi lên khi có :values.',
    'present_with_all' => 'Trường :attribute phải được gửi lên khi có :values.',
    'prohibited' => 'Trường :attribute không được phép.',
    'prohibited_if' => 'Trường :attribute không được phép khi :other là :value.',
    'prohibited_if_accepted' => 'Trường :attribute không được phép khi :other được chấp nhận.',
    'prohibited_if_declined' => 'Trường :attribute không được phép khi :other bị từ chối.',
    'prohibited_unless' => 'Trường :attribute không được phép trừ khi :other thuộc :values.',
    'prohibits' => 'Trường :attribute khiến :other không được phép xuất hiện.',
    'regex' => 'Định dạng :attribute không hợp lệ.',
    'required' => 'Vui lòng nhập :attribute.',
    'required_array_keys' => 'Trường :attribute phải chứa các khoá: :values.',
    'required_if' => 'Vui lòng nhập :attribute khi :other là :value.',
    'required_if_accepted' => 'Vui lòng nhập :attribute khi :other được chấp nhận.',
    'required_if_declined' => 'Vui lòng nhập :attribute khi :other bị từ chối.',
    'required_unless' => 'Vui lòng nhập :attribute trừ khi :other thuộc :values.',
    'required_with' => 'Vui lòng nhập :attribute khi có :values.',
    'required_with_all' => 'Vui lòng nhập :attribute khi có :values.',
    'required_without' => 'Vui lòng nhập :attribute khi chưa có :values.',
    'required_without_all' => 'Vui lòng nhập :attribute khi chưa có :values.',
    'same' => 'Trường :attribute phải trùng với :other.',
    'size' => [
        'array' => 'Trường :attribute phải có đúng :size phần tử.',
        'file' => 'Dung lượng :attribute phải đúng :size KB.',
        'numeric' => 'Giá trị :attribute phải bằng :size.',
        'string' => 'Độ dài :attribute phải đúng :size ký tự.',
    ],
    'starts_with' => 'Trường :attribute phải bắt đầu bằng một trong: :values.',
    'string' => 'Giá trị :attribute phải là chuỗi ký tự.',
    'timezone' => 'Trường :attribute phải là múi giờ hợp lệ.',
    'ulid' => 'Trường :attribute phải là ULID hợp lệ.',
    'unique' => 'Giá trị :attribute đã tồn tại.',
    'uploaded' => 'Không tải lên được :attribute.',
    'uppercase' => 'Trường :attribute phải viết hoa.',
    'url' => 'Trường :attribute phải là URL hợp lệ.',
    'uuid' => 'Trường :attribute phải là UUID hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Thông báo riêng cho từng trường
    |--------------------------------------------------------------------------
    |
    | "Định dạng đường dẫn không hợp lệ" không nói cho người sửa biết cái gì mới
    | là hợp lệ, nên vài trường hay sai được viết lại cho cụ thể.
    |
    */

    'custom' => [
        'translations.*.slug' => [
            'regex' => 'Đường dẫn chỉ được dùng chữ thường không dấu, số và dấu gạch ngang.',
            'unique' => 'Đường dẫn này đã được dùng cho một nội dung khác.',
        ],
        'key' => [
            'regex' => 'Mã trang chỉ được dùng chữ thường không dấu, số và dấu gạch ngang.',
            'unique' => 'Mã trang này đã tồn tại.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tên hiển thị của các trường
    |--------------------------------------------------------------------------
    |
    | Không có phần này thì :attribute là tên field thô, kiểu
    | `translations.vi.meta_title`.
    |
    */

    'attributes' => [
        'alt' => 'mô tả ảnh',
        'body' => 'nội dung',
        'category_id' => 'danh mục',
        'cover_media_id' => 'ảnh đại diện',
        'description' => 'mô tả',
        'email' => 'email',
        'excerpt' => 'mô tả ngắn',
        'file' => 'tệp',
        'icon' => 'biểu tượng',
        'industry_ids' => 'lĩnh vực',
        'is_featured' => 'nổi bật',
        'key' => 'mã trang',
        'locale' => 'ngôn ngữ',
        'meta_description' => 'meta description',
        'meta_title' => 'meta title',
        'name' => 'tên',
        'page_id' => 'trang',
        'parent_id' => 'mục cha',
        'password' => 'mật khẩu',
        'published_at' => 'thời điểm đăng',
        'service_ids' => 'dịch vụ',
        'slug' => 'đường dẫn',
        'sort_order' => 'thứ tự',
        'status' => 'trạng thái',
        'tag_ids' => 'thẻ',
        'title' => 'tiêu đề',
        'translations' => 'nội dung đa ngôn ngữ',
        'translations.*.body' => 'nội dung',
        'translations.*.description' => 'mô tả',
        'translations.*.excerpt' => 'mô tả ngắn',
        'translations.*.meta_description' => 'meta description',
        'translations.*.meta_title' => 'meta title',
        'translations.*.name' => 'tên',
        'translations.*.slug' => 'đường dẫn',
        'translations.*.title' => 'tiêu đề',
        'url' => 'đường dẫn',
    ],
];
