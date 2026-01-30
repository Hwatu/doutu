<?php

namespace DouTu\Utils;

use RuntimeException;

/**
 * 路径处理工具类
 */
class PathHelper
{
    /**
     * 检查路径是否为绝对路径
     *
     * @param string $path 路径
     * @return bool
     */
    public static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        // Unix 路径
        if ($path[0] === '/') {
            return true;
        }

        // Windows 路径（盘符）
        if (strlen($path) >= 2 && $path[1] === ':' && ctype_alpha($path[0])) {
            return true;
        }

        return false;
    }

    /**
     * 确保路径存在，如不存在则创建
     *
     * @param string $path 路径
     * @param int $mode 权限（默认 0777）
     * @return void
     * @throws RuntimeException
     */
    public static function ensureDirectory(string $path, int $mode = 0777): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, $mode, true)) {
                throw new RuntimeException("无法创建目录: {$path}");
            }
        }
    }

    /**
     * 规范化路径（去除 . 和 ..）
     *
     * @param string $path 路径
     * @return string 规范化后的路径
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($normalized);
            } else {
                $normalized[] = $part;
            }
        }

        return implode('/', $normalized);
    }

    /**
     * 获取相对路径
     *
     * @param string $from 起始路径
     * @param string $to 目标路径
     * @return string 相对路径
     */
    public static function getRelativePath(string $from, string $to): string
    {
        $from = self::normalize($from);
        $to = self::normalize($to);

        $fromParts = explode('/', $from);
        $toParts = explode('/', $to);

        $i = 0;
        $max = min(count($fromParts), count($toParts));

        while ($i < $max && $fromParts[$i] === $toParts[$i]) {
            $i++;
        }

        $up = count($fromParts) - $i - 1;
        $down = array_slice($toParts, $i);

        $relative = str_repeat('../', max(0, $up)) . implode('/', $down);

        return $relative ?: '.';
    }

    /**
     * 获取文件扩展名
     *
     * @param string $filename 文件名
     * @return string 扩展名（小写，不含点号）
     */
    public static function getExtension(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $ext ?: '';
    }

    /**
     * 确保文件扩展名
     *
     * @param string $filename 文件名
     * @param string $extension 扩展名
     * @return string 带扩展名的文件名
     */
    public static function ensureExtension(string $filename, string $extension): string
    {
        $currentExt = self::getExtension($filename);
        $targetExt = strtolower(ltrim($extension, '.'));

        if ($currentExt !== $targetExt) {
            return $filename . '.' . $targetExt;
        }

        return $filename;
    }

    /**
     * 生成唯一文件名
     *
     * @param string $prefix 前缀
     * @param string $extension 扩展名
     * @return string 唯一文件名
     */
    public static function generateUniqueName(string $prefix = '', string $extension = ''): string
    {
        $name = $prefix . '_' . uniqid(more_entropy: true);

        if (!empty($extension)) {
            $name = self::ensureExtension($name, $extension);
        }

        return $name;
    }

    /**
     * 获取文件大小（人类可读格式）
     *
     * @param int $bytes 字节数
     * @param int $decimals 小数位数
     * @return string 格式化后的大小
     */
    public static function formatFileSize(int $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        $size = $bytes / pow(1024, $factor);

        return sprintf("%.{$decimals}f %s", $size, $units[$factor] ?? 'B');
    }
}
