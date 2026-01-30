<?php

namespace DouTu\Cache;

use RuntimeException;

/**
 * 文件缓存实现
 */
class FileCache
{
    private string $cacheDir;
    private int $ttl;
    private string $prefix = '';

    /**
     * 构造函数
     *
     * @param string $cacheDir 缓存目录
     * @param int $ttl TTL（秒），0 为不过期
     */
    public function __construct(string $cacheDir, int $ttl = 86400)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->ttl = $ttl;

        // 创建缓存目录
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0777, true)) {
                throw new RuntimeException("无法创建缓存目录: {$this->cacheDir}");
            }
        }
    }

    /**
     * 设置缓存键前缀
     *
     * @param string $prefix 前缀
     * @return void
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * 获取缓存
     *
     * @param string $key 缓存键
     * @return mixed|null 缓存值或 null
     */
    public function get(string $key): mixed
    {
        $path = $this->getCachePath($key);

        if (!file_exists($path)) {
            return null;
        }

        // 检查是否过期
        if ($this->ttl > 0) {
            $age = time() - filemtime($path);
            if ($age > $this->ttl) {
                @unlink($path);
                return null;
            }
        }

        $data = file_get_contents($path);

        if ($data === false) {
            return null;
        }

        $decoded = @json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $data;
        }

        return $decoded;
    }

    /**
     * 设置缓存
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int|null $ttl 自定义 TTL（秒）
     * @return bool 是否成功
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $path = $this->getCachePath($key);

        $data = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : $value;

        if ($data === false) {
            return false;
        }

        $result = file_put_contents($path, $data, LOCK_EX);

        if ($result === false) {
            return false;
        }

        // 设置自定义 TTL
        if ($ttl !== null && $ttl > 0) {
            touch($path, time() + $ttl);
        }

        return true;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键
     * @return bool 是否成功
     */
    public function delete(string $key): bool
    {
        $path = $this->getCachePath($key);

        if (!file_exists($path)) {
            return true;
        }

        return @unlink($path);
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool
    {
        $path = $this->getCachePath($key);

        if (!file_exists($path)) {
            return false;
        }

        // 检查是否过期
        if ($this->ttl > 0) {
            $age = time() - filemtime($path);
            return $age <= $this->ttl;
        }

        return true;
    }

    /**
     * 清空所有缓存
     *
     * @return bool 是否成功
     */
    public function clear(): bool
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        $success = true;

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                if (!@unlink($file->getPathname())) {
                    $success = false;
                }
            } elseif ($file->isDir()) {
                @rmdir($file->getPathname());
            }
        }

        return $success;
    }

    /**
     * 清理过期缓存
     *
     * @return int 清理的文件数
     */
    public function cleanupExpired(): int
    {
        if ($this->ttl <= 0) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $count = 0;

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $age = time() - $file->getMTime();
                if ($age > $this->ttl) {
                    if (@unlink($file->getPathname())) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * 获取缓存统计信息
     *
     * @return array ['total' => int, 'size' => int, 'expired' => int]
     */
    public function getStats(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $total = 0;
        $size = 0;
        $expired = 0;

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total++;
                $size += $file->getSize();

                if ($this->ttl > 0) {
                    $age = time() - $file->getMTime();
                    if ($age > $this->ttl) {
                        $expired++;
                    }
                }
            }
        }

        return [
            'total' => $total,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'expired' => $expired,
        ];
    }

    /**
     * 获取缓存文件路径
     *
     * @param string $key 缓存键
     * @return string
     */
    private function getCachePath(string $key): string
    {
        $hash = md5($this->prefix . $key);
        $dir = $this->cacheDir . '/' . substr($hash, 0, 2);

        // 创建子目录
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir . '/' . $hash . '.cache';
    }

    /**
     * 格式化字节大小
     *
     * @param int $bytes 字节数
     * @param int $decimals 小数位数
     * @return string
     */
    private function formatBytes(int $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        $size = $bytes / pow(1024, $factor);

        return sprintf("%.{$decimals}f %s", $size, $units[$factor] ?? 'B');
    }
}
