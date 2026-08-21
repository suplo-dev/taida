<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tự xuất bản lại site sau khi biên tập
    |--------------------------------------------------------------------------
    |
    | Site công khai là HTML tĩnh sinh lúc build, nên nội dung vừa lưu trong CMS
    | chưa xuất hiện cho tới khi build lại. Khi bật, mỗi thay đổi nội dung sẽ
    | hẹn một lần build trên GitHub Actions.
    |
    | Để trống token là tắt — ứng dụng vẫn chạy bình thường, chỉ là phải tự bấm
    | "Run workflow" trên GitHub.
    |
    */

    'enabled' => env('PUBLISH_ENABLED', false),

    'github' => [
        // Personal access token loại fine-grained, quyền `actions: write` trên
        // đúng repo này. Không cần quyền nào khác.
        'token' => env('PUBLISH_GITHUB_TOKEN'),

        // Dạng "chu-so-huu/ten-repo".
        'repository' => env('PUBLISH_GITHUB_REPOSITORY'),

        // Tên file workflow, không phải tên hiển thị.
        'workflow' => env('PUBLISH_GITHUB_WORKFLOW', 'deploy.yml'),

        'ref' => env('PUBLISH_GITHUB_REF', 'main'),

        /*
        | Input truyền cho workflow. `api => false` là điểm mấu chốt: lần build
        | này sinh ra từ một thao tác BIÊN TẬP, mã nguồn không đổi một byte —
        | không có lý do gì cài lại `vendor/`, đẩy 28MB qua FTP rồi chạy
        | migrate. Bỏ trống input thì GitHub áp mặc định của workflow (`true`)
        | và mỗi lần sửa một dòng chữ lại kéo theo một lượt deploy backend đầy
        | đủ. Job `Backend` bị skip, và điều kiện của job `Build` đã lường sẵn
        | ca đó (`needs.api.result != 'failure'`).
        |
        | Đổi PUBLISH_GITHUB_WORKFLOW sang workflow khác thì phải sửa cả đây:
        | GitHub trả 422 nếu input không có trong workflow được gọi.
        */
        'inputs' => ['api' => false],
    ],

    /*
    | Đợi bấy nhiêu giây kể từ lần sửa cuối rồi mới build. Biên tập viên hiếm khi
    | sửa đúng một thứ: sửa dịch vụ, sửa tiếp ảnh, sửa tiếp menu. Nếu build ngay
    | từng lần thì ba lần sửa thành ba lần build, và bản build đầu đã lỗi thời
    | trước cả khi chạy xong.
    */
    'quiet_period' => (int) env('PUBLISH_QUIET_PERIOD', 90),

    /*
    | Khoảng cách tối thiểu giữa hai lần build. Chặn trường hợp nhập liệu liên
    | tục hàng giờ làm hàng chục build xếp hàng — GitHub Actions có hạn mức, và
    | build sau sẽ huỷ ý nghĩa của build trước.
    */
    'cooldown' => (int) env('PUBLISH_COOLDOWN', 300),

];
