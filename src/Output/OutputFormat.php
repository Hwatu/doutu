<?php

namespace DouTu\Output;

/**
 * 输出格式类
 * 处理不同格式的图片输出
 */
class OutputFormat
{
    /**
     * 保存 PNG 格式
     *
     * @param mixed $image 图片资源
     * @param string $path 保存路径
     * @param int $quality 质量 (0-9)
     * @return bool
     */
    public static function savePNG($image, string $path, int $quality = 9): bool
    {
        if (!function_exists('imagepng')) {
            return false;
        }
        return imagepng($image, $path, $quality);
    }

    /**
     * 保存 GIF 格式（静态）
     *
     * @param mixed $image 图片资源
     * @param string $path 保存路径
     * @return bool
     */
    public static function saveGIF($image, string $path): bool
    {
        if (!function_exists('imagegif')) {
            return false;
        }
        return imagegif($image, $path);
    }

    /**
     * 保存 GIF 动画
     *
     * @param array $frames 帧数组
     * @param string $path 保存路径
     * @param int $delay 每帧延迟（毫秒）
     * @param int $loopCount 循环次数（0 = 无限）
     * @return bool
     */
    public static function saveAnimatedGIF(array $frames, string $path, int $delay = 100, int $loopCount = 0): bool
    {
        $encoder = new \DouTu\Animation\GIFEncoder($delay, $loopCount);

        try {
            $encoder->addFrames($frames);
            return $encoder->save($path);
        } catch (\Exception $e) {
            error_log('GIF 动画保存失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 保存 WebP 格式（静态）
     *
     * @param mixed $image 图片资源
     * @param string $path 保存路径
     * @param int $quality 质量 (0-100)
     * @return bool
     */
    public static function saveWebP($image, string $path, int $quality = 80): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }
        return imagewebp($image, $path, $quality);
    }

    /**
     * 保存 WebP 动画
     *
     * @param array $frames 帧数组
     * @param string $path 保存路径
     * @param int $delay 每帧延迟（毫秒）
     * @param int $quality 质量 (0-100)
     * @return bool
     */
    public static function saveAnimatedWebP(array $frames, string $path, int $delay = 100, int $quality = 80): bool
    {
        // WebP 动画需要 ImageMagick
        $delayArg = $delay;

        $tempFiles = [];
        try {
            foreach ($frames as $i => $frame) {
                $tempFile = sys_get_temp_dir() . '/frame_' . $i . '.webp';
                imagewebp($frame, $tempFile, $quality);
                $tempFiles[] = $tempFile;
            }

            $files = implode(' ', array_map('escapeshellarg', $tempFiles));

            // 使用 ImageMagick 创建 WebP 动画
            $command = "convert -delay {$delayArg} -loop 0 {$files} " . escapeshellarg($path);

            $output = [];
            $returnCode = 0;

            exec($command . ' 2>&1', $output, $returnCode);

            $this->cleanupTempFiles($tempFiles);

            return $returnCode === 0 && file_exists($path);
        } catch (\Exception $e) {
            $this->cleanupTempFiles($tempFiles);
            error_log('WebP 动画保存失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 保存 AVIF 格式
     *
     * @param mixed $image 图片资源
     * @param string $path 保存路径
     * @param int $quality 质量 (0-100)
     * @return bool
     */
    public static function saveAVIF($image, string $path, int $quality = 80): bool
    {
        if (!function_exists('imageavif')) {
            // 降级到 PNG
            return self::savePNG($image, str_replace('.avif', '.png', $path), 9);
        }
        return imageavif($image, $path, $quality);
    }

    /**
     * 检查格式是否支持动画
     *
     * @param string $format 格式
     * @return bool
     */
    public static function supportsAnimation(string $format): bool
    {
        return in_array(strtolower($format), ['gif', 'webp'], true);
    }

    /**
     * 检查格式是否支持
     *
     * @param string $format 格式
     * @return bool
     */
    public static function isFormatSupported(string $format): bool
    {
        return in_array(strtolower($format), ['png', 'gif', 'webp', 'avif'], true);
    }

    /**
     * 获取 MIME 类型
     *
     * @param string $format 格式
     * @return string
     */
    public static function getMimeType(string $format): string
    {
        return match(strtolower($format)) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'image/png',
        };
    }

    /**
     * 清理临时文件
     */
    private static function cleanupTempFiles(array $tempFiles): void
    {
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
