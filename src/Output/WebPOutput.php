<?php

namespace DouTu\Output;

class WebPOutput implements OutputInterface
{
    public function getFormat(): string
    {
        return 'webp';
    }

    public function supportsAnimation(): bool
    {
        return $this->hasImageMagick() && function_exists('imagewebp');
    }

    public function isSupported(): bool
    {
        return function_exists('imagewebp');
    }

    public function save(mixed $image, string $path, array $options = []): bool
    {
        if (!$this->isSupported()) {
            return false;
        }

        $quality = $options['quality'] ?? 80;
        return imagewebp($image, $path, $quality);
    }

    public function saveAnimated(array $frames, string $path, array $options = []): bool
    {
        if (!$this->supportsAnimation()) {
            return false;
        }

        $delay = isset($options['delay']) ? (int) $options['delay'] : 100;
        $quality = $options['quality'] ?? 80;

        $tempFiles = [];

        try {
            foreach ($frames as $i => $frame) {
                $tempFile = sys_get_temp_dir() . '/frame_' . $i . '.webp';
                imagewebp($frame, $tempFile, $quality);
                $tempFiles[] = $tempFile;
            }

            $files = implode(' ', array_map('escapeshellarg', $tempFiles));
            $command = "convert -delay {$delay} -loop 0 {$files} " . escapeshellarg($path);

            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);

            $this->cleanupTempFiles($tempFiles);

            return $returnCode === 0 && file_exists($path);
        } catch (\Throwable $e) {
            $this->cleanupTempFiles($tempFiles);
            return false;
        }
    }

    private function cleanupTempFiles(array $tempFiles): void
    {
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    private function hasImageMagick(): bool
    {
        $output = [];
        $returnCode = 0;

        exec('which convert 2>/dev/null', $output, $returnCode);

        return $returnCode === 0 && !empty($output);
    }
}
