<?php

namespace DouTu\Core;

use Exception;

/**
 * 应用入口类
 * 处理路由和请求分发
 */
class Application
{
    private Config $config;
    private array $routes = [];

    /**
     * 构造函数
     *
     * @param Config $config 配置实例
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * 注册路由
     *
     * @param string $path 路由路径
     * @param callable $handler 处理函数
     * @return void
     */
    public function route(string $path, callable $handler): void
    {
        $this->routes[$path] = $handler;
    }

    /**
     * 运行应用
     *
     * @return void
     * @throws Exception
     */
    public function run(): void
    {
        // 处理静态文件
        if (PHP_SAPI === 'cli-server') {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $path = rawurldecode(parse_url($requestUri, PHP_URL_PATH));

            if ($path !== '/' && $path !== '' && $path !== '/index.php') {
                $fullPath = realpath($this->config->get('app.public_path', __DIR__ . '/../../public') . $path);
                if (
                    $fullPath !== false &&
                    strpos($fullPath, __DIR__) === 0 &&
                    is_file($fullPath)
                ) {
                    return false;
                }
            }
        }

        // 设置响应头
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

        // 处理 CORS 预检请求
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            http_response_code(200);
            exit;
        }

        // 解析请求路径
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = rtrim($path, '/');
        $path = $path ?: '/';

        // 路由分发
        if (isset($this->routes[$path])) {
            try {
                $response = call_user_func($this->routes[$path], $_GET, $_POST, $_SERVER);
                $this->sendResponse($response);
            } catch (Exception $e) {
                $this->sendError($e->getMessage(), 500);
            }
        } else {
            $this->sendError('接口不存在', 404);
        }
    }

    /**
     * 发送响应
     *
     * @param mixed $data 响应数据
     * @return void
     */
    private function sendResponse(mixed $data): void
    {
        // 如果是图片路径，直接输出图片
        if (is_string($data) && file_exists($data)) {
            $format = strtolower(pathinfo($data, PATHINFO_EXTENSION));
            header('Content-Type: ' . Constants::getMimeType($format));
            header('Content-Length: ' . filesize($data));
            readfile($data);
            exit;
        }

        // JSON 响应
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 发送错误响应
     *
     * @param string $message 错误消息
     * @param int $code HTTP 状态码
     * @return void
     */
    private function sendError(string $message, int $code = 500): void
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 批量注册路由
     *
     * @param array $routes 路由数组 ['path' => handler]
     * @return void
     */
    public function routes(array $routes): void
    {
        foreach ($routes as $path => $handler) {
            $this->route($path, $handler);
        }
    }

    /**
     * 获取配置实例
     *
     * @return Config
     */
    public function config(): Config
    {
        return $this->config;
    }
}
