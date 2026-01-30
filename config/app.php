<?php

/**
 * 应用配置文件
 */
return [
    // ========== 应用配置 ==========
    'app' => [
        'public_path' => __DIR__ . '/../public',
        'storage_path' => __DIR__ . '/../storage',
        'debug' => false,
    ],

    // ========== 路径配置 ==========
    'paths' => [
        'fonts' => __DIR__ . '/../fonts',
        'configs' => __DIR__ . '/../configs',
        'output' => __DIR__ . '/../output',
        'img' => __DIR__ . '/../img',
    ],

    // ========== 默认值 ==========
    'defaults' => [
        'font' => 'msyh.ttf',
        'format' => 'png',
        'canvas_size' => 1024,
        'padding' => 60,
        'font_size' => 32,
        'font_color' => '#000000',
        'bg_color' => '#FFFFFF',
    ],

    // ========== 字体 Fallback ==========
    'fonts' => [
        'default' => 'msyh.ttf',
        'fallbacks' => [
            'msyh.ttf',
            'wqy-zenhei.ttc',
            '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ],
    ],

    // ========== 动画配置 ==========
    'animation' => [
        'default_frame_count' => 12,
        'default_frame_delay' => 100,
        'max_frame_count' => 60,
        'min_frame_count' => 1,
        'default_loop_count' => 0,
    ],

    // ========== 缓存配置 ==========
    'cache' => [
        'enabled' => true,
        'ttl' => 86400, // 24小时
        'path' => __DIR__ . '/../storage/cache',
    ],

    // ========== 清理配置 ==========
    'cleanup' => [
        'enabled' => true,
        'ttl_minutes' => 1440, // 24小时
        'interval_minutes' => 60,
    ],

    // ========== 性能配置 ==========
    'performance' => [
        'max_generation_time' => 1000, // 毫秒
        'enable_cache' => true,
        'enable_frame_optimization' => true,
    ],

    // ========== 输出配置 ==========
    'output' => [
        'quality' => [
            'png' => 9,
            'jpeg' => 90,
            'webp' => 80,
            'avif' => 80,
        ],
    ],
];
