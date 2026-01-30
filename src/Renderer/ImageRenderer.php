<?php

namespace DouTu\Renderer;

/**
 * 图片渲染器接口
 * 定义所有渲染器必须实现的方法
 */
interface ImageRenderer
{
    /**
     * 创建画布
     *
     * @param int $width 宽度
     * @param int $height 高度
     * @return mixed 画布资源
     */
    public function createCanvas(int $width, int $height): mixed;

    /**
     * 加载字体
     *
     * @param string $path 字体文件路径
     * @param int $size 字体大小
     * @return mixed 字体资源
     */
    public function loadFont(string $path, int $size): mixed;

    /**
     * 测量文本尺寸
     *
     * @param string $text 文本
     * @param mixed $font 字体资源
     * @return array ['width' => int, 'height' => int, 'bbox' => array]
     */
    public function measureText(string $text, mixed $font): array;

    /**
     * 绘制文本
     *
     * @param mixed $image 画布资源
     * @param string $text 文本
     * @param mixed $font 字体资源
     * @param int $x X 坐标
     * @param int $y Y 坐标
     * @param array $color RGB 颜色数组 [r, g, b]
     * @param int $alpha 透明度 (0-127, 0 为不透明)
     * @return void
     */
    public function drawText(mixed $image, string $text, mixed $font, int $x, int $y, array $color, int $alpha = 0): void;

    /**
     * 绘制文本（带特效）
     *
     * @param mixed $image 画布资源
     * @param string $text 文本
     * @param mixed $font 字体资源
     * @param int $x X 坐标
     * @param int $y Y 坐标
     * @param array $color RGB 颜色数组 [r, g, b]
     * @param array $effects 特效数组
     * @return void
     */
    public function drawTextWithEffects(mixed $image, string $text, mixed $font, int $x, int $y, array $color, array $effects = []): void;

    /**
     * 绘制矩形
     *
     * @param mixed $image 画布资源
     * @param int $x1 左上角 X
     * @param int $y1 左上角 Y
     * @param int $x2 右下角 X
     * @param int $y2 右下角 Y
     * @param array $color RGB 颜色数组 [r, g, b]
     * @param int $alpha 透明度 (0-127, 0 为不透明)
     * @param bool $filled 是否填充
     * @return void
     */
    public function drawRectangle(mixed $image, int $x1, int $y1, int $x2, int $y2, array $color, int $alpha = 0, bool $filled = true): void;

    /**
     * 填充颜色
     *
     * @param mixed $image 画布资源
     * @param array $color RGB 颜色数组 [r, g, b]
     * @param int $alpha 透明度 (0-127, 0 为不透明)
     * @return void
     */
    public function fill(mixed $image, array $color, int $alpha = 0): void;

    /**
     * 加载图片
     *
     * @param string $path 图片路径
     * @return mixed 图片资源
     */
    public function loadImage(string $path): mixed;

    /**
     * 调整图片大小
     *
     * @param mixed $image 图片资源
     * @param int $width 新宽度
     * @param int $height 新高度
     * @return mixed 调整后的图片资源
     */
    public function resize(mixed $image, int $width, int $height): mixed;

    /**
     * 复制图片
     *
     * @param mixed $dst 目标画布
     * @param mixed $src 源图片
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
    public function copy(mixed $dst, mixed $src, int $dstX, int $dstY, int $srcX, int $srcY, int $srcW, int $srcH, int $dstW, int $dstH): void;

    /**
     * 保存图片
     *
     * @param mixed $image 图片资源
     * @param string $path 保存路径
     * @param string $format 输出格式
     * @param array $options 选项（如 quality）
     * @return bool 是否成功
     */
    public function save(mixed $image, string $path, string $format = 'png', array $options = []): bool;

    /**
     * 销毁资源
     *
     * @param mixed $resource 资源
     * @return void
     */
    public function destroy(mixed $resource): void;
}
