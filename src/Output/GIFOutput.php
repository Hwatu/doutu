<?php

namespace DouTu\Output;

use DouTu\Animation\GIFEncoder;

class GIFOutput implements OutputInterface
{
    public function getFormat(): string
    {
        return 'gif';
    }

    public function supportsAnimation(): bool
    {
        return true;
    }

    public function isSupported(): bool
    {
        return function_exists('imagegif');
    }

    public function save(mixed $image, string $path, array $options = []): bool
    {
        if (!$this->isSupported()) {
            return false;
        }

        return imagegif($image, $path);
    }

    public function saveAnimated(array $frames, string $path, array $options = []): bool
    {
        if (!$this->isSupported()) {
            return false;
        }

        $delay = isset($options['delay']) ? (int) $options['delay'] : 100;
        $loop = isset($options['loop']) ? (int) $options['loop'] : 0;

        try {
            $encoder = new GIFEncoder($delay, $loop);
            $encoder->addFrames($frames);
            return $encoder->save($path);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
