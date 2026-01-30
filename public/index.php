<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DouTu\Animation\BackgroundAnimation;
use DouTu\Animation\TextAnimation;
use DouTu\Cache\FileCache;
use DouTu\Core\Application;
use DouTu\Core\Config;
use DouTu\Core\Constants;
use DouTu\Core\ImageGenerator;
use DouTu\Renderer\GDRenderer;

// 加载配置
$config = Config::loadFromFile(__DIR__ . '/../config/app.php');

// 创建应用实例
$app = new Application($config);
$renderer = new GDRenderer();
$cache = null;

if ($config->get('cache.enabled', false)) {
    $cachePath = $config->get('cache.path');
    $cacheTtl = (int) $config->get('cache.ttl', Constants::DEFAULT_CACHE_TTL);
    $cache = new FileCache($cachePath, $cacheTtl);
}

$generator = new ImageGenerator($config, $renderer, $cache);

// 定义路由
$app->routes([
    // 生成接口
    '/' => function($get, $post, $server) use ($config, $generator) {
        $text = $get['keyword'] ?? $get['text'] ?? '斗图';
        $format = strtolower($get['format'] ?? 'png');

        // 验证格式
        if (!Constants::isFormatSupported($format)) {
            return [
                'success' => false,
                'error' => "不支持的格式: {$format}",
                'supported_formats' => Constants::SUPPORTED_FORMATS,
            ];
        }

        $params = $get;
        $params['format'] = $format;

        return $generator->generate($text, $params);
    },

    // 健康检查
    '/health' => function() use ($config) {
        return [
            'success' => true,
            'status' => 'ok',
            'version' => '2.0.0',
            'cache_enabled' => $config->get('cache.enabled', false),
            'cache_stats' => [
                'path' => $config->get('cache.path'),
                'ttl' => $config->get('cache.ttl'),
            ],
        ];
    },

    // 支持的格式
    '/formats' => function() {
        return [
            'success' => true,
            'formats' => Constants::SUPPORTED_FORMATS,
            'animated_formats' => Constants::ANIMATED_FORMATS,
            'mime_types' => array_reduce(Constants::SUPPORTED_FORMATS, function($carry, $format) {
                $carry[$format] = Constants::getMimeType($format);
                return $carry;
            }, []),
        ];
    },

    // 支持的动画
    '/animations' => function() {
        return [
            'success' => true,
            'text_animations' => [
                TextAnimation::ANIMATION_NONE => '无动画',
                TextAnimation::ANIMATION_FLOWLIGHT => '流光效果',
                TextAnimation::ANIMATION_BLINK => '闪烁效果',
                TextAnimation::ANIMATION_GLOW => '发光效果',
                TextAnimation::ANIMATION_SHAKE => '抖动效果',
                TextAnimation::ANIMATION_BOUNCE => '弹跳效果',
            ],
            'background_animations' => [
                BackgroundAnimation::ANIMATION_NONE => '无动画',
                BackgroundAnimation::ANIMATION_GRADIENT => '渐变流动',
            ],
        ];
    },
]);

// 运行应用
$app->run();
