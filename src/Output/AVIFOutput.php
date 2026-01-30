<?php

namespace DouTu\Output;

class AVIFOutput implements OutputInterface
{
    public function getFormat(): string
    {
        return 'avif';
    }

    public function supportsAnimation(): bool
    {
        return false;
    }

    public function isSupported(): bool
    {
        return function_exists('imageavif');
    }

    public function save(mixed $image, string $path, array $options = []): bool
    {
        if (!$this->isSupported()) {
            return false;
        }

        $quality = $options['quality'] ?? 80;
        return imageavif($image, $path, $quality);
    }

    public function saveAnimated(array $frames, string $path, array $options = []): bool
    {
        return false;
    }
}
