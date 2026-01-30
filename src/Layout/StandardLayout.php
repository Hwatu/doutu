<?php

namespace DouTu\Layout;

use DouTu\Core\Constants;
use DouTu\Renderer\ImageRenderer;
use DouTu\Utils\ColorHelper;

/**
 * 标准布局实现
 */
class StandardLayout implements LayoutInterface
{
    private ImageRenderer $renderer;
    private int $canvasSize = 1024;
    private int $padding = 60;

    public function __construct(ImageRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function prepare(string $text, array $params): LayoutData
    {
        $fontFile = $params['font_file'] ?? '';
        $requestedFontSize = isset($params['font_size']) ? max(12, (int) $params['font_size']) : 32;
        $autoSize = (bool) ($params['auto_size'] ?? true);
        $wrapAuto = (bool) ($params['wrap_auto'] ?? true);

        // 计算画布尺寸
        $canvasSize = $params['canvas_size'] ?? $this->canvasSize;
        $padding = $params['padding'] ?? $this->padding;
        $maxCanvasWidth = max(200, $canvasSize - ($padding * 2));
        $maxCanvasHeight = max(200, $canvasSize - ($padding * 2));

        // 换行限制
        $wrapLimitInput = isset($params['wrap_limit']) ? (int) $params['wrap_limit'] : 0;
        $effectiveWrapLimit = 0;

        if ($wrapAuto) {
            $effectiveWrapLimit = $wrapLimitInput > 0
                ? min($wrapLimitInput, $maxCanvasWidth)
                : $maxCanvasWidth;
        } else {
            $effectiveWrapLimit = $wrapLimitInput > 0 ? max(50, $wrapLimitInput) : 0;
        }

        // 字号二分查找
        $minFontSize = Constants::MIN_FONT_SIZE;
        $maxFontSize = Constants::MAX_FONT_SIZE;
        $searchMax = $autoSize ? $maxFontSize : max($minFontSize, min($requestedFontSize, $maxFontSize));

        $bestSize = max($minFontSize, min($requestedFontSize, $searchMax));
        $bestLayout = $this->measureLayout($text, $fontFile, $bestSize, $effectiveWrapLimit);

        if ($bestLayout['metrics']['max_width'] > $maxCanvasWidth
            || $bestLayout['metrics']['total_height'] > $maxCanvasHeight) {
            $bestLayout = null;
        }

        // 二分查找最优字号
        $low = $minFontSize;
        $high = $searchMax;

        while ($low <= $high) {
            $mid = (int) (($low + $high) / 2);
            $layout = $this->measureLayout($text, $fontFile, $mid, $effectiveWrapLimit);
            $metrics = $layout['metrics'];

            if ($metrics['max_width'] <= $maxCanvasWidth && $metrics['total_height'] <= $maxCanvasHeight) {
                $bestSize = $mid;
                $bestLayout = $layout;
                $low = $mid + 1;
                if (!$autoSize && $mid >= $requestedFontSize) {
                    break;
                }
            } else {
                $high = $mid - 1;
            }
        }

        if ($bestLayout === null) {
            $bestSize = $minFontSize;
            $bestLayout = $this->measureLayout($text, $fontFile, $bestSize, $effectiveWrapLimit);
        }

        $lines = $bestLayout['lines'];
        $metrics = $bestLayout['metrics'];
        $textWidth = $metrics['max_width'];
        $textHeight = $metrics['total_height'];
        $lineHeight = $metrics['line_height'];
        $lineSpacing = $metrics['line_spacing'];

        // 计算起始坐标
        $textAlign = $params['text_align'] ?? 'center';
        $posX = $params['pos_x'] ?? 50; // 百分比
        $posY = $params['pos_y'] ?? 50; // 百分比

        $startX = $this->calculateStartX($canvasSize, $textWidth, $padding, $textAlign, $posX);
        $startY = $this->calculateStartY($canvasSize, $textHeight, $padding, $posY);

        return new LayoutData(
            width: $canvasSize,
            height: $canvasSize,
            lines: $lines,
            textWidth: $textWidth,
            textHeight: $textHeight,
            lineHeight: $lineHeight,
            startX: $startX,
            startY: $startY,
            fontInfo: [
                'path' => $fontFile,
                'size' => $bestSize,
                'line_spacing' => $lineSpacing,
            ]
        );
    }

    public function drawBackground($canvas, array $params): void
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $bgColorSetting = $params['bg_color'] ?? '#FFFFFF';

        if ($bgColorSetting === 'image') {
            $bgImagePath = $params['bg_image_path'] ?? __DIR__ . '/../../img/background.png';
            if (!file_exists($bgImagePath)) {
                $this->fillSolid($canvas, '#FFFFFF');
                return;
            }

            try {
                $bgImage = $this->renderer->loadImage($bgImagePath);
                $bgWidth = imagesx($bgImage);
                $bgHeight = imagesy($bgImage);
                $bgImageSize = $params['bg_image_size'] ?? 'cover';

                $this->drawBackgroundImage($canvas, $bgImage, $width, $height, $bgWidth, $bgHeight, $bgImageSize);
                $this->renderer->destroy($bgImage);
            } catch (\Exception $e) {
                // 降级到纯色背景
                $this->fillSolid($canvas, '#FFFFFF');
            }
        } elseif ($bgColorSetting === 'transparent') {
            // 透明背景，无需填充
        } else {
            // 纯色或渐变背景
            $this->fillSolid($canvas, $bgColorSetting);
        }
    }

    public function drawText($canvas, LayoutData $layout, array $params, array $frameParams = []): void
    {
        $font = $layout->fontInfo;
        $fontFile = $font['path'];
        $fontSize = $font['size'];
        $lineSpacing = $font['line_spacing'];

        // 解析颜色
        $colorSetting = $params['font_color'] ?? '#000000';
        $randomColor = (bool) ($params['random_color'] ?? false);

        if ($randomColor) {
            $color = [
                mt_rand(0, 255),
                mt_rand(0, 255),
                mt_rand(0, 255),
            ];
        } else {
            $color = $this->safeHexToRgb($colorSetting, [0, 0, 0]);
        }

        // 应用动画参数
        $textColor = $color;
        $alpha = 0;

        if (!empty($frameParams['color'])) {
            $textColor = $frameParams['color'];
        }

        if (!empty($frameParams['alpha'])) {
            $alpha = $frameParams['alpha'];
        }

        // 应用偏移
        $offsetX = $frameParams['offset_x'] ?? 0;
        $offsetY = $frameParams['offset_y'] ?? 0;

        $x = $layout->startX + $offsetX;
        $y = $layout->startY + $offsetY;

        $effect = $params['effect'] ?? 'none';
        $fontWeight = isset($params['font_weight']) ? (int) $params['font_weight'] : 0;

        // 绘制每行文本
        foreach ($layout->lines as $index => $line) {
            $lineY = $y + $index * ($layout->lineHeight + $lineSpacing);

            $this->drawTextLineWithEffects(
                $canvas,
                $fontSize,
                $fontFile,
                $line === '' ? ' ' : $line,
                (int) $x,
                (int) $lineY,
                $textColor,
                $alpha,
                $effect,
                $fontWeight,
                $frameParams
            );
        }
    }

    public function getCanvasSize(array $params): array
    {
        $canvasSize = $params['canvas_size'] ?? $this->canvasSize;
        return ['width' => $canvasSize, 'height' => $canvasSize];
    }

    /**
     * 测量布局
     */
    private function measureLayout(string $text, string $fontFile, int $fontSize, int $wrapLimit): array
    {
        $lines = $this->prepareTextLines($text, $fontFile, $fontSize, $wrapLimit);
        $metrics = $this->calculateTextMetrics($lines, $fontFile, $fontSize);

        return [
            'lines' => $lines,
            'metrics' => $metrics,
        ];
    }

    /**
     * 准备文本行
     */
    private function prepareTextLines(string $text, string $fontFile, int $fontSize, int $wrapLimit): array
    {
        $rawLines = preg_split("/\r\n|\r|\n/", $text);
        $rawLines = $rawLines === false ? [] : $rawLines;

        if (empty($rawLines)) {
            $rawLines = [$text];
        }

        $lines = [];
        foreach ($rawLines as $rawLine) {
            $lines = array_merge($lines, $this->wrapLineByWidth($rawLine, $fontFile, $fontSize, $wrapLimit));
        }

        if (empty($lines)) {
            $lines = [$text];
        }

        return $lines;
    }

    /**
     * 按宽度换行
     */
    private function wrapLineByWidth(string $line, string $fontFile, int $fontSize, int $maxWidth): array
    {
        if ($maxWidth <= 0) {
            return [$line];
        }

        $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false || empty($characters)) {
            return [$line];
        }

        $lines = [];
        $current = '';

        foreach ($characters as $char) {
            $candidate = $current . $char;
            $sample = $candidate === '' ? ' ' : $candidate;
            $bbox = imagettfbbox($fontSize, 0, $fontFile, $sample);
            $width = abs($bbox[4] - $bbox[0]);

            if ($width <= $maxWidth || $current === '') {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $char;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (empty($lines)) {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * 计算文本度量
     */
    private function calculateTextMetrics(array $lines, string $fontFile, int $fontSize): array
    {
        $maxWidth = 0;
        $lineHeight = 0;
        $boxes = [];

        foreach ($lines as $line) {
            $sample = $line === '' ? ' ' : $line;
            $bbox = imagettfbbox($fontSize, 0, $fontFile, $sample);
            $width = abs($bbox[4] - $bbox[0]);
            $height = abs($bbox[5] - $bbox[1]);

            $maxWidth = max($maxWidth, $width);
            $lineHeight = max($lineHeight, $height);
            $boxes[] = $bbox;
        }

        if ($lineHeight === 0) {
            $fallbackBox = imagettfbbox($fontSize, 0, $fontFile, '汉');
            $lineHeight = abs($fallbackBox[5] - $fallbackBox[1]) ?: $fontSize;
        }

        $lineSpacing = max(2, (int) ceil($fontSize * 0.25));
        $lineCount = count($lines);
        $totalHeight = ($lineCount * $lineHeight) + max(0, ($lineCount - 1) * $lineSpacing);

        return [
            'max_width' => $maxWidth,
            'line_height' => $lineHeight,
            'line_spacing' => $lineSpacing,
            'total_height' => $totalHeight,
            'boxes' => $boxes,
        ];
    }

    /**
     * 计算起始 X 坐标
     */
    private function calculateStartX(int $canvasSize, int $textWidth, int $padding, string $textAlign, int $posX): int
    {
        $availableWidth = $canvasSize - ($padding * 2);

        return match($textAlign) {
            'left' => $padding,
            'right' => $canvasSize - $padding - $textWidth,
            'center' => ($canvasSize - $textWidth) / 2,
            default => ($canvasSize - $textWidth) / 2 + ($availableWidth * $posX / 100),
        };
    }

    /**
     * 计算起始 Y 坐标
     */
    private function calculateStartY(int $canvasSize, int $textHeight, int $padding, int $posY): int
    {
        return ($canvasSize - $textHeight) / 2 + ($padding * ($posY - 50) / 100);
    }

    /**
     * 填充纯色背景
     */
    private function fillSolid($canvas, string $color): void
    {
        $rgb = ColorHelper::hexToRgb($color);
        $this->renderer->fill($canvas, $rgb, 0);
    }

    /**
     * 绘制背景图片
     */
    private function drawBackgroundImage($canvas, $bgImage, int $width, int $height, int $bgWidth, int $bgHeight, string $bgImageSize): void
    {
        imagealphablending($canvas, false);

        switch ($bgImageSize) {
            case 'cover':
                $ratioW = $width / $bgWidth;
                $ratioH = $height / $bgHeight;
                $ratio = max($ratioW, $ratioH);
                $newW = max(1, (int) ($bgWidth * $ratio));
                $newH = max(1, (int) ($bgHeight * $ratio));
                $xOffset = (int) (($width - $newW) / 2);
                $yOffset = (int) (($height - $newH) / 2);
                $this->renderer->copy($canvas, $bgImage, $xOffset, $yOffset, 0, 0, $bgWidth, $bgHeight, $newW, $newH);
                break;

            case 'contain':
                $ratioW = $width / $bgWidth;
                $ratioH = $height / $bgHeight;
                $ratio = min($ratioW, $ratioH);
                $newW = max(1, (int) ($bgWidth * $ratio));
                $newH = max(1, (int) ($bgHeight * $ratio));
                $xOffset = (int) (($width - $newW) / 2);
                $yOffset = (int) (($height - $newH) / 2);
                $this->renderer->copy($canvas, $bgImage, $xOffset, $yOffset, 0, 0, $bgWidth, $bgHeight, $newW, $newH);
                break;

            case 'stretch':
                $this->renderer->copy($canvas, $bgImage, 0, 0, 0, 0, $bgWidth, $bgHeight, $width, $height);
                break;

            case 'tile':
                for ($x = 0; $x < $width; $x += $bgWidth) {
                    for ($y = 0; $y < $height; $y += $bgHeight) {
                        $copyW = min($bgWidth, $width - $x);
                        $copyH = min($bgHeight, $height - $y);
                        $this->renderer->copy($canvas, $bgImage, $x, $y, 0, 0, $copyW, $copyH, $copyW, $copyH);
                    }
                }
                break;
        }

        imagealphablending($canvas, true);
    }

    private function drawTextLineWithEffects($image, int $fontSize, string $fontPath, string $text, int $x, int $y, array $color, int $alpha, string $effect, int $fontWeight, array $frameParams): void
    {
        $effect = strtolower($effect);

        if ($effect === 'shadow') {
            $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 80);
            imagettftext($image, $fontSize, 0, $x + 2, $y + 2, $shadowColor, $fontPath, $text);
        } elseif ($effect === 'outline') {
            $outlineColor = imagecolorallocate($image, 0, 0, 0);
            for ($i = -1; $i <= 1; $i++) {
                for ($j = -1; $j <= 1; $j++) {
                    if ($i === 0 && $j === 0) {
                        continue;
                    }
                    imagettftext($image, $fontSize, 0, $x + $i, $y + $j, $outlineColor, $fontPath, $text);
                }
            }
        } elseif ($effect === 'glow' || !empty($frameParams['glow_radius'])) {
            $glowRadius = !empty($frameParams['glow_radius']) ? (int) $frameParams['glow_radius'] : 2;
            $glowAlpha = 80;
            $glowColor = imagecolorallocatealpha($image, 255, 255, 255, $glowAlpha);
            for ($i = -$glowRadius; $i <= $glowRadius; $i++) {
                for ($j = -$glowRadius; $j <= $glowRadius; $j++) {
                    imagettftext($image, $fontSize, 0, $x + $i, $y + $j, $glowColor, $fontPath, $text);
                }
            }
        } elseif (in_array($effect, ['marquee', 'flowlight', 'liuguang'], true)) {
            $highlightSteps = 12;
            for ($i = -$highlightSteps; $i <= $highlightSteps; $i++) {
                $highlightAlpha = max(20, min(110, 90 + abs($i) * 4));
                $highlightColor = imagecolorallocatealpha($image, 255, 255, 255, $highlightAlpha);
                $offsetX = $x + $i;
                $offsetY = $y - ($i * 0.4);
                imagettftext($image, $fontSize, 0, (int) $offsetX, (int) $offsetY, $highlightColor, $fontPath, $text);
            }
        }

        if ($fontWeight > 0) {
            $offset = $fontWeight / 10;
            for ($i = -$fontWeight; $i <= $fontWeight; $i++) {
                $ox = $fontWeight === 0 ? 0 : $i * $offset / $fontWeight;
                for ($j = -$fontWeight; $j <= $fontWeight; $j++) {
                    $oy = $fontWeight === 0 ? 0 : $j * $offset / $fontWeight;
                    if ($i === 0 && $j === 0) {
                        continue;
                    }
                    $colorResource = imagecolorallocatealpha($image, $color[0], $color[1], $color[2], $alpha);
                    imagettftext($image, $fontSize, 0, (int) ($x + $ox), (int) ($y + $oy), $colorResource, $fontPath, $text);
                }
            }
        }

        $mainColor = imagecolorallocatealpha($image, $color[0], $color[1], $color[2], $alpha);
        imagettftext($image, $fontSize, 0, $x, $y, $mainColor, $fontPath, $text);
    }

    private function safeHexToRgb(string $hex, array $fallback): array
    {
        try {
            return ColorHelper::hexToRgb($hex);
        } catch (\Throwable $e) {
            return $fallback;
        }
    }
}
