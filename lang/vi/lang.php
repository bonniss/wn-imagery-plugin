<?php

return [
    'plugin' => [
        'name' => 'Imagery',
        'description' => 'Ảnh placeholder chất lượng thấp, hiển thị trong lúc ảnh thật đang tải.',
    ],
    'permissions' => [
        'manage_settings' => 'Quản lý cài đặt placeholder ảnh',
    ],
    'modes' => [
        'dominant_color' => 'Màu chủ đạo - một màu phẳng, nhẹ nhất',
        'gradient' => 'Gradient - bốn màu, không có dữ liệu ảnh',
        'blur' => 'Ảnh thu nhỏ làm mờ - giống thật nhất, nặng nhất',
        'css_only' => 'Chỉ CSS (thử nghiệm) - cần stylesheet giải mã',
        'thumbhash' => 'ThumbHash - chuỗi hash cho API/app tự giải mã, không phải CSS',
    ],
    'drivers' => [
        'auto' => 'Tự động (Imagick nếu có, không thì GD)',
        'gd' => 'GD',
        'imagick' => 'Imagick - nhanh hơn với JPEG lớn',
    ],
    'settings' => [
        'label' => 'Placeholder ảnh',
        'description' => 'Cách tạo placeholder hiển thị trong lúc ảnh đang tải.',
        'inherit' => 'Kế thừa (hiện tại: :value)',
        'yes' => 'Có',
        'no' => 'Không',
        'mode' => [
            'label' => 'Kiểu placeholder',
            'comment' => 'Filter |lqip cần giá trị cho background-image, nên với "Chỉ CSS" nó tự dùng màu chủ đạo. |lqipStyle dùng được với mọi kiểu.',
        ],
        'driver' => [
            'label' => 'Engine xử lý ảnh',
            'comment' => 'Chỉ liệt kê engine có trên server này. Đổi engine không làm mất placeholder đã tạo.',
        ],
        'format' => [
            'label' => 'Định dạng ảnh (chỉ cho blur)',
            'comment' => 'Ở kích thước này WebP nhỏ hơn JPEG khoảng 9 lần. Nếu server không ghi được WebP thì tự dùng JPEG.',
        ],
        'blur' => [
            'label' => 'Làm mờ ảnh thu nhỏ (chỉ cho blur)',
            'comment' => 'Làm mềm để trông như ảnh mờ thay vì ô vuông nhỏ. Cũng giúp nhẹ hơn.',
        ],
        'long_edge' => [
            'label' => 'Kích thước ảnh thu nhỏ, px (chỉ cho blur)',
            'comment' => 'Độ dài cạnh dài, 1-64. Mặc định đã gần mức nhỏ nhất mà vẫn đẹp.',
        ],
        'quality' => [
            'label' => 'Chất lượng nén (chỉ cho blur)',
            'comment' => '0-100. Sau khi làm mờ gần như không còn chi tiết; tăng lên chủ yếu chỉ tốn thêm byte.',
        ],
        'smooth' => [
            'label' => 'Làm mịn khi phóng to (chỉ cho blur)',
            'comment' => 'Làm mờ thêm một lần ở kích thước hiển thị, hết các khối vuông mờ. Nặng gấp khoảng 4 lần (khoảng 530 ký tự thay vì 130).',
        ],
        'background' => [
            'label' => 'Màu nền cho ảnh trong suốt',
            'comment' => 'Để trống thì giữ trong suốt: logo có placeholder đúng màu và đúng hình. Điền màu nền trang (vd. #ffffff) để đặt ảnh lên màu đó trước.',
        ],
    ],
];
