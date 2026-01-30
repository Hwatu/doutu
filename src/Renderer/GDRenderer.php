<?php

namespace DouTu\Renderer;

use RuntimeException;

/**
 * GD 渲染器实现
 * 基于 PHP GD 扩展的图片渲染
 */
class GDRenderer implements ImageRenderer
{
    /** @var array 字体缓存 */
    private array $fontCache = [];

    /**
     * 创建画布
     *
     * @param int $width 宽度
     * @param int $height 高度
     * @return \GdImage 画布资源
     * @throws RuntimeException
     */
    public function createCanvas(int $width, int $height): mixed
    {
        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException('画布尺寸必须大于0');
        }

        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            throw new RuntimeException('创建画布失败');
        }

        // 启用 alpha 通道
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * 加载字体
     *
     * @param string $path 字体文件路径
     * @param int $size 字体大小
     * @return array 字体信息 ['path' => string, 'size' => int]
     * @throws RuntimeException
     */
    public function loadFont(string $path, int $size): mixed
    {
        $cacheKey = $path . ':' . $size;

        if (!isset($this->fontCache[$cacheKey])) {
            if (!file_exists($path)) {
                throw new RuntimeException("字体文件不存在: {$path}");
            }

            $this->fontCache[$cacheKey] = [
                'path' => $path,
                'size' => $size,
            ];
        }

        return $this->fontCache[$cacheKey];
    }

    /**
     * 测量文本尺寸
     *
     * @param string $text 文本
     * @param mixed $font 字体资源
     * @return array ['width' => int, 'height' => int, 'bbox' => array]
     */
    public function measureText(string $text, mixed $font): array
    {
        if (!isset($font['path'], $font['size'])) {
            throw new RuntimeException('字体格式错误');
        }

        $bbox = imagettfbbox($font['size'], 0, $font['path'], $text);

        if ($bbox === false) {
            throw new RuntimeException('测量文本失败');
        }

        return [
            'width' => abs($bbox[4] - $bbox[0]),
            'height' => abs($bbox[5] - $bbox[1]),
            'bbox' => $bbox,
            'top' => abs($bbox[5]),
            'left' => abs($bbox[0]),
        ];
    }

    /**
     * 绘制文本
     *
     * @param \GdImage $image 画布资源
     * @param string $text 文本
     * @param mixed $font 字体资源
     * @param int $x X 坐标
     * @param int $y Y 坐标
     * @param array $color RGB 颜色数组 [r, g, b]
     * @param int $alpha 透明度 (0-127, 0 为不透明)
     * @return void
     */
    public function drawText(mixed $image, string $text, mixed $font, int $x, int $y, array $color, int $alpha = 0): void
    {
        if (!isset($color[0], $color[1], $color[2])) {
            throw new RuntimeException('颜色格式错误');
        }

        $colorResource = imagecolorallocatealpha(
            $image,
            $color[0],
            $color[1],
            $color[2],
            $alpha
        );

        if ($colorResource === false) {
            throw new RuntimeException('分配颜色失败');
        }

        imagettftext(
            $image,
            $font['size'],
            0,
            $x,
            $y,
            $colorResource,
            $font['path'],
            $text
        );

        imagedestroy($colorResource);
    }

    /**
     * 绘制文本（带特效）
     *
     * @param \GdImage $image 画布资源
     * @param string $text 文本
     * @param mixed $font 字体资源
     * @param int $x X 坐标
     * @param int $y Y 坐标
     * @param array $color RGB 颜色数组 [r, g, b]
     * @param array $effects 特效数组
     * @return void
     */
    public function drawTextWithEffects(mixed $image, string $text, mixed $font, int $x, int $y, array $color, array $effects = []): void
    {
        // 阴影
        if (!empty($effects['shadow']) && $effects['shadow']['enabled'] ?? false) {
            $shadow = $effects['shadow'];
            $shadowColor = $shadow['color'] ?? [0, 0, 0];
            $shadowOffsetX = $shadow['offset_x'] ?? 3;
            $shadowOffsetY = $shadow['offset_y'] ?? 3;
            $shadowBlur = $shadow['blur'] ?? 0;

            $shadowAlpha = (int) (($shadow['opacity'] ?? 50) / 100 * 127);

            // 绘制多层阴影实现模糊效果
            for ($i = $shadowBlur; $i >= 0; $i--) {
                $this->drawText(
                    $image,
                    $text,
                    $font,
                    $x + $shadowOffsetX + $i,
                    $y + $shadowOffsetY + $i,
                    $shadowColor,
                    $shadowAlpha
                );
            }
        }

        // 描边
        if (!empty($effects['outline']) && $effects['outline']['enabled'] ?? false) {
            $outline = $effects['outline'];
            $outlineColor = $outline['color'] ?? [255, 255, 255];
            $outlineWidth = $outline['width'] ?? 1;

            for ($i = -$outlineWidth; $i <= $outlineWidth; $i++) {
                for ($j = -$outlineWidth; $j <= $outlineWidth; $j++) {
                    if ($i === 0 && $j === 0) {
                        continue;
                    }

                    $this->drawText(
                        $image,
                        $text,
                        $font,
                        $x + $i,
                        $y + $j,
                        $outlineColor,
                        0
                    );
                }
            }
        }

        // 发光
        if (!empty($effects['glow']) && $effects['glow']['enabled'] ?? false) {
            $glow = $effects['glow'];
            $glowColor = $glow['color'] ?? [255, 255, 0];
            $glowRadius = $glow['radius'] ?? 2;
            $glowAlpha = (int) (($glow['opacity'] ?? 30) / 100 * 127);

            // 绘制多层发光
            for ($i = $glowRadius; $i > 0; $i--) {
                $alpha = (int) ($glowAlpha * (1 - $i / $glowRadius));
                $this->drawText(
                    $image,
                    $text,
                    $font,
                    $x,
                    $y,
                    $glowColor,
                    $alpha
                );
            }
        }

        // 绘制主文本
        $textAlpha = (int) (($effects['opacity'] ?? 100) / 100 * 127);
        $this->drawText(
            $image,
            $text,
            $font,
            $x,
            $y,
            $color,
            $textAlpha
        );
    }

    /**
     * 绘制矩形
     *
     * @param \GdImage $image 画布资源
     * @param int $x1 左上角 X
     * @param int $y1 左上角 Y
     * @param int $x2 右下角 X
     * @param int $y2 右下角 Y
     * @param array $color RGB 颜色数组 [r, g, b]
     * @param int $alpha 透明度 (0-127, 0 为不透明)
     * @param bool $filled 是否填充
     * @return void
     */
    public function drawRectangle(mixed $image, int $x1, int $y1, int $x2, int $y2, array $color, int $alpha = 0, bool $filled = true): void
    {
        $colorResource = imagecolorallocatealpha(
            $image,
            $color[0],
            $color[1],
            $color[2],
            $alpha
        );

        if ($filled) {
            imagefilledrectangle($image, $x1, $y1, $x2, $y2, $colorResource);
        } else {
            imagerectangle($image, $x1, $y1, $x2, $y2, $colorResource);
        }

        imagedestroy($colorResource);
    }

    /**
     * 填充颜色
     *
     * @param \GdImage $image 画布资源
     * @param array $color RGB 颜色数组 [r, g, b]
     * @param int $alpha 透明度 (0-127, 0 为不透明)
     * @return void
     */
    public function fill(mixed $image, array $color, int $alpha = 0): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $this->drawRectangle($image, 0, 0, $width - 1, $height - 1, $color, $alpha, true);
    }

    /**
     * 加载图片
     *
     * @param string $path 图片路径
     * @return \GdImage 图片资源
     * @throws RuntimeException
     */
    public function loadImage(string $path): mixed
    {
        if (!file_exists($path)) {
            throw new RuntimeException("图片文件不存在: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $image = match($extension) {
            'png' => imagecreatefrompng($path),
            'jpg', 'jpeg' => imagecreatefromjpeg($path),
            'gif' => imagecreatefromgif($path),
            'webp' => imagecreatefromwebp($path),
            default => throw new RuntimeException("不支持的图片格式: {$extension}"),
        };

        if (!$image) {
            throw new RuntimeException("加载图片失败: {$path}");
        }

        return $image;
    }

    /**
     * 调整图片大小
     *
     * @param \GdImage $image 图片资源
     * @param int $width 新宽度
     * @param int $height 新高度
     * @return \GdImage 调整后的图片资源
     * @throws RuntimeException
     */
    public function resize(mixed $image, int $width, int $height): mixed
    {
        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException('目标尺寸必须大于0');
        }

        $newImage = imagecreatetruecolor($width, $height);
        if (!$newImage) {
            throw new RuntimeException('创建新画布失败');
        }

        imagealphablending($newImage, true);
        imagesavealpha($newImage, true);

        // 保持透明度
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefill($newImage, 0, 0, $transparent);

        if (!imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image))) {
            throw new RuntimeException('调整图片大小失败');
        }

        return $newImage;
    }

    /**
     * 复制图片
     *
     * @param \GdImage $dst 目标画布
     * @param \GdImage $src 源图片
     * @param int $dstX 目标 X
     * @param int $dstY 目标 Y
     * @param int $srcX 源 X
     * @param int $srcY 源 Y
     * @param int $srcW 源宽度
     * @param int $srcH 源高度
     * @param int $dstW 目标宽度
     * @param int $dstH 目标高度
     * @return void
     */
    public function copy(mixed $dst, mixed $src, int $dstX, int $dstY, int $srcX, int $srcY, int $srcW, int $srcH, int $dstW, int $dstH): void
    {
        imagecopyresampled(
            $dst,
            $src,
            $dstX,
            $dstY,
            $srcX,
            $srcY,
            $dstW,
            $dstH,
            $srcW,
            $srcH
        );
    }

    /**
     * 保存图片
     *
     * @param \GdImage $image 图片资源
     * @param string $path 保存路径
     * @param string $format 输出格式
     * @param array $options 选项（如 quality）
     * @return bool 是否成功
     * @throws RuntimeException
     */
    public function save(mixed $image, string $path, string $format = 'png', array $options = []): bool
    {
        $quality = $options['quality'] ?? null;

        $result = match(strtolower($format)) {
            'png' => imagepng($image, $path, $quality ?? 9),
            'jpg', 'jpeg' => imagejpeg($image, $path, $quality ?? 90),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path, $quality ?? 80),
            'avif' => function_exists('imageavif') ? imageavif($image, $path, $quality ?? 80) : false,
            default => throw new RuntimeException("不支持的输出格式: {$format}"),
        };

        if ($result === false) {
            throw new RuntimeException("保存图片失败: {$path}");
        }

        return true;
    }

    /**
     * 销毁资源
     *
     * @param mixed $resource 资源
     * @return void
     */
    public function destroy(mixed $resource): void
    {
        if (is_resource($resource) || $resource instanceof \GdImage) {
            imagedestroy($resource);
        }
    }
}
