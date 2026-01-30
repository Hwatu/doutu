<?php

namespace DouTu\Core;

use RuntimeException;

/**
 * 配置管理类
 * 替代全局变量，使用单例模式
 */
class Config
{
    /** @var Config|null 单例实例 */
    private static ?Config $instance = null;

    /** @var array 配置数据 */
    private array $data;

    /**
     * 私有构造函数
     */
    private function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * 获取单例实例
     *
     * @param array $data 配置数据（仅首次加载时使用）
     * @return self
     */
    public static function getInstance(array $data = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($data);
        }
        return self::$instance;
    }

    /**
     * 从文件加载配置
     *
     * @param string $configPath 配置文件路径
     * @return self
     */
    public static function loadFromFile(string $configPath): self
    {
        if (!file_exists($configPath)) {
            throw new RuntimeException("配置文件不存在: {$configPath}");
        }

        $data = require $configPath;

        if (!is_array($data)) {
            throw new RuntimeException("配置文件格式错误: {$configPath}");
        }

        return self::getInstance($data);
    }

    /**
     * 获取配置值
     *
     * @param string $key 配置键（支持点号分隔的多级键，如 'cache.ttl'）
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置配置值
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $data = &$this->data;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $data[$k] = $value;
            } else {
                if (!isset($data[$k]) || !is_array($data[$k])) {
                    $data[$k] = [];
                }
                $data = &$data[$k];
            }
        }
    }

    /**
     * 检查配置键是否存在
     *
     * @param string $key 配置键
     * @return bool
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * 获取所有配置
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * 重置配置
     *
     * @param array $data 新配置数据
     * @return void
     */
    public function reset(array $data = []): void
    {
        $this->data = $data;
    }

    /**
     * 禁止克隆
     */
    private function __clone()
    {
    }

    /**
     * 禁止序列化
     */
    public function __sleep(): array
    {
        throw new RuntimeException('配置类不支持序列化');
    }

    /**
     * 禁止反序列化
     */
    public function __wakeup(): void
    {
        throw new RuntimeException('配置类不支持反序列化');
    }
}
