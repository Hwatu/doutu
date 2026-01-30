<?php

namespace DouTu\Core;

use DouTu\Animation\BackgroundAnimation;
use DouTu\Animation\TextAnimation;
use DouTu\Cache\FileCache;
use DouTu\Layout\ChatLayout;
use DouTu\Layout\LayoutInterface;
use DouTu\Layout\StandardLayout;
use DouTu\Layout\TemplateCardLayout;
use DouTu\Output\OutputFactory;
use DouTu\Output\OutputInterface;
use DouTu\Renderer\ImageRenderer;
use DouTu\Utils\PathHelper;

class ImageGenerator
{
    private Config $config;
    private ImageRenderer $renderer;
    private ?FileCache $cache;

    public function __construct(Config $config, ImageRenderer $renderer, ?FileCache $cache = null)
    {
        $this->config = $config;
        $this->renderer = $renderer;
        $this->cache = $cache;
    }

    public function generate(string $text, array $params = []): string
    {
        $params = $this->normalizeParams($params);
        $this->maybeCleanupOutputs();

        $format = $params['format'];
        $layoutMode = $params['layout_mode'];

        $fontName = $params['font_file'] ?? $params['font'] ?? $params['font_family'] ?? '';
        $fontPath = $this->resolveFontPath($fontName);
        if ($fontPath === null) {
            throw new \RuntimeException('未找到可用字体');
        }
        $params['font_file'] = $fontPath;

        $output = $this->resolveOutput($format);
        $format = $output->getFormat();
        $params['format'] = $format;

        $layout = $this->createLayout($layoutMode);
        $layoutData = $layout->prepare($text, $params);
        $width = $layoutData->width;
        $height = $layoutData->height;

        $animationType = strtolower((string) ($params['animation'] ?? Constants::ANIMATION_NONE));
        $bgAnimationType = strtolower((string) ($params['bg_animation'] ?? Constants::BG_ANIMATION_NONE));

        $isAnimated = ($animationType !== Constants::ANIMATION_NONE || $bgAnimationType !== Constants::BG_ANIMATION_NONE)
            && $output->supportsAnimation();

        $filename = $this->buildFilename($text, $params, $format);
        $outputDir = $this->config->get('paths.output', __DIR__ . '/../../output');
        PathHelper::ensureDirectory($outputDir);
        $outputPath = rtrim($outputDir, '/') . '/' . $filename;

        if (is_file($outputPath)) {
            return $outputPath;
        }

        if ($isAnimated) {
            $this->renderAnimated($output, $layout, $layoutData, $params, $outputPath, $width, $height);
            return $outputPath;
        }

        $canvas = $this->renderer->createCanvas($width, $height);
        $layout->drawBackground($canvas, $params);
        $layout->drawText($canvas, $layoutData, $params, []);

        $saved = $output->save($canvas, $outputPath, $this->getQualityOptions($format));
        $this->renderer->destroy($canvas);

        if (!$saved) {
            throw new \RuntimeException('保存输出失败');
        }

        return $outputPath;
    }

    private function renderAnimated(OutputInterface $output, LayoutInterface $layout, $layoutData, array $params, string $outputPath, int $width, int $height): void
    {
        $frameCount = $this->normalizeFrameCount($params['frame_count'] ?? $params['frameCount'] ?? $this->config->get('animation.default_frame_count', Constants::DEFAULT_FRAME_COUNT));
        $frameDelay = $this->normalizeFrameDelay($params['frame_delay'] ?? $params['frameDelay'] ?? $this->config->get('animation.default_frame_delay', Constants::DEFAULT_FRAME_DELAY));
        $loopCount = $this->normalizeLoopCount($params['loop_count'] ?? $params['loopCount'] ?? $this->config->get('animation.default_loop_count', Constants::DEFAULT_LOOP_COUNT));

        $animationType = strtolower((string) ($params['animation'] ?? Constants::ANIMATION_NONE));
        $bgAnimationType = strtolower((string) ($params['bg_animation'] ?? Constants::BG_ANIMATION_NONE));

        $bgFrames = null;
        if ($bgAnimationType !== Constants::BG_ANIMATION_NONE) {
            $bgParams = [
                'start_color' => $params['bgAnimStartColor'] ?? $params['bg_anim_start_color'] ?? '#FF0000',
                'end_color' => $params['bgAnimEndColor'] ?? $params['bg_anim_end_color'] ?? '#0000FF',
                'direction' => $params['bgAnimDirection'] ?? $params['bg_anim_direction'] ?? 'horizontal',
            ];
            $bgAnimation = new BackgroundAnimation($bgAnimationType, $frameCount, $bgParams, $this->renderer);
            $bgFrames = $bgAnimation->generateFrames($width, $height);
        }

        $frames = [];
        if ($animationType !== Constants::ANIMATION_NONE) {
            $textParams = [
                'base_color' => $params['animBaseColor'] ?? $params['animation_base_color'] ?? '#FFFFFF',
                'highlight_color' => $params['animHighlightColor'] ?? $params['animation_highlight_color'] ?? '#FFFF00',
                'intensity' => $params['shakeIntensity'] ?? 3,
                'amplitude' => $params['bounceAmplitude'] ?? 10,
            ];
            $textAnimation = new TextAnimation($animationType, $frameCount, $textParams, $this->renderer);
            $frames = $textAnimation->generateFrames(function ($canvas, $frameParams, $frameIndex) use ($layout, $layoutData, $params, $bgFrames, $width, $height) {
                if ($bgFrames) {
                    $this->renderer->copy($canvas, $bgFrames[$frameIndex], 0, 0, 0, 0, $width, $height, $width, $height);
                } else {
                    $layout->drawBackground($canvas, $params);
                }
                $layout->drawText($canvas, $layoutData, $params, $frameParams);
            }, $width, $height);
        } else {
            for ($i = 0; $i < $frameCount; $i++) {
                $canvas = $this->renderer->createCanvas($width, $height);
                if ($bgFrames) {
                    $this->renderer->copy($canvas, $bgFrames[$i], 0, 0, 0, 0, $width, $height, $width, $height);
                } else {
                    $layout->drawBackground($canvas, $params);
                }
                $layout->drawText($canvas, $layoutData, $params, []);
                $frames[] = $canvas;
            }
        }

        $saved = $output->saveAnimated($frames, $outputPath, array_merge($this->getQualityOptions($output->getFormat()), [
            'delay' => $frameDelay,
            'loop' => $loopCount,
        ]));

        foreach ($frames as $frame) {
            $this->renderer->destroy($frame);
        }

        if (is_array($bgFrames)) {
            foreach ($bgFrames as $frame) {
                $this->renderer->destroy($frame);
            }
        }

        if (!$saved) {
            throw new \RuntimeException('保存动画失败');
        }
    }

    private function normalizeParams(array $params): array
    {
        $defaults = $this->config->get('defaults', []);
        $params['format'] = strtolower((string) ($params['format'] ?? $defaults['format'] ?? Constants::FORMAT_PNG));
        $params['layout_mode'] = $params['layout_mode'] ?? $params['layout'] ?? Constants::LAYOUT_STANDARD;
        $params['canvas_size'] = $params['canvas_size'] ?? $defaults['canvas_size'] ?? Constants::DEFAULT_CANVAS_SIZE;
        $params['padding'] = $params['padding'] ?? $defaults['padding'] ?? Constants::DEFAULT_PADDING;
        $params['font_size'] = $params['font_size'] ?? $defaults['font_size'] ?? Constants::DEFAULT_FONT_SIZE;
        $params['font_color'] = $params['font_color'] ?? $defaults['font_color'] ?? '#000000';
        $params['bg_color'] = $params['bg_color'] ?? $defaults['bg_color'] ?? '#FFFFFF';
        $params['random_color'] = $this->toBoolean($params['random_color'] ?? false, false);
        $params['auto_size'] = $this->toBoolean($params['auto_size'] ?? true, true);
        $params['wrap_auto'] = $this->toBoolean($params['wrap_auto'] ?? true, true);

        return $params;
    }

    private function resolveOutput(string $format): OutputInterface
    {
        $format = strtolower($format);
        if (!Constants::isFormatSupported($format)) {
            $format = Constants::FORMAT_PNG;
        }

        $output = OutputFactory::create($format);
        if (!$output->isSupported()) {
            $output = OutputFactory::create(Constants::FORMAT_PNG);
        }

        return $output;
    }

    private function createLayout(string $layoutMode): LayoutInterface
    {
        return match (strtolower($layoutMode)) {
            Constants::LAYOUT_CHAT => new ChatLayout($this->renderer),
            Constants::LAYOUT_TEMPLATE_CARD => new TemplateCardLayout($this->renderer),
            default => new StandardLayout($this->renderer),
        };
    }

    private function resolveFontPath(string $fontName): ?string
    {
        $fontName = trim((string) $fontName);
        $fontsPath = rtrim((string) $this->config->get('paths.fonts', __DIR__ . '/../../fonts'), '/');
        $candidates = [];

        if ($fontName !== '') {
            if (PathHelper::isAbsolutePath($fontName)) {
                $candidates[] = $fontName;
            } else {
                $candidates[] = $fontsPath . '/' . $fontName;
            }
        }

        $fallbacks = $this->config->get('fonts.fallbacks', []);
        foreach ($fallbacks as $fallback) {
            $fallback = trim((string) $fallback);
            if ($fallback === '' || $fallback === $fontName) {
                continue;
            }
            if (PathHelper::isAbsolutePath($fallback)) {
                $candidates[] = $fallback;
            } else {
                $candidates[] = $fontsPath . '/' . $fallback;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildFilename(string $text, array $params, string $format): string
    {
        $hashParams = $params;
        $this->sortRecursive($hashParams);
        $seed = $text . json_encode($hashParams, JSON_UNESCAPED_UNICODE);
        return md5($seed) . '.' . $format;
    }

    private function sortRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
    }

    private function normalizeFrameCount($value): int
    {
        $count = (int) $value;
        $count = max(Constants::MIN_FRAME_COUNT, min(Constants::MAX_FRAME_COUNT, $count));
        return $count ?: Constants::DEFAULT_FRAME_COUNT;
    }

    private function normalizeFrameDelay($value): int
    {
        $delay = (int) $value;
        return max(Constants::MIN_FRAME_DELAY, min(Constants::MAX_FRAME_DELAY, $delay));
    }

    private function normalizeLoopCount($value): int
    {
        $loop = (int) $value;
        return max(0, $loop);
    }

    private function getQualityOptions(string $format): array
    {
        $qualityMap = $this->config->get('output.quality', []);
        $quality = $qualityMap[$format] ?? null;
        return $quality !== null ? ['quality' => $quality] : [];
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

    private function maybeCleanupOutputs(): void
    {
        $cleanup = $this->config->get('cleanup', []);
        if (!is_array($cleanup) || empty($cleanup['enabled'])) {
            return;
        }

        $outputDir = $this->config->get('paths.output', __DIR__ . '/../../output');
        if (!is_dir($outputDir) || !is_writable($outputDir)) {
            return;
        }

        $ttlMinutes = isset($cleanup['ttl_minutes']) ? (int) $cleanup['ttl_minutes'] : 1440;
        $intervalMinutes = isset($cleanup['interval_minutes']) ? (int) $cleanup['interval_minutes'] : 60;

        $ttlMinutes = max(1, $ttlMinutes);
        $intervalMinutes = max(5, $intervalMinutes);

        $stateFile = rtrim($outputDir, '/') . '/.cleanup_state.json';
        $state = [
            'last_run' => 0,
            'ttl_minutes' => $ttlMinutes,
        ];

        if (is_file($stateFile)) {
            $content = @file_get_contents($stateFile);
            $decoded = $content ? json_decode($content, true) : null;
            if (is_array($decoded)) {
                $state = array_merge($state, $decoded);
            }
        }

        $now = time();
        $elapsed = $now - (int) ($state['last_run'] ?? 0);
        $ttlChanged = (int) ($state['ttl_minutes'] ?? 0) !== $ttlMinutes;
        if ($elapsed < ($intervalMinutes * 60) && !$ttlChanged) {
            return;
        }

        $ttlSeconds = $ttlMinutes * 60;
        $deleted = 0;

        $iterator = new \DirectoryIterator($outputDir);
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isFile()) {
                continue;
            }
            $basename = $fileInfo->getBasename();
            if ($basename[0] === '.') {
                continue;
            }

            $age = $now - $fileInfo->getMTime();
            if ($age > $ttlSeconds) {
                @unlink($fileInfo->getPathname());
                $deleted++;
            }
        }

        $state['last_run'] = $now;
        $state['ttl_minutes'] = $ttlMinutes;
        $state['deleted_last_run'] = $deleted;

        @file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
