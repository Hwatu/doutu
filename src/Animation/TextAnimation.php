<?php

namespace DouTu\Animation;

use DouTu\Utils\ColorHelper;
use DouTu\Renderer\ImageRenderer;

/**
 * 文字动画类
 * 支持多种文字动画效果
 */
class TextAnimation
{
    // 动画类型常量
    public const ANIMATION_NONE = 'none';
    public const ANIMATION_FLOWLIGHT = 'flowlight';
    public const ANIMATION_BLINK = 'blink';
    public const ANIMATION_GLOW = 'glow';
    public const ANIMATION_SHAKE = 'shake';
    public const ANIMATION_BOUNCE = 'bounce';

    private string $type;
    private int $frameCount;
    private array $params;
    private ImageRenderer $renderer;

    /**
     * 构造函数
     *
     * @param string $type 动画类型
     * @param int $frameCount 帧数
     * @param array $params 动画参数
     */
    public function __construct(string $type, int $frameCount = 12, array $params = [], ImageRenderer $renderer = null)
    {
        $this->type = $type;
        $this->frameCount = max(1, min(60, $frameCount));
        $this->params = $params;
        $this->renderer = $renderer;
    }

    /**
     * 设置渲染器
     *
     * @param ImageRenderer $renderer
     * @return void
     */
    public function setRenderer(ImageRenderer $renderer): void
    {
        $this->renderer = $renderer;
    }

    /**
     * 生成动画帧
     *
     * @param callable $renderCallback 渲染回调 ($canvas, $frameParams, $frameIndex)
     * @param int $width 宽度
     * @param int $height 高度
     * @return array 帧数组
     */
    public function generateFrames(callable $renderCallback, int $width, int $height): array
    {
        $frames = [];

        for ($i = 0; $i < $this->frameCount; $i++) {
            $progress = $i / max(1, $this->frameCount - 1);

            $frameParams = $this->calculateFrameParams($progress);

            $canvas = $this->renderer ? $this->renderer->createCanvas($width, $height) : imagecreatetruecolor($width, $height);

            $renderCallback($canvas, $frameParams, $i);

            $frames[] = $canvas;
        }

        return $frames;
    }

    /**
     * 计算帧参数
     *
     * @param float $progress 进度 (0.0 - 1.0)
     * @return array 帧参数
     */
    private function calculateFrameParams(float $progress): array
    {
        return match($this->type) {
            self::ANIMATION_FLOWLIGHT => $this->calculateFlowlight($progress),
            self::ANIMATION_BLINK => $this->calculateBlink($progress),
            self::ANIMATION_GLOW => $this->calculateGlow($progress),
            self::ANIMATION_SHAKE => $this->calculateShake($progress),
            self::ANIMATION_BOUNCE => $this->calculateBounce($progress),
            default => [],
        };
    }

    /**
     * 流光效果：颜色从左到右渐变流动
     *
     * @param float $progress 进度
     * @return array
     */
    private function calculateFlowlight(float $progress): array
    {
        $baseColor = $this->params['base_color'] ?? '#FFFFFF';
        $highlightColor = $this->params['highlight_color'] ?? '#FFFF00';

        // 计算插值颜色
        $color = ColorHelper::interpolateColor($baseColor, $highlightColor, $progress);

        // 透明度随进度变化（中间亮）
        $alpha = (int) (127 - sin($progress * M_PI) * 60);

        return [
            'color' => ColorHelper::hexToRgb($color),
            'alpha' => max(0, min(127, $alpha)),
        ];
    }

    /**
     * 闪烁效果：透明度周期性变化
     *
     * @param float $progress 进度
     * @return array
     */
    private function calculateBlink(float $progress): array
    {
        // 正弦波周期变化
        $alpha = (int) (80 + 47 * sin($progress * M_PI * 2));

        return [
            'alpha' => max(0, min(127, $alpha)),
        ];
    }

    /**
     * 发光效果：光晕半径周期性变化
     *
     * @param float $progress 进度
     * @return array
     */
    private function calculateGlow(float $progress): array
    {
        // 半径周期性变化
        $radius = 2 + 3 * sin($progress * M_PI * 2);
        $intensity = 0.5 + 0.5 * sin($progress * M_PI * 2);

        return [
            'glow_radius' => max(1, $radius),
            'glow_intensity' => max(0, min(1, $intensity)),
        ];
    }

    /**
     * 抖动效果：位置随机偏移
     *
     * @param float $progress 进度
     * @return array
     */
    private function calculateShake(float $progress): array
    {
        $intensity = $this->params['intensity'] ?? 3;

        // 使用正弦和余弦创建抖动效果
        $offsetX = (int) (sin($progress * M_PI * 8) * $intensity);
        $offsetY = (int) (cos($progress * M_PI * 8) * $intensity);

        return [
            'offset_x' => $offsetX,
            'offset_y' => $offsetY,
        ];
    }

    /**
     * 弹跳效果：Y 轴位置周期性变化
     *
     * @param float $progress 进度
     * @return array
     */
    private function calculateBounce(float $progress): array
    {
        $amplitude = $this->params['amplitude'] ?? 10;

        // 正弦波弹跳效果
        $offsetY = (int) (-abs(sin($progress * M_PI)) * $amplitude);

        return [
            'offset_y' => $offsetY,
        ];
    }

    /**
     * 获取动画类型
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 获取帧数
     *
     * @return int
     */
    public function getFrameCount(): int
    {
        return $this->frameCount;
    }

    /**
     * 获取支持的动画类型
     *
     * @return array
     */
    public static function getSupportedTypes(): array
    {
        return [
            self::ANIMATION_FLOWLIGHT,
            self::ANIMATION_BLINK,
            self::ANIMATION_GLOW,
            self::ANIMATION_SHAKE,
            self::ANIMATION_BOUNCE,
        ];
    }

    /**
     * 检查动画类型是否支持
     *
     * @param string $type 动画类型
     * @return bool
     */
    public static function isTypeSupported(string $type): bool
    {
        return in_array($type, self::getSupportedTypes(), true);
    }
}
