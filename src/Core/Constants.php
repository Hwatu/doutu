<?php

namespace DouTu\Core;

/**
 * 常量定义类
 * 消除魔法数字，集中管理所有常量
 */
class Constants
{
    // ========== 画布尺寸 ==========
    /** 默认画布尺寸 */
    public const DEFAULT_CANVAS_SIZE = 1024;
    /** 默认内边距 */
    public const DEFAULT_PADDING = 60;
    /** 最小画布尺寸 */
    public const MIN_CANVAS_SIZE = 200;
    /** 最大画布尺寸 */
    public const MAX_CANVAS_SIZE = 2048;

    // ========== 字体 ==========
    /** 最小字体大小 */
    public const MIN_FONT_SIZE = 12;
    /** 最大字体大小 */
    public const MAX_FONT_SIZE = 900;
    /** 默认字体大小 */
    public const DEFAULT_FONT_SIZE = 32;
    /** 字体大小自适应默认最大 */
    public const ADAPTIVE_MAX_FONT_SIZE = 900;
    /** 字体大小自适应默认最小 */
    public const ADAPTIVE_MIN_FONT_SIZE = 12;

    // ========== 动画 ==========
    /** 默认动画帧数 */
    public const DEFAULT_FRAME_COUNT = 12;
    /** 最大动画帧数 */
    public const MAX_FRAME_COUNT = 60;
    /** 最小动画帧数 */
    public const MIN_FRAME_COUNT = 1;
    /** 默认帧延迟（毫秒） */
    public const DEFAULT_FRAME_DELAY = 100;
    /** 最小帧延迟（毫秒） */
    public const MIN_FRAME_DELAY = 50;
    /** 最大帧延迟（毫秒） */
    public const MAX_FRAME_DELAY = 200;
    /** 默认循环次数（0 = 无限） */
    public const DEFAULT_LOOP_COUNT = 0;

    // ========== 输出格式 ==========
    /** PNG 格式 */
    public const FORMAT_PNG = 'png';
    /** GIF 格式 */
    public const FORMAT_GIF = 'gif';
    /** WebP 格式 */
    public const FORMAT_WEBP = 'webp';
    /** AVIF 格式 */
    public const FORMAT_AVIF = 'avif';

    // ========== 缓存 ==========
    /** 默认缓存 TTL（24小时，秒） */
    public const DEFAULT_CACHE_TTL = 86400;

    // ========== 布局模式 ==========
    /** 标准布局 */
    public const LAYOUT_STANDARD = 'standard';
    /** 聊天气泡布局 */
    public const LAYOUT_CHAT = 'chat';
    /** 模板卡片布局 */
    public const LAYOUT_TEMPLATE_CARD = 'template_card';

    // ========== 动画类型 ==========
    /** 无动画 */
    public const ANIMATION_NONE = 'none';
    /** 流光动画 */
    public const ANIMATION_FLOWLIGHT = 'flowlight';
    /** 闪烁动画 */
    public const ANIMATION_BLINK = 'blink';
    /** 发光动画 */
    public const ANIMATION_GLOW = 'glow';
    /** 抖动动画 */
    public const ANIMATION_SHAKE = 'shake';
    /** 弹跳动画 */
    public const ANIMATION_BOUNCE = 'bounce';

    // ========== 背景动画类型 ==========
    /** 无背景动画 */
    public const BG_ANIMATION_NONE = 'none';
    /** 渐变流动 */
    public const BG_ANIMATION_GRADIENT = 'gradient';

    // ========== 支持的输出格式列表 ==========
    public const SUPPORTED_FORMATS = [
        self::FORMAT_PNG,
        self::FORMAT_GIF,
        self::FORMAT_WEBP,
        self::FORMAT_AVIF,
    ];

    // ========== 支持动画的格式 ==========
    public const ANIMATED_FORMATS = [
        self::FORMAT_GIF,
        self::FORMAT_WEBP,
    ];

    /**
     * 检查格式是否支持动画
     *
     * @param string $format 输出格式
     * @return bool
     */
    public static function supportsAnimation(string $format): bool
    {
        return in_array(strtolower($format), self::ANIMATED_FORMATS, true);
    }

    /**
     * 检查格式是否支持
     *
     * @param string $format 输出格式
     * @return bool
     */
    public static function isFormatSupported(string $format): bool
    {
        return in_array(strtolower($format), self::SUPPORTED_FORMATS, true);
    }

    /**
     * 获取格式的 MIME 类型
     *
     * @param string $format 输出格式
     * @return string
     */
    public static function getMimeType(string $format): string
    {
        return match(strtolower($format)) {
            self::FORMAT_PNG => 'image/png',
            self::FORMAT_GIF => 'image/gif',
            self::FORMAT_WEBP => 'image/webp',
            self::FORMAT_AVIF => 'image/avif',
            default => 'image/png',
        };
    }
}
