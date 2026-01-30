<?php

namespace DouTu\Output;

class PNGOutput implements OutputInterface
{
    public function getFormat(): string
    {
        return 'png';
    }

    public function supportsAnimation(): bool
    {
        return false;
    }

    public function isSupported(): bool
    {
        return function_exists('imagepng');
    }

    public function save(mixed $image, string $path, array $options = []): bool
    {
        if (!$this->isSupported()) {
            return false;
        }

        $quality = $options['quality'] ?? 9;
        return imagepng($image, $path, $quality);
    }

    public function saveAnimated(array $frames, string $path, array $options = []): bool
    {
        return false;
    }
}
