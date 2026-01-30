from PIL import Image, ImageDraw, ImageFont
import textwrap


def generate_text_image(text, output_path, width=800, font_size=48, stroke_width=3):
    """
    根据模板规范生成文字转图片

    Args:
        text: 要转换的文字内容
        output_path: 输出图片路径
        width: 图片宽度 (px)
        font_size: 字体大小
        stroke_width: 描边宽度
    """
    # 1. 加载字体 (推荐使用系统黑体)
    try:
        font = ImageFont.truetype("Heiti SC", font_size)
    except IOError:
        font = ImageFont.load_default()

    # 2. 计算文本渲染尺寸
    lines = textwrap.wrap(text, width=30)
    total_height = len(lines) * (font_size + 10)

    # 3. 创建画布
    bg_color = (255, 255, 255)  # 白色背景
    image = Image.new("RGB", (width, total_height + 50), bg_color)
    draw = ImageDraw.Draw(image)

    # 4. 绘制描边效果 (双层绘制)
    for i, line in enumerate(lines):
        y = 30 + i * (font_size + 10)
        # 阴影层
        for dx in range(-stroke_width, stroke_width+1):
            for dy in range(-stroke_width, stroke_width+1):
                if dx != 0 or dy != 0:
                    draw.text((50+dx, y+dy), line, font=font, fill=(200, 200, 200))
        # 主文字
        draw.text((50, y), line, font=font, fill=(0, 0, 0))

    # 5. 保存图片
    image.save(output_path, quality=95)
    return output_path

# 示例用法
if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        print("Usage: python text2image.py \"Your text here\"")
        sys.exit(1)
    text = sys.argv[1]
    generate_text_image(text, "output.png")