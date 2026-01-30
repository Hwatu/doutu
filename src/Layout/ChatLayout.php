<?php

namespace DouTu\Layout;

use DouTu\Renderer\ImageRenderer;
use DouTu\Utils\ColorHelper;
use DouTu\Utils\PathHelper;

/**
 * Chat bubble layout implementation.
 */
class ChatLayout implements LayoutInterface
{
    private ImageRenderer $renderer;

    public function __construct(ImageRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function prepare(string $text, array $params): LayoutData
    {
        $fontFile = $params['font_file'] ?? '';
        $fontSize = isset($params['font_size']) ? max(12, (int) $params['font_size']) : 32;
        $wrapAuto = $this->toBoolean($params['wrap_auto'] ?? true, true);
        $wrapLimitInput = isset($params['wrap_limit']) ? (int) $params['wrap_limit'] : 0;

        $chatMaxWidth = isset($params['chat_max_width']) ? max(160, (int) $params['chat_max_width']) : 720;
        $chatMinWidth = isset($params['chat_min_width']) ? max(120, (int) $params['chat_min_width']) : 220;
        $chatMinHeight = isset($params['chat_min_height']) ? max(80, (int) $params['chat_min_height']) : 140;

        $padLeftRatio = $this->clampFloat($params['chat_padding_left'] ?? 0.22, 0.0, 0.45, 0.22);
        $padRightRatio = $this->clampFloat($params['chat_padding_right'] ?? 0.22, 0.0, 0.45, 0.22);
        $padTopRatio = $this->clampFloat($params['chat_padding_top'] ?? 0.25, 0.0, 0.45, 0.25);
        $padBottomRatio = $this->clampFloat($params['chat_padding_bottom'] ?? 0.25, 0.0, 0.45, 0.25);

        $horizontalRatio = max(0.05, 1 - ($padLeftRatio + $padRightRatio));
        $verticalRatio = max(0.05, 1 - ($padTopRatio + $padBottomRatio));

        if ($wrapAuto) {
            $effectiveWrapLimit = $wrapLimitInput > 0 ? min($wrapLimitInput, $chatMaxWidth) : $chatMaxWidth;
        } else {
            $effectiveWrapLimit = $wrapLimitInput > 0 ? $wrapLimitInput : 2048;
        }
        $effectiveWrapLimit = max(40, $effectiveWrapLimit);

        $lines = $this->prepareTextLines($text, $fontFile, $fontSize, $effectiveWrapLimit);
        $metrics = $this->calculateTextMetrics($lines, $fontFile, $fontSize);
        $textWidth = max(1, $metrics['max_width']);
        $textHeight = max(1, $metrics['total_height']);
        $lineHeight = $metrics['line_height'];
        $lineSpacing = $metrics['line_spacing'];
        $lineBoxes = $metrics['boxes'];

        $attempts = 0;
        while ($wrapAuto && $textWidth / $horizontalRatio > $chatMaxWidth && $attempts < 4) {
            $effectiveWrapLimit = max(80, (int) ($effectiveWrapLimit * 0.85));
            $lines = $this->prepareTextLines($text, $fontFile, $fontSize, $effectiveWrapLimit);
            $metrics = $this->calculateTextMetrics($lines, $fontFile, $fontSize);
            $textWidth = max(1, $metrics['max_width']);
            $textHeight = max(1, $metrics['total_height']);
            $lineHeight = $metrics['line_height'];
            $lineSpacing = $metrics['line_spacing'];
            $lineBoxes = $metrics['boxes'];
            $attempts++;
        }

        $bubbleWidth = max($chatMinWidth, (int) ceil($textWidth / $horizontalRatio));
        if ($wrapAuto) {
            $bubbleWidth = min($bubbleWidth, max($chatMinWidth, $chatMaxWidth));
        }
        $bubbleHeight = max($chatMinHeight, (int) ceil($textHeight / $verticalRatio));

        $bubbleWidth = min(2048, $bubbleWidth);
        $bubbleHeight = min(2048, $bubbleHeight);

        $paddingLeft = max(8, (int) round($bubbleWidth * $padLeftRatio));
        $paddingRight = max(8, (int) round($bubbleWidth * $padRightRatio));
        $paddingTop = max(8, (int) round($bubbleHeight * $padTopRatio));
        $paddingBottom = max(8, (int) round($bubbleHeight * $padBottomRatio));

        $contentWidth = max(20, $bubbleWidth - $paddingLeft - $paddingRight);
        if ($contentWidth < $textWidth) {
            $bubbleWidth = $textWidth + $paddingLeft + $paddingRight + 6;
            $bubbleWidth = max($bubbleWidth, $chatMinWidth);
            $paddingLeft = max(8, (int) round($bubbleWidth * $padLeftRatio));
            $paddingRight = max(8, (int) round($bubbleWidth * $padRightRatio));
            $contentWidth = max(20, $bubbleWidth - $paddingLeft - $paddingRight);
        }

        $contentHeight = max(20, $bubbleHeight - $paddingTop - $paddingBottom);
        if ($contentHeight < $textHeight) {
            $bubbleHeight = $textHeight + $paddingTop + $paddingBottom + 6;
            $bubbleHeight = max($bubbleHeight, $chatMinHeight);
            $paddingTop = max(8, (int) round($bubbleHeight * $padTopRatio));
            $paddingBottom = max(8, (int) round($bubbleHeight * $padBottomRatio));
            $contentHeight = max(20, $bubbleHeight - $paddingTop - $paddingBottom);
        }

        $bubbleWidth = max($bubbleWidth, $paddingLeft + $paddingRight + $textWidth + 10);
        $bubbleHeight = max($bubbleHeight, $paddingTop + $paddingBottom + $textHeight + 10);
        $bubbleWidth = min(2048, $bubbleWidth);
        $bubbleHeight = min(2048, $bubbleHeight);

        $posX = isset($params['pos_x']) ? max(0, min(100, (float) $params['pos_x'])) : 50;
        $posY = isset($params['pos_y']) ? max(0, min(100, (float) $params['pos_y'])) : 50;

        $contentWidth = max(10, $bubbleWidth - $paddingLeft - $paddingRight);
        $contentHeight = max(10, $bubbleHeight - $paddingTop - $paddingBottom);

        $blockLeft = $paddingLeft;
        if ($contentWidth > $textWidth) {
            if ($posX == 50) {
                $blockLeft = $paddingLeft + ($contentWidth - $textWidth) / 2;
            } elseif ($posX == 100) {
                $blockLeft = $paddingLeft + $contentWidth - $textWidth;
            } elseif ($posX == 0) {
                $blockLeft = $paddingLeft;
            } else {
                $blockLeft = $paddingLeft + ($contentWidth - $textWidth) * ($posX / 100);
            }
        }

        $blockTop = $paddingTop;
        if ($contentHeight > $textHeight) {
            if ($posY == 50) {
                $blockTop = $paddingTop + ($contentHeight - $textHeight) / 2;
            } elseif ($posY == 100) {
                $blockTop = $paddingTop + $contentHeight - $textHeight;
            } elseif ($posY == 0) {
                $blockTop = $paddingTop;
            } else {
                $blockTop = $paddingTop + ($contentHeight - $textHeight) * ($posY / 100);
            }
        }

        return new LayoutData(
            width: $bubbleWidth,
            height: $bubbleHeight,
            lines: $lines,
            textWidth: $textWidth,
            textHeight: $textHeight,
            lineHeight: $lineHeight,
            startX: (int) $blockLeft,
            startY: (int) $blockTop,
            fontInfo: [
                'path' => $fontFile,
                'size' => $fontSize,
                'line_spacing' => $lineSpacing,
            ],
            extra: [
                'padding_left' => $paddingLeft,
                'padding_right' => $paddingRight,
                'padding_top' => $paddingTop,
                'padding_bottom' => $paddingBottom,
                'content_width' => $contentWidth,
                'content_height' => $contentHeight,
                'line_boxes' => $lineBoxes,
            ]
        );
    }

    public function drawBackground($canvas, array $params): void
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);

        $bgDefault = __DIR__ . '/../../img/background.png';
        $chatBgPath = $this->resolveImagePath($params['chat_bg'] ?? '', $bgDefault);
        $chatMirror = $this->toBoolean($params['chat_mirror'] ?? false, false);

        $slice = [
            'x_start' => $this->clampFloat($params['chat_slice_x_start'] ?? 0.35, 0.05, 0.95, 0.35),
            'x_end' => $this->clampFloat($params['chat_slice_x_end'] ?? 0.65, 0.05, 1.0, 0.65),
            'y_start' => $this->clampFloat($params['chat_slice_y_start'] ?? 0.35, 0.05, 0.95, 0.35),
            'y_end' => $this->clampFloat($params['chat_slice_y_end'] ?? 0.65, 0.05, 1.0, 0.65),
        ];

        if ($slice['x_end'] <= $slice['x_start']) {
            $slice['x_end'] = min(0.95, $slice['x_start'] + 0.1);
        }
        if ($slice['y_end'] <= $slice['y_start']) {
            $slice['y_end'] = min(0.95, $slice['y_start'] + 0.1);
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        $drawn = $this->drawNinePatch($canvas, $chatBgPath, $width, $height, $slice, $chatMirror);
        if (!$drawn) {
            $fallback = imagecolorallocatealpha($canvas, 255, 238, 200, 0);
            imagefill($canvas, 0, 0, $fallback);
        }

        imagealphablending($canvas, true);
    }

    public function drawText($canvas, LayoutData $layout, array $params, array $frameParams = []): void
    {
        $font = $layout->fontInfo;
        $fontFile = $font['path'];
        $fontSize = $font['size'];
        $lineSpacing = $font['line_spacing'];

        $colorSetting = $params['font_color'] ?? '#000000';
        $randomColor = $this->toBoolean($params['random_color'] ?? false, false);

        if ($randomColor) {
            $color = [mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)];
        } else {
            $color = $this->safeHexToRgb($colorSetting, [0, 0, 0]);
        }

        $textColor = $color;
        $alpha = 0;
        if (!empty($frameParams['color'])) {
            $textColor = $frameParams['color'];
        }
        if (!empty($frameParams['alpha'])) {
            $alpha = (int) $frameParams['alpha'];
        }

        $offsetX = $frameParams['offset_x'] ?? 0;
        $offsetY = $frameParams['offset_y'] ?? 0;

        $blockLeft = $layout->startX + $offsetX;
        $blockTop = $layout->startY + $offsetY;
        $blockBaseline = $blockTop + $layout->lineHeight;

        $lineBoxes = $layout->extra['line_boxes'] ?? [];
        $effect = $params['effect'] ?? 'none';
        $fontWeight = isset($params['font_weight']) ? (int) $params['font_weight'] : 0;

        foreach ($layout->lines as $index => $line) {
            $lineText = $line === '' ? ' ' : $line;
            $lineBox = $lineBoxes[$index] ?? imagettfbbox($fontSize, 0, $fontFile, $lineText);
            $lineX = $blockLeft - $lineBox[0];
            $lineY = $blockBaseline + $index * ($layout->lineHeight + $lineSpacing);

            $this->drawTextLineWithEffects(
                $canvas,
                $fontSize,
                $fontFile,
                $lineText,
                (int) $lineX,
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
        $width = isset($params['chat_max_width']) ? max(160, (int) $params['chat_max_width']) : 720;
        $height = isset($params['chat_min_height']) ? max(80, (int) $params['chat_min_height']) : 140;
        return ['width' => $width, 'height' => $height];
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
            $glowAlpha = 80;
            $glowRadius = !empty($frameParams['glow_radius']) ? (int) $frameParams['glow_radius'] : 2;
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

    private function prepareTextLines(string $text, string $fontFile, int $fontSize, int $maxWidth): array
    {
        $rawLines = preg_split("/\\r\\n|\\r|\\n/", $text);
        $rawLines = $rawLines === false ? [] : $rawLines;

        if (empty($rawLines)) {
            $rawLines = [$text];
        }

        $lines = [];
        foreach ($rawLines as $rawLine) {
            $lines = array_merge($lines, $this->wrapLineByWidth($rawLine, $fontFile, $fontSize, $maxWidth));
        }

        if (empty($lines)) {
            $lines = [$text];
        }

        return $lines;
    }

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

    private function toBoolean($value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        $trueValues = ['1', 'true', 'on', 'yes', 'y'];
        $falseValues = ['0', 'false', 'off', 'no', 'n'];

        if (in_array($normalized, $trueValues, true)) {
            return true;
        }
        if (in_array($normalized, $falseValues, true)) {
            return false;
        }

        return $default;
    }

    private function clampFloat($value, float $min, float $max, float $default): float
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $float = (float) $value;
        if ($float < $min) {
            return $min;
        }
        if ($float > $max) {
            return $max;
        }
        return $float;
    }

    private function resolveImagePath(string $path, string $default): string
    {
        $path = trim($path);
        if ($path === '') {
            return $default;
        }

        if (PathHelper::isAbsolutePath($path) && is_readable($path)) {
            return $path;
        }

        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');
        $candidate = realpath(__DIR__ . '/../../' . $normalized);
        $root = realpath(__DIR__ . '/../../');

        if ($candidate !== false && $root && str_starts_with($candidate, $root) && is_readable($candidate)) {
            return $candidate;
        }

        return $default;
    }

    private function loadImageResource(string $path)
    {
        if (!is_readable($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        return match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => null,
        };
    }

    private function mirrorImageHorizontally($image)
    {
        if (function_exists('imageflip')) {
            @imageflip($image, IMG_FLIP_HORIZONTAL);
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $mirrored = imagecreatetruecolor($width, $height);
        imagealphablending($mirrored, false);
        imagesavealpha($mirrored, true);

        for ($x = 0; $x < $width; $x++) {
            imagecopy($mirrored, $image, $width - $x - 1, 0, $x, 0, 1, $height);
        }

        return $mirrored;
    }

    private function drawNinePatch($target, string $bgPath, int $targetWidth, int $targetHeight, array $slice, bool $mirror = false): bool
    {
        $source = $this->loadImageResource($bgPath);
        if (!$source) {
            return false;
        }

        if ($mirror) {
            $mirrored = $this->mirrorImageHorizontally($source);
            if ($mirrored !== $source) {
                imagedestroy($source);
                $source = $mirrored;
            }
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($source);
            return false;
        }

        $xStart = $this->clampFloat($slice['x_start'] ?? 0.35, 0.0, 0.95, 0.35);
        $xEnd = $this->clampFloat($slice['x_end'] ?? 0.65, 0.0, 1.0, 0.65);
        $yStart = $this->clampFloat($slice['y_start'] ?? 0.35, 0.0, 0.95, 0.35);
        $yEnd = $this->clampFloat($slice['y_end'] ?? 0.65, 0.0, 1.0, 0.65);

        if ($xEnd <= $xStart) {
            $xEnd = min(0.9, $xStart + 0.1);
        }
        if ($yEnd <= $yStart) {
            $yEnd = min(0.9, $yStart + 0.1);
        }

        $srcLeft = (int) round($xStart * $srcW);
        $srcRight = (int) round($xEnd * $srcW);
        $srcTop = (int) round($yStart * $srcH);
        $srcBottom = (int) round($yEnd * $srcH);

        $leftWidth = $srcLeft;
        $rightWidth = $srcW - $srcRight;
        $topHeight = $srcTop;
        $bottomHeight = $srcH - $srcBottom;

        if ($targetWidth < ($leftWidth + $rightWidth) || $targetHeight < ($topHeight + $bottomHeight)) {
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcW, $srcH);
            imagedestroy($source);
            return true;
        }

        $destStretchWidth = max(0, $targetWidth - ($leftWidth + $rightWidth));
        $destStretchHeight = max(0, $targetHeight - ($topHeight + $bottomHeight));

        $srcX = [0, $srcLeft, $srcRight, $srcW];
        $srcY = [0, $srcTop, $srcBottom, $srcH];
        $destX = [0, $leftWidth, $leftWidth + $destStretchWidth, $targetWidth];
        $destY = [0, $topHeight, $topHeight + $destStretchHeight, $targetHeight];

        imagealphablending($target, false);
        imagesavealpha($target, true);

        for ($iy = 0; $iy < 3; $iy++) {
            for ($ix = 0; $ix < 3; $ix++) {
                $srcWidth = $srcX[$ix + 1] - $srcX[$ix];
                $srcHeight = $srcY[$iy + 1] - $srcY[$iy];
                $dstWidth = $destX[$ix + 1] - $destX[$ix];
                $dstHeight = $destY[$iy + 1] - $destY[$iy];

                if ($srcWidth <= 0 || $srcHeight <= 0 || $dstWidth <= 0 || $dstHeight <= 0) {
                    continue;
                }

                imagecopyresampled(
                    $target,
                    $source,
                    $destX[$ix],
                    $destY[$iy],
                    $srcX[$ix],
                    $srcY[$iy],
                    $dstWidth,
                    $dstHeight,
                    $srcWidth,
                    $srcHeight
                );
            }
        }

        imagealphablending($target, true);
        imagedestroy($source);
        return true;
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
