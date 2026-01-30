<?php

namespace DouTu\Output;

interface OutputInterface
{
    public function getFormat(): string;

    public function supportsAnimation(): bool;

    public function isSupported(): bool;

    public function save(mixed $image, string $path, array $options = []): bool;

    public function saveAnimated(array $frames, string $path, array $options = []): bool;
}
