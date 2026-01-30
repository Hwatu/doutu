<?php

namespace DouTu\Animation;

use DouTu\Utils\ColorHelper;
use DouTu\Renderer\ImageRenderer;

/**
 * 背景动画类
 * 支持背景渐变流动等动画效果
 */
class BackgroundAnimation
{
    // 动画类型常量
    public const ANIMATION_NONE = 'none';
    public const ANIMATION_GRADIENT = 'gradient';

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
    public function __construct(string $type, int $frameCount = 24, array $params = [], ImageRenderer $renderer = null)
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
     * @param int $width 宽度
     * @param int $height 高度
     * @return array 帧数组
     */
    public function generateFrames(int $width, int $height): array
    {
        $frames = [];

        for ($i = 0; $i < $this->frameCount; $i++) {
            $progress = $i / $this->frameCount;

            $canvas = $this->renderer ? $this->renderer->createCanvas($width, $height) : imagecreatetruecolor($width, $height);

            $this->drawFrame($canvas, $width, $height, $progress);

            $frames[] = $canvas;
        }

        return $frames;
    }

    /**
     * 绘制单帧
     *
     * @param mixed $canvas 画布
     * @param int $width 宽度
     * @param int $height 高度
     * @param float $progress 进度
     * @return void
     */
    private function drawFrame($canvas, int $width, int $height, float $progress): void
    {
        match($this->type) {
            self::ANIMATION_GRADIENT => $this->drawGradient($canvas, $width, $height, $progress),
            default => null,
        };
    }

    /**
     * 绘制渐变流动背景
     *
     * @param mixed $canvas 画布
     * @param int $width 宽度
     * @param int $height 高度
     * @param float $offset 偏移量
     * @return void
     */
    private function drawGradient($canvas, int $width, int $height, float $offset): void
    {
        $startColor = $this->params['start_color'] ?? '#FF0000';
        $endColor = $this->params['end_color'] ?? '#0000FF';
        $direction = $this->params['direction'] ?? 'horizontal';

        $c1 = ColorHelper::hexToRgb($startColor);
        $c2 = ColorHelper::hexToRgb($endColor);

        if ($direction === 'horizontal') {
            $this->drawHorizontalGradient($canvas, $width, $height, $c1, $c2, $offset);
        } else {
            $this->drawVerticalGradient($canvas, $width, $height, $c1, $c2, $offset);
        }
    }

    /**
     * 绘制水平渐变
     *
     * @param mixed $canvas 画布
     * @param int $width 宽度
     * @param int $height 高度
     * @param array $c1 起始颜色 RGB
     * @param array $c2 结束颜色 RGB
     * @param float $offset 偏移量
     * @return void
     */
    private function drawHorizontalGradient($canvas, int $width, int $height, array $c1, array $c2, float $offset): void
    {
        for ($x = 0; $x < $width; $x++) {
            // 计算偏移后的位置，实现流动效果
            $normalizedPos = ($x + $offset * $width) % $width / $width;

            $r = (int) round($c1[0] * (1 - $normalizedPos) + $c2[0] * $normalizedPos);
            $g = (int) round($c1[1] * (1 - $normalizedPos) + $c2[1] * $normalizedPos);
            $b = (int) round($c1[2] * (1 - $normalizedPos) + $c2[2] * $normalizedPos);

            $color = imagecolorallocate($canvas, $r, $g, $b);
            imageline($canvas, $x, 0, $x, $height, $color);
            imagedestroy($color);
        }
    }

    /**
     * 绘制垂直渐变
     *
     * @param mixed $canvas 画布
     * @param int $width 宽度
     * @param int $height 高度
     * @param array $c1 起始颜色 RGB
     * @param array $c2 结束颜色 RGB
     * @param float $offset 偏移量
     * @return void
     */
    private function drawVerticalGradient($canvas, int $width, int $height, array $c1, array $c2, float $offset): void
    {
        for ($y = 0; $y < $height; $y++) {
            // 计算偏移后的位置，实现流动效果
            $normalizedPos = ($y + $offset * $height) % $height / $height;

            $r = (int) round($c1[0] * (1 - $normalizedPos) + $c2[0] * $normalizedPos);
            $g = (int) round($c1[1] * (1 - $normalizedPos) + $c2[1] * $normalizedPos);
            $b = (int) round($c1[2] * (1 - $normalizedPos) + $c2[2] * $normalizedPos);

            $color = imagecolorallocate($canvas, $r, $g, $b);
            imageline($canvas, 0, $y, $width - 1, $y, $color);
            imagedestroy($color);
        }
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
            self::ANIMATION_GRADIENT,
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
