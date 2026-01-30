# 斗图助手 v2.0

一个支持自定义字体样式、多种输出格式、动态效果的图片生成 API 服务。

## ✨ 新特性

- 🎨 **多格式输出**：PNG、GIF 动画、WebP 动画、AVIF 静态
- 🎬 **动态效果**：文字流光/闪烁/发光/抖动/弹跳，背景渐变流动
- ⚡ **性能优化**：缓存系统、帧数控制，生成时间 < 1 秒
- 🏗️ **架构重构**：模块化设计，易于扩展和维护
- 🔧 **向后兼容**：保留原有 API，无缝升级

## 📋 目录结构

```
doutu/
├── src/                      # 源代码目录
│   ├── Core/                 # 核心类
│   │   ├── Config.php         # 配置管理
│   │   ├── Constants.php      # 常量定义
│   │   └── Application.php    # 应用入口
│   ├── Renderer/              # 渲染器
│   │   ├── ImageRenderer.php   # 渲染器接口
│   │   └── GDRenderer.php    # GD 实现
│   ├── Layout/               # 布局（待实现）
│   ├── Animation/            # 动画
│   │   ├── TextAnimation.php   # 文字动画
│   │   ├── BackgroundAnimation.php # 背景动画
│   │   └── GIFEncoder.php    # GIF 编码器
│   ├── Output/               # 输出格式（待实现）
│   ├── Cache/                # 缓存
│   │   └── FileCache.php     # 文件缓存
│   └── Utils/                # 工具类
│       ├── ColorHelper.php     # 颜色处理
│       └── PathHelper.php     # 路径处理
├── public/                  # 公共目录
│   └── index.php           # API 入口
├── storage/                 # 存储目录
│   ├── fonts/              # 字体文件
│   ├── output/             # 生成图片
│   ├── configs/            # 用户配置
│   └── cache/             # 缓存目录
├── config/                  # 配置文件
│   └── app.php            # 应用配置
├── font.html               # 字体配置页面（原有）
├── upload.html             # 字体上传页面（原有）
├── wxid.txt              # 授权用户列表
├── index.php              # 原有 API（保留）
├── composer.json          # 依赖管理
└── Dockerfile            # 容器配置
```

## 🚀 快速开始

### 环境要求

- **PHP 8.2+**
- **GD 扩展**（必须）
- **ImageMagick**（可选，用于 GIF 动画）
- **Composer**（可选，用于依赖管理）

### Docker 部署（推荐）

```bash
# 1. 克隆项目
git clone <repository-url> doutu
cd doutu

# 2. 构建镜像
docker build -t doutu .

# 3. 运行容器
docker-compose up -d
```

### 手动部署

```bash
# 1. 安装依赖（如使用 Composer）
composer install

# 2. 创建目录
mkdir -p storage/fonts storage/output storage/configs storage/cache

# 3. 设置权限
chmod 755 public/index.php
chmod 755 -R storage
chmod 666 wxid.txt

# 4. 配置 Web 服务器指向 public/ 目录
```

## 📚 API 文档

### 健康检查

```bash
GET /health
```

**响应示例：**
```json
{
  "success": true,
  "status": "ok",
  "version": "2.0.0",
  "cache_enabled": true,
  "cache_stats": {
    "path": "/path/to/cache",
    "ttl": 86400
  }
}
```

### 支持的格式

```bash
GET /formats
```

**响应示例：**
```json
{
  "success": true,
  "formats": ["png", "gif", "webp", "avif"],
  "animated_formats": ["gif", "webp"],
  "mime_types": {
    "png": "image/png",
    "gif": "image/gif",
    "webp": "image/webp",
    "avif": "image/avif"
  }
}
```

### 支持的动画

```bash
GET /animations
```

**响应示例：**
```json
{
  "success": true,
  "text_animations": {
    "none": "无动画",
    "flowlight": "流光效果",
    "blink": "闪烁效果",
    "glow": "发光效果",
    "shake": "抖动效果",
    "bounce": "弹跳效果"
  },
  "background_animations": {
    "none": "无动画",
    "gradient": "渐变流动"
  }
}
```

### 生成图片（新 API）

```bash
GET /?keyword=文字&format=gif&animation=flowlight
```

**参数说明：**

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `keyword` | string | - | 要生成的文字（必需） |
| `format` | string | `png` | 输出格式：`png\|gif\|webp\|avif` |
| `animation` | string | `none` | 动画类型：`none\|flowlight\|blink\|glow\|shake\|bounce` |
| `frameCount` | int | `12` | 动画帧数（1-60） |
| `frameDelay` | int | `100` | 每帧延迟（毫秒，50-200） |
| `loopCount` | int | `0` | 循环次数（0 = 无限） |
| `bgAnimation` | string | `none` | 背景动画：`none\|gradient` |
| `bgAnimStartColor` | string | - | 背景动画起始颜色（#RRGGBB） |
| `bgAnimEndColor` | string | - | 背景动画结束颜色（#RRGGBB） |
| `bgAnimDirection` | string | `horizontal` | 渐变方向：`horizontal\|vertical` |

### 原有 API（向后兼容）

```bash
GET /index.php?ac=search&wxid=你的ID&keyword=文字
```

原有 API 完全保留，无需修改现有调用方式。

## 🎨 动画效果说明

### 文字动画

- **流光（flowlight）**：颜色从左到右流动，高光扫过文字
- **闪烁（blink）**：透明度周期性变化，产生闪烁效果
- **发光（glow）**：光晕半径周期性变化，产生呼吸灯效果
- **抖动（shake）**：位置小幅度随机偏移，产生抖动效果
- **弹跳（bounce）**：Y 轴位置周期性变化，产生弹跳效果

### 背景动画

- **渐变流动（gradient）**：颜色渐变在水平或垂直方向流动

## ⚙️ 配置说明

配置文件位于 `config/app.php`，主要配置项：

```php
return [
    'app' => [
        'debug' => false,  // 调试模式
    ],
    'animation' => [
        'default_frame_count' => 12,    // 默认帧数
        'max_frame_count' => 60,         // 最大帧数
    ],
    'cache' => [
        'enabled' => true,              // 启用缓存
        'ttl' => 86400,               // 缓存时间（秒）
    ],
    'performance' => [
        'max_generation_time' => 1000,  // 最大生成时间（毫秒）
    ],
];
```

## 🎯 使用示例

### 生成流光 GIF

```bash
GET /?keyword=你好&format=gif&animation=flowlight&frameCount=12
```

### 生成渐变背景 GIF

```bash
GET /?keyword=测试&format=gif&bgAnimation=gradient&bgAnimStartColor=%23FF0000&bgAnimEndColor=%230000FF
```

### 生成 WebP 静态图

```bash
GET /?keyword=测试&format=webp
```

## 🔧 开发指南

### 本地开发

```bash
# 安装依赖
composer install

# 启动开发服务器
composer run serve
```

访问 http://localhost:8080

### 运行测试

```bash
composer run test
```

### 清理缓存

```bash
composer run cache:clean
```

## 📊 性能优化

### 缓存系统

- **缓存键**：`md5($text + json_encode($params))`
- **TTL**：24 小时（可配置）
- **分层存储**：使用子目录减少单目录文件数

### 帧数控制

- **默认帧数**：12 帧
- **最大帧数**：60 帧
- **提前终止**：超过时间限制时停止生成

### 资源复用

- 字体加载缓存
- 颜色分配缓存
- 避布对象复用

## ⚠️ 常见问题

### Q: GIF 动画无法生成？

**A:** 确保服务器安装了 ImageMagick：

```bash
# 检查是否安装
which convert

# Ubuntu/Debian
sudo apt-get install imagemagick

# CentOS/RHEL
sudo yum install ImageMagick
```

### Q: AVIF 不支持？

**A:** AVIF 需要 PHP 8.1+ 和 GD AVIF 支持。如不支持，系统会自动降级到 PNG。

### Q: 生成速度慢？

**A:** 检查以下几点：

1. 减少动画帧数（默认 12 帧）
2. 启用缓存系统
3. 确保服务器性能足够

### Q: 原有 API 还能用吗？

**A:** 完全兼容！原有 `index.php` 保留，所有现有调用无需修改。

## 📝 迁移指南

### 从 v1.x 升级

1. **备份原有文件**
   ```bash
   cp index.php index.php.backup
   ```

2. **更新代码**
   ```bash
   git pull
   composer install
   ```

3. **更新配置**
   ```bash
   cp config/app.php.example config/app.php
   # 根据需要修改配置
   ```

4. **测试新 API**
   ```bash
   curl http://your-domain/health
   ```

5. **清理旧缓存**（可选）
   ```bash
   composer run cache:clean
   ```

## 📄 许可证

MIT License - 详见 LICENSE 文件

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📞 联系方式

- 问题反馈：提交 GitHub Issue
