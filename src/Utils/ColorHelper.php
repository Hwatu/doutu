<?php

namespace DouTu\Utils;

use RuntimeException;

/**
 * 颜色处理工具类
 */
class ColorHelper
{
    /**
     * HEX 颜色转 RGB
     *
     * @param string $hex HEX 颜色（支持 #RRGGBB 或 RRGGBB）
     * @return array [r, g, b]
     * @throws RuntimeException
     */
    public static function hexToRgb(string $hex): array
    {
        // 移除 # 号
        $hex = ltrim($hex, '#');

        // 验证格式
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            throw new RuntimeException("无效的 HEX 颜色格式: {$hex}");
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * RGB 转 HEX
     *
     * @param int $r 红 (0-255)
     * @param int $g 绿 (0-255)
     * @param int $b 蓝 (0-255)
     * @return string HEX 颜色（#RRGGBB）
     */
    public static function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * 颜色插值
     * 在两个颜色之间进行线性插值
     *
     * @param string $color1 起始颜色（#RRGGBB）
     * @param string $color2 结束颜色（#RRGGBB）
     * @param float $t 插值因子 (0.0 - 1.0)
     * @return string 插值后的颜色（#RRGGBB）
     */
    public static function interpolateColor(string $color1, string $color2, float $t): string
    {
        // 限制 t 的范围
        $t = max(0.0, min(1.0, $t));

        $c1 = self::hexToRgb($color1);
        $c2 = self::hexToRgb($color2);

        $r = (int) round($c1[0] + ($c2[0] - $c1[0]) * $t);
        $g = (int) round($c1[1] + ($c2[1] - $c1[1]) * $t);
        $b = (int) round($c1[2] + ($c2[2] - $c1[2]) * $t);

        return self::rgbToHex($r, $g, $b);
    }

    /**
     * 颜色变暗
     *
     * @param string $color 颜色（#RRGGBB）
     * @param float $factor 变暗因子 (0.0 - 1.0)
     * @return string 变暗后的颜色
     */
    public static function darkenColor(string $color, float $factor = 0.2): string
    {
        $rgb = self::hexToRgb($color);
        $r = (int) max(0, $rgb[0] * (1 - $factor));
        $g = (int) max(0, $rgb[1] * (1 - $factor));
        $b = (int) max(0, $rgb[2] * (1 - $factor));
        return self::rgbToHex($r, $g, $b);
    }

    /**
     * 颜色变亮
     *
     * @param string $color 颜色（#RRGGBB）
     * @param float $factor 变亮因子 (0.0 - 1.0)
     * @return string 变亮后的颜色
     */
    public static function lightenColor(string $color, float $factor = 0.2): string
    {
        $rgb = self::hexToRgb($color);
        $r = (int) min(255, $rgb[0] + (255 - $rgb[0]) * $factor);
        $g = (int) min(255, $rgb[1] + (255 - $rgb[1]) * $factor);
        $b = (int) min(255, $rgb[2] + (255 - $rgb[2]) * $factor);
        return self::rgbToHex($r, $g, $b);
    }

    /**
     * 解析透明度颜色
     * 支持格式：#RRGGBBAA 或 #RRGGBB (AA 默认为 FF)
     *
     * @param string $color 颜色
     * @return array [r, g, b, a] (a 为 0-127，0 为完全透明)
     */
    public static function parseRgba(string $color): array
    {
        $color = ltrim($color, '#');
        $len = strlen($color);

        if ($len === 6) {
            // #RRGGBB
            $rgb = self::hexToRgb($color);
            $rgb[] = 0; // 完全不透明
            return $rgb;
        } elseif ($len === 8) {
            // #RRGGBBAA
            $r = hexdec(substr($color, 0, 2));
            $g = hexdec(substr($color, 2, 2));
            $b = hexdec(substr($color, 4, 2));
            $a = hexdec(substr($color, 6, 2));
            // 转换为 0-127 的透明度（GD 使用）
            $alpha = (int) (127 - ($a / 255) * 127);
            return [$r, $g, $b, $alpha];
        }

        throw new RuntimeException("无效的颜色格式: {$color}");
    }
}
