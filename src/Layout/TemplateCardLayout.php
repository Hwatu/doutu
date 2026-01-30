<?php

namespace DouTu\Layout;

use DouTu\Renderer\ImageRenderer;
use DouTu\Utils\ColorHelper;
use DouTu\Utils\PathHelper;

/**
 * Template card layout implementation.
 */
class TemplateCardLayout implements LayoutInterface
{
    private ImageRenderer $renderer;

    public function __construct(ImageRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function prepare(string $text, array $params): LayoutData
    {
        $fontFile = $params['font_file'] ?? '';
        if ($fontFile === '' || !is_readable($fontFile)) {
            throw new \RuntimeException('模板卡片布局需要可用字体');
        }

        $defaultTemplate = __DIR__ . '/../../template.jpeg';
        $templatePath = $this->resolveImagePath($params['template_path'] ?? $defaultTemplate, $defaultTemplate);

        $imageInfo = @getimagesize($templatePath);
        if ($imageInfo === false) {
            throw new \RuntimeException('模板背景图片不可用');
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        $padLeft = $this->normalizePadding($params['template_pad_left'] ?? null, $width, 0.08);
        $padRight = $this->normalizePadding($params['template_pad_right'] ?? null, $width, 0.08);
        $padTop = $this->normalizePadding($params['template_pad_top'] ?? null, $height, 0.18);
        $padBottom = $this->normalizePadding($params['template_pad_bottom'] ?? null, $height, 0.12);

        $textBoxWidth = max(20, $width - $padLeft - $padRight);
        $textBoxHeight = max(20, $height - $padTop - $padBottom);
        if ($textBoxWidth <= 0 || $textBoxHeight <= 0) {
            throw new \RuntimeException('模板卡片文本区域无效');
        }

        $minFont = isset($params['template_font_min']) ? max(12, (int) $params['template_font_min']) : 42;
        $maxFont = isset($params['template_font_max']) ? max($minFont, (int) $params['template_font_max']) : 150;

        $normalizedText = trim((string) $text);
        if ($normalizedText === '') {
            $normalizedText = ' ';
        }

        $bestLayout = null;
        $bestSize = $minFont;
        $low = $minFont;
        $high = $maxFont;

        while ($low <= $high) {
            $mid = (int) (($low + $high) / 2);
            $lines = $this->prepareTextLines($normalizedText, $fontFile, $mid, $textBoxWidth);
            $metrics = $this->calculateTextMetrics($lines, $fontFile, $mid);
            if ($metrics['max_width'] <= $textBoxWidth && $metrics['total_height'] <= $textBoxHeight) {
                $bestSize = $mid;
                $bestLayout = [$lines, $metrics];
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        if ($bestLayout === null) {
            $bestSize = $minFont;
            $fallbackLines = $this->prepareTextLines($normalizedText, $fontFile, $bestSize, $textBoxWidth);
            $bestLayout = [$fallbackLines, $this->calculateTextMetrics($fallbackLines, $fontFile, $bestSize)];
        }

        $lines = $bestLayout[0];
        $metrics = $bestLayout[1];

        return new LayoutData(
            width: $width,
            height: $height,
            lines: $lines,
            textWidth: $metrics['max_width'],
            textHeight: $metrics['total_height'],
            lineHeight: $metrics['line_height'],
            startX: $padLeft,
            startY: $padTop,
            fontInfo: [
                'path' => $fontFile,
                'size' => $bestSize,
                'line_spacing' => $metrics['line_spacing'],
            ],
            extra: [
                'template_path' => $templatePath,
                'pad_left' => $padLeft,
                'pad_right' => $padRight,
                'pad_top' => $padTop,
                'pad_bottom' => $padBottom,
                'text_box_width' => $textBoxWidth,
                'text_box_height' => $textBoxHeight,
                'line_boxes' => $metrics['boxes'],
            ]
        );
    }

    public function drawBackground($canvas, array $params): void
    {
        $defaultTemplate = __DIR__ . '/../../template.jpeg';
        $templatePath = $this->resolveImagePath($params['template_path'] ?? $defaultTemplate, $defaultTemplate);
        $image = $this->loadImageResource($templatePath);

        if ($image) {
            $width = imagesx($image);
            $height = imagesy($image);
            imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
            imagedestroy($image);
        }
    }

    public function drawText($canvas, LayoutData $layout, array $params, array $frameParams = []): void
    {
        $font = $layout->fontInfo;
        $fontFile = $font['path'];
        $fontSize = $font['size'];
        $lineSpacing = $font['line_spacing'];

        $padLeft = (int) ($layout->extra['pad_left'] ?? 0);
        $textBoxWidth = (int) ($layout->extra['text_box_width'] ?? $layout->width);
        $lineBoxes = $layout->extra['line_boxes'] ?? [];

        $metricsHeight = $layout->textHeight;
        $lineHeight = $layout->lineHeight;
        $startY = $layout->startY + (int) (($layout->extra['text_box_height'] ?? $layout->height) - $metricsHeight) / 2 + $lineHeight;

        $baseColorHex = $params['template_text_color'] ?? ($params['font_color'] ?? '#111111');
        $fillColor = $this->parseColorOrDefault($baseColorHex, '#111111');
        $outlineColor = $this->adjustColorLevels($fillColor, -0.35);
        $highlightColor = $this->adjustColorLevels($fillColor, 0.5);
        $shadowColor = $this->parseColorOrDefault($params['template_shadow_color'] ?? '#000000', '#000000');

        $shadowOffsetX = isset($params['template_shadow_offset_x']) ? (int) $params['template_shadow_offset_x'] : 4;
        $shadowOffsetY = isset($params['template_shadow_offset_y']) ? (int) $params['template_shadow_offset_y'] : 5;
        $shadowAlpha = isset($params['template_shadow_alpha']) ? max(0, min(127, (int) $params['template_shadow_alpha'])) : 90;
        $highlightAlpha = isset($params['template_highlight_alpha']) ? max(0, min(127, (int) $params['template_highlight_alpha'])) : 60;
        $highlightOffset = max(1, (int) round($fontSize * 0.08));
        $strokeRatio = 0.035;

        foreach ($layout->lines as $index => $line) {
            $lineText = $line === '' ? ' ' : $line;
            $lineBox = $lineBoxes[$index] ?? imagettfbbox($fontSize, 0, $fontFile, $lineText);
            $lineWidth = abs($lineBox[4] - $lineBox[0]);
            $lineX = $padLeft + max(0, ($textBoxWidth - $lineWidth) / 2) - $lineBox[0];
            $lineY = $startY + $index * ($lineHeight + $lineSpacing);

            $this->drawTemplateTextLine($canvas, $fontSize, $fontFile, $lineText, (int) $lineX, (int) $lineY, [
                'fill_color' => $fillColor,
                'outline_color' => $outlineColor,
                'shadow_color' => $shadowColor,
                'highlight_color' => $highlightColor,
                'shadow_offset_x' => $shadowOffsetX,
                'shadow_offset_y' => $shadowOffsetY,
                'shadow_alpha' => $shadowAlpha,
                'highlight_alpha' => $highlightAlpha,
                'highlight_offset' => $highlightOffset,
                'stroke_ratio' => $strokeRatio,
            ]);
        }
    }

    public function getCanvasSize(array $params): array
    {
        $defaultTemplate = __DIR__ . '/../../template.jpeg';
        $templatePath = $this->resolveImagePath($params['template_path'] ?? $defaultTemplate, $defaultTemplate);
        $imageInfo = @getimagesize($templatePath);
        if ($imageInfo === false) {
            return ['width' => 1024, 'height' => 1024];
        }

        return ['width' => $imageInfo[0], 'height' => $imageInfo[1]];
    }

    private function drawTemplateTextLine($image, int $fontSize, string $fontPath, string $text, int $x, int $y, array $style): void
    {
        $shadowColor = $this->allocateColor($image, $style['shadow_color'] ?? [0, 0, 0], $style['shadow_alpha'] ?? 90);
        imagettftext(
            $image,
            $fontSize,
            0,
            $x + ($style['shadow_offset_x'] ?? 4),
            $y + ($style['shadow_offset_y'] ?? 5),
            $shadowColor,
            $fontPath,
            $text
        );

        $strokeWidth = max(1, (int) round($fontSize * ($style['stroke_ratio'] ?? 0.035)));
        $outlineColor = $this->allocateColor($image, $style['outline_color'] ?? [0, 0, 0], 0);
        for ($dx = -$strokeWidth; $dx <= $strokeWidth; $dx++) {
            for ($dy = -$strokeWidth; $dy <= $strokeWidth; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }
                imagettftext($image, $fontSize, 0, $x + $dx, $y + $dy, $outlineColor, $fontPath, $text);
            }
        }

        $highlightColor = $this->allocateColor($image, $style['highlight_color'] ?? [255, 255, 255], $style['highlight_alpha'] ?? 60);
        $highlightOffset = (int) ($style['highlight_offset'] ?? 2);
        imagettftext($image, $fontSize, 0, $x - $highlightOffset, $y - $highlightOffset, $highlightColor, $fontPath, $text);

        $fillColor = $this->allocateColor($image, $style['fill_color'] ?? [255, 255, 255], 0);
        imagettftext($image, $fontSize, 0, $x, $y, $fillColor, $fontPath, $text);
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

    private function normalizePadding($value, int $base, float $defaultRatio): int
    {
        if ($value === null || $value === '') {
            return (int) round($base * $defaultRatio);
        }

        if (!is_numeric($value)) {
            return (int) round($base * $defaultRatio);
        }

        $float = (float) $value;
        if ($float > 0 && $float <= 1) {
            return (int) round($base * $float);
        }

        return (int) max(0, min($base - 1, $float));
    }

    private function parseColorOrDefault(string $hex, string $fallback): array
    {
        try {
            return ColorHelper::hexToRgb($hex);
        } catch (\Throwable $e) {
            return ColorHelper::hexToRgb($fallback);
        }
    }

    private function adjustColorLevels(array $rgb, float $factor): array
    {
        $factor = max(-1.0, min(1.0, $factor));
        $r = (int) max(0, min(255, $rgb[0] + ($factor * 255)));
        $g = (int) max(0, min(255, $rgb[1] + ($factor * 255)));
        $b = (int) max(0, min(255, $rgb[2] + ($factor * 255)));
        return [$r, $g, $b];
    }

    private function allocateColor($image, array $rgb, int $alpha): int
    {
        return imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], $alpha);
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
}
