<?php

namespace DouTu\Animation;

use RuntimeException;

/**
 * GIF 编码器
 * 将多帧合并为 GIF 动画
 */
class GIFEncoder
{
    private array $frames = [];
    private int $delay;
    private int $loopCount;
    private int $width;
    private int $height;

    /**
     * 构造函数
     *
     * @param int $delay 每帧延迟（毫秒）
     * @param int $loopCount 循环次数（0 = 无限）
     */
    public function __construct(int $delay = 100, int $loopCount = 0)
    {
        $this->delay = $delay;
        $this->loopCount = $loopCount;
    }

    /**
     * 添加帧
     *
     * @param \GdImage $frame 帧资源
     * @param int $x X 偏移
     * @param int $y Y 偏移
     * @return void
     */
    public function addFrame(\GdImage $frame, int $x = 0, int $y = 0): void
    {
        // 设置画布尺寸为第一帧的尺寸
        if (empty($this->frames)) {
            $this->width = imagesx($frame);
            $this->height = imagesy($frame);
        }

        // 调整帧尺寸以匹配画布
        $width = imagesx($frame);
        $height = imagesy($frame);

        if ($width !== $this->width || $height !== $this->height) {
            $resized = imagecreatetruecolor($this->width, $this->height);
            imagealphablending($resized, true);
            imagesavealpha($resized, true);
            imagecopyresampled(
                $resized,
                $frame,
                $x,
                $y,
                0,
                0,
                $width,
                $height,
                $width,
                $height
            );
            $frame = $resized;
        }

        $this->frames[] = $frame;
    }

    /**
     * 从数组添加帧
     *
     * @param array $frames 帧数组
     * @return void
     */
    public function addFrames(array $frames): void
    {
        foreach ($frames as $frame) {
            $this->addFrame($frame);
        }
    }

    /**
     * 保存为 GIF 文件
     *
     * @param string $path 保存路径
     * @return bool 是否成功
     * @throws RuntimeException
     */
    public function save(string $path): bool
    {
        if (empty($this->frames)) {
            throw new RuntimeException('没有可保存的帧');
        }

        // 使用 GD 的 imagegif 逐帧保存并合并
        // 注意：GD 原生不支持多帧 GIF，这里使用简单方法

        $tempFiles = [];

        try {
            // 1. 将每帧保存为临时 GIF
            foreach ($this->frames as $i => $frame) {
                $tempFile = sys_get_temp_dir() . '/frame_' . $i . '.gif';
                imagegif($frame, $tempFile);
                $tempFiles[] = $tempFile;
            }

            // 2. 尝试使用 ImageMagick 合并
            if ($this->hasImageMagick()) {
                $result = $this->mergeWithImageMagick($tempFiles, $path);
                $this->cleanupTempFiles($tempFiles);
                return $result;
            }

            // 3. 降级方案：只保存第一帧
            throw new RuntimeException('GIF 动画需要 ImageMagick 扩展');
        } catch (\Exception $e) {
            $this->cleanupTempFiles($tempFiles);
            throw $e;
        }
    }

    /**
     * 使用 ImageMagick 合并 GIF
     *
     * @param array $tempFiles 临时文件数组
     * @param string $outputPath 输出路径
     * @return bool
     */
    private function mergeWithImageMagick(array $tempFiles, string $outputPath): bool
    {
        $delayArg = $this->delay;
        $loopArg = $this->loopCount === 0 ? '0' : (string)$this->loopCount;

        $files = implode(' ', array_map('escapeshellarg', $tempFiles));

        $command = "convert -delay {$delayArg} -loop {$loopArg} {$files} " . escapeshellarg($outputPath);

        $output = [];
        $returnCode = 0;

        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException('ImageMagick 合并 GIF 失败: ' . implode("\n", $output));
        }

        return file_exists($outputPath);
    }

    /**
     * 检查是否安装了 ImageMagick
     *
     * @return bool
     */
    private function hasImageMagick(): bool
    {
        $output = [];
        $returnCode = 0;

        exec('which convert 2>/dev/null', $output, $returnCode);

        return $returnCode === 0 && !empty($output);
    }

    /**
     * 清理临时文件
     *
     * @param array $tempFiles 临时文件数组
     * @return void
     */
    private function cleanupTempFiles(array $tempFiles): void
    {
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * 获取帧数
     *
     * @return int
     */
    public function getFrameCount(): int
    {
        return count($this->frames);
    }

    /**
     * 清空所有帧
     *
     * @return void
     */
    public function clear(): void
    {
        $this->frames = [];
    }
}
