<?php

namespace DouTu\Layout;

/**
 * 布局接口
 * 所有布局实现必须实现此接口
 */
interface LayoutInterface
{
    /**
     * 准备布局数据
     *
     * @param string $text 文本内容
     * @param array $params 生成参数
     * @return LayoutData 布局数据
     */
    public function prepare(string $text, array $params): LayoutData;

    /**
     * 绘制背景
     *
     * @param mixed $canvas 画布资源
     * @param array $params 生成参数
     * @return void
     */
    public function drawBackground($canvas, array $params): void;

    /**
     * 绘制文本
     *
     * @param mixed $canvas 画布资源
     * @param LayoutData $layout 布局数据
     * @param array $params 生成参数
     * @param array $frameParams 帧参数（动画）
     * @return void
     */
    public function drawText($canvas, LayoutData $layout, array $params, array $frameParams = []): void;

    /**
     * 获取画布尺寸
     *
     * @param array $params 生成参数
     * @return array ['width' => int, 'height' => int]
     */
    public function getCanvasSize(array $params): array;
}
