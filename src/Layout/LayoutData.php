<?php

namespace DouTu\Layout;

/**
 * 布局数据类
 * 存储布局计算后的数据
 */
class LayoutData
{
    /** @var int 画布宽度 */
    public int $width;

    /** @var int 画布高度 */
    public int $height;

    /** @var array 文本行数组 */
    public array $lines;

    /** @var int 文本总宽度 */
    public int $textWidth;

    /** @var int 文本总高度 */
    public int $textHeight;

    /** @var int 行高 */
    public int $lineHeight;

    /** @var int X 起始坐标 */
    public int $startX;

    /** @var int Y 起始坐标 */
    public int $startY;

    /** @var array 字体信息 */
    public array $fontInfo;

    /** @var array 额外布局信息 */
    public array $extra;

    /**
     * 构造函数
     */
    public function __construct(
        int $width,
        int $height,
        array $lines = [],
        int $textWidth = 0,
        int $textHeight = 0,
        int $lineHeight = 0,
        int $startX = 0,
        int $startY = 0,
        array $fontInfo = [],
        array $extra = []
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->lines = $lines;
        $this->textWidth = $textWidth;
        $this->textHeight = $textHeight;
        $this->lineHeight = $lineHeight;
        $this->startX = $startX;
        $this->startY = $startY;
        $this->fontInfo = $fontInfo;
        $this->extra = $extra;
    }
}
