<?php

namespace DouTu\Output;

class OutputFactory
{
    public static function create(string $format): OutputInterface
    {
        return match (strtolower($format)) {
            'gif' => new GIFOutput(),
            'webp' => new WebPOutput(),
            'avif' => new AVIFOutput(),
            default => new PNGOutput(),
        };
    }
}
