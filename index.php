<?php

if (PHP_SAPI === 'cli-server') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = rawurldecode(parse_url($requestUri, PHP_URL_PATH));
    if ($path !== '/' && $path !== '' && $path !== '/index.php') {
        $fullPath = realpath(__DIR__ . $path);
        if (
            $fullPath !== false &&
            strpos($fullPath, __DIR__) === 0 &&
            is_file($fullPath)
        ) {
            return false;
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 配置
$config = [
    'font_path' => __DIR__ . '/fonts/',
    'output_path' => __DIR__ . '/output/',
    'config_path' => __DIR__ . '/configs/',
    'default_font' => 'msyh.ttf',
    // Fallback字体列表，按顺序查找可用字体
    'font_fallbacks' => [
        'msyh.ttf',
        'wqy-zenhei.ttc',
        '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
    ]
];

// 创建必要的目录
foreach ([$config['font_path'], $config['output_path'], $config['config_path']] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

/**
 * 判断路径是否为绝对路径
 */
function isAbsolutePath($path) {
    if ($path === '') {
        return false;
    }
    
    if ($path[0] === '/' || $path[0] === '\\') {
        return true;
    }
    
    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
        return true;
    }
    
    return strpos($path, __DIR__) === 0;
}

/**
 * 按优先级解析可用字体文件路径
 */
function resolveFontPath($fontName) {
    global $config;
    
    $fontName = trim((string) $fontName);
    $candidates = [];
    
    if ($fontName !== '') {
        if (isAbsolutePath($fontName)) {
            $candidates[] = $fontName;
        } else {
            $candidates[] = $config['font_path'] . $fontName;
        }
    }
    
    foreach ($config['font_fallbacks'] as $fallback) {
        if ($fallback === '') {
            continue;
        }
        if ($fontName !== '' && $fallback === $fontName) {
            continue;
        }
        if (isAbsolutePath($fallback)) {
            $candidates[] = $fallback;
        } else {
            $candidates[] = $config['font_path'] . $fallback;
        }
    }
    
    foreach ($candidates as $candidate) {
        if (is_readable($candidate)) {
            return $candidate;
        }
    }
    
    return null;
}

/**
 * 字符串转布尔
 */
function toBoolean($value, $default = false) {
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null) {
        return $default;
    }
    
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }
    
    $trueValues = ['1', 'true', 'on', 'yes', 'y'];
    $falseValues = ['0', 'false', 'off', 'no', 'n'];
    
    if (in_array($normalized, $trueValues, true)) {
        return true;
    }
    if (in_array($normalized, $falseValues, true)) {
        return false;
    }
    
    return $default;
}

/**
 * 将文本按最大宽度拆分成多行
 */
function wrapLineByWidth($line, $fontPath, $fontSize, $maxWidth) {
    if ($maxWidth <= 0) {
        return [$line];
    }
    
    $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
    if ($characters === false || empty($characters)) {
        return [$line];
    }
    
    $lines = [];
    $current = '';
    
    foreach ($characters as $char) {
        $candidate = $current . $char;
        $sample = $candidate === '' ? ' ' : $candidate;
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $sample);
        $width = abs($bbox[4] - $bbox[0]);
        
        if ($width <= $maxWidth || $current === '') {
            $current = $candidate;
        } else {
            $lines[] = $current;
            $current = $char;
        }
    }
    
    if ($current !== '') {
        $lines[] = $current;
    }
    
    if (empty($lines)) {
        $lines[] = $line;
    }
    
    return $lines;
}

/**
 * 根据最大宽度准备文本行，自动套用换行
 */
function prepareTextLines($text, $fontPath, $fontSize, $maxWidth) {
    $rawLines = preg_split("/\\r\\n|\\r|\\n/", $text);
    $rawLines = $rawLines === false ? [] : $rawLines;
    
    if (empty($rawLines)) {
        $rawLines = [$text];
    }
    
    $lines = [];
    foreach ($rawLines as $rawLine) {
        $sample = $rawLine === '' ? ' ' : $rawLine;
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $sample);
        $width = abs($bbox[4] - $bbox[0]);
        
        if ($maxWidth > 0 && $width > $maxWidth) {
            $lines = array_merge($lines, wrapLineByWidth($rawLine, $fontPath, $fontSize, $maxWidth));
        } else {
            $lines[] = $rawLine;
        }
    }
    
    if (empty($lines)) {
        $lines[] = $text;
    }
    
    return $lines;
}

/**
 * 限制浮点数在给定范围
 */
function clampFloat($value, $min, $max, $default) {
    if (!is_numeric($value)) {
        return $default;
    }
    
    $float = (float) $value;
    if ($float < $min) return $min;
    if ($float > $max) return $max;
    return $float;
}

/**
 * 解析图片路径，支持相对路径
 */
function resolveImagePath($path, $default) {
    $path = trim((string) $path);
    if ($path === '') {
        return $default;
    }
    
    if (isAbsolutePath($path) && is_readable($path)) {
        return $path;
    }
    
    $normalized = str_replace('\\', '/', $path);
    $normalized = ltrim($normalized, '/');
    $candidate = realpath(__DIR__ . '/' . $normalized);
    
    if ($candidate !== false && strpos($candidate, __DIR__) === 0 && is_readable($candidate)) {
        return $candidate;
    }
    
    return $default;
}

/**
 * 加载图片资源
 */
function loadImageResource($path) {
    if (!is_readable($path)) {
        return null;
    }
    
    $info = @getimagesize($path);
    if ($info === false) {
        return null;
    }
    
    switch ($info[2]) {
        case IMAGETYPE_PNG:
            return @imagecreatefrompng($path);
        case IMAGETYPE_JPEG:
            return @imagecreatefromjpeg($path);
        case IMAGETYPE_GIF:
            return @imagecreatefromgif($path);
        default:
            return null;
    }
}

/**
 * 水平镜像图片资源
 */
function mirrorImageHorizontally($image) {
    if (function_exists('imageflip')) {
        @imageflip($image, IMG_FLIP_HORIZONTAL);
        return $image;
    }
    
    $width = imagesx($image);
    $height = imagesy($image);
    $mirrored = imagecreatetruecolor($width, $height);
    imagealphablending($mirrored, false);
    imagesavealpha($mirrored, true);
    
    for ($x = 0; $x < $width; $x++) {
        imagecopy($mirrored, $image, $width - $x - 1, 0, $x, 0, 1, $height);
    }
    
    return $mirrored;
}

/**
 * 绘制九宫格背景
 */
function drawNinePatch(&$target, $bgPath, $targetWidth, $targetHeight, $slice, $mirror = false) {
    $source = loadImageResource($bgPath);
    if (!$source) {
        return false;
    }
    
    if ($mirror) {
        $mirrored = mirrorImageHorizontally($source);
        if ($mirrored !== $source) {
            imagedestroy($source);
            $source = $mirrored;
        }
    }
    
    $srcW = imagesx($source);
    $srcH = imagesy($source);
    if ($srcW <= 0 || $srcH <= 0) {
        imagedestroy($source);
        return false;
    }
    
    $xStart = clampFloat($slice['x_start'] ?? 0.35, 0.0, 0.95, 0.35);
    $xEnd = clampFloat($slice['x_end'] ?? 0.65, 0.0, 1.0, 0.65);
    $yStart = clampFloat($slice['y_start'] ?? 0.35, 0.0, 0.95, 0.35);
    $yEnd = clampFloat($slice['y_end'] ?? 0.65, 0.0, 1.0, 0.65);
    
    if ($xEnd <= $xStart) {
        $xEnd = min(0.9, $xStart + 0.1);
    }
    if ($yEnd <= $yStart) {
        $yEnd = min(0.9, $yStart + 0.1);
    }
    
    $srcLeft = (int) round($xStart * $srcW);
    $srcRight = (int) round($xEnd * $srcW);
    $srcTop = (int) round($yStart * $srcH);
    $srcBottom = (int) round($yEnd * $srcH);
    
    $leftWidth = $srcLeft;
    $rightWidth = $srcW - $srcRight;
    $topHeight = $srcTop;
    $bottomHeight = $srcH - $srcBottom;
    
    if ($targetWidth < ($leftWidth + $rightWidth) || $targetHeight < ($topHeight + $bottomHeight)) {
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcW, $srcH);
        imagedestroy($source);
        return true;
    }
    
    $destStretchWidth = max(0, $targetWidth - ($leftWidth + $rightWidth));
    $destStretchHeight = max(0, $targetHeight - ($topHeight + $bottomHeight));
    
    $srcX = [0, $srcLeft, $srcRight, $srcW];
    $srcY = [0, $srcTop, $srcBottom, $srcH];
    $destX = [0, $leftWidth, $leftWidth + $destStretchWidth, $targetWidth];
    $destY = [0, $topHeight, $topHeight + $destStretchHeight, $targetHeight];
    
    imagealphablending($target, false);
    imagesavealpha($target, true);
    
    for ($iy = 0; $iy < 3; $iy++) {
        for ($ix = 0; $ix < 3; $ix++) {
            $srcWidth = $srcX[$ix + 1] - $srcX[$ix];
            $srcHeight = $srcY[$iy + 1] - $srcY[$iy];
            $dstWidth = $destX[$ix + 1] - $destX[$ix];
            $dstHeight = $destY[$iy + 1] - $destY[$iy];
            
            if ($srcWidth <= 0 || $srcHeight <= 0 || $dstWidth <= 0 || $dstHeight <= 0) {
                continue;
            }
            
            imagecopyresampled(
                $target,
                $source,
                $destX[$ix],
                $destY[$iy],
                $srcX[$ix],
                $srcY[$iy],
                $dstWidth,
                $dstHeight,
                $srcWidth,
                $srcHeight
            );
        }
    }
    
    imagealphablending($target, true);
    imagedestroy($source);
    return true;
}

/**
 * 计算多行文本尺寸信息
 */
function calculateTextMetrics(array $lines, $fontPath, $fontSize) {
    $maxWidth = 0;
    $lineHeight = 0;
    $boxes = [];
    
    foreach ($lines as $line) {
        $sample = $line === '' ? ' ' : $line;
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $sample);
        $width = abs($bbox[4] - $bbox[0]);
        $height = abs($bbox[5] - $bbox[1]);
        
        $maxWidth = max($maxWidth, $width);
        $lineHeight = max($lineHeight, $height);
        $boxes[] = $bbox;
    }
    
    if ($lineHeight === 0) {
        $fallbackBox = imagettfbbox($fontSize, 0, $fontPath, '汉');
        $lineHeight = abs($fallbackBox[5] - $fallbackBox[1]) ?: $fontSize;
    }
    
    $lineSpacing = max(2, (int) ceil($fontSize * 0.25));
    $lineCount = count($lines);
    $totalHeight = ($lineCount * $lineHeight) + max(0, ($lineCount - 1) * $lineSpacing);
    
    return [
        'max_width' => $maxWidth,
        'line_height' => $lineHeight,
        'line_spacing' => $lineSpacing,
        'total_height' => $totalHeight,
        'boxes' => $boxes
    ];
}

/**
 * 绘制单行文本并应用特效
 */
function drawTextLineWithEffects($image, $fontSize, $fontPath, $text, $x, $y, $color, $effect, $fontWeight) {
    switch ($effect) {
        case 'shadow':
            $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 80);
            imagettftext($image, $fontSize, 0, (int) ($x + 2), (int) ($y + 2), $shadowColor, $fontPath, $text);
            break;
            
        case 'outline':
            $outlineColor = imagecolorallocate($image, 0, 0, 0);
            for ($i = -1; $i <= 1; $i++) {
                for ($j = -1; $j <= 1; $j++) {
                    if ($i !== 0 || $j !== 0) {
                        imagettftext($image, $fontSize, 0, (int) ($x + $i), (int) ($y + $j), $outlineColor, $fontPath, $text);
                    }
                }
            }
            break;
            
        case 'glow':
            $glowColor = imagecolorallocatealpha($image, 255, 255, 255, 80);
            for ($i = -2; $i <= 2; $i++) {
                for ($j = -2; $j <= 2; $j++) {
                    imagettftext($image, $fontSize, 0, (int) ($x + $i), (int) ($y + $j), $glowColor, $fontPath, $text);
                }
            }
            break;
            
        case 'marquee':
        case 'flowlight':
        case 'liuguang':
            $highlightSteps = 12;
            for ($i = -$highlightSteps; $i <= $highlightSteps; $i++) {
                $alpha = max(20, min(110, 90 + abs($i) * 4));
                $highlightColor = imagecolorallocatealpha($image, 255, 255, 255, $alpha);
                $offsetX = (int) ($x + $i);
                $offsetY = (int) ($y - ($i * 0.4));
                imagettftext($image, $fontSize, 0, $offsetX, $offsetY, $highlightColor, $fontPath, $text);
            }
            break;
    }
    
    $fontWeight = (int) $fontWeight;
    if ($fontWeight > 0) {
        $offset = $fontWeight / 10;
        for ($i = -$fontWeight; $i <= $fontWeight; $i++) {
            $ox = $fontWeight === 0 ? 0 : $i * $offset / $fontWeight;
            for ($j = -$fontWeight; $j <= $fontWeight; $j++) {
                $oy = $fontWeight === 0 ? 0 : $j * $offset / $fontWeight;
                if ($i === 0 && $j === 0) {
                    continue;
                }
                imagettftext($image, $fontSize, 0, (int) ($x + $ox), (int) ($y + $oy), $color, $fontPath, $text);
            }
        }
    }
    
    imagettftext($image, $fontSize, 0, (int) $x, (int) $y, $color, $fontPath, $text);
}

// 获取授权的 wxid
function isAuthorizedWxid($wxid) {
    $authorizedWxids = file(__DIR__ . '/wxid.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($wxid, $authorizedWxids);
}

// 获取请求参数
$action = $_GET['ac'] ?? 'all';
$wxid = $_GET['wxid'] ?? '';  // 获取wxid作为配置ID
$start = intval($_GET['start'] ?? 0);
$limit = intval($_GET['limit'] ?? 40);
$keyword = $_GET['keyword'] ?? '';

// 检查 wxid 是否授权
if (!isAuthorizedWxid($wxid)) {
    // 如果 wxid 未授权，替换请求的文字信息
    $keyword = '我是狗，偷接口，偷来接口当小丑';
}

// 处理请求
switch ($action) {
    case 'search':
    case 'all':
        $items = [];
        $text = urldecode($keyword);  // 使用替换后的文字
        
        // 获取用户配置
        if (!empty($wxid)) {
            $userConfig = getConfig($wxid);
            if ($userConfig['code'] === 1) {
                $styles = $userConfig['data']['styles'] ?? [];
                if (isset($userConfig['data']['cleanup'])) {
                    maybeCleanupOutputs($userConfig['data']['cleanup']);
                }
            }
        }
        
        // 如果没有配置或获取失败，使用默认样式
        if (empty($styles)) {
            $styles = [[
                'font_family' => $config['default_font'],
                'font_size' => 32,
                'font_bold' => false,
                'text_align' => 'center',
                'random_color' => false,
                'effect' => 'none',
                'font_color' => '#000000',
                'bg_color' => '#FFFFFF',
                'thumbnail_mode' => false,
                'force_size' => false,
                'auto_size' => true,
                'wrap_auto' => true,
                'wrap_limit' => 0
            ]];
        }
        
        // 生成图片
        foreach ($styles as $style) {
            $requestedFont = $style['font_family'] ?? $config['default_font'];
            $fontFile = resolveFontPath($requestedFont);
            
            if ($fontFile === null) {
                error_log(sprintf('Font not found for request "%s". Checked %s', $requestedFont, json_encode($config['font_fallbacks'])));
                continue;
            }
            
            $params = [
                'font_size' => $style['font_size'] ?? 32,
                'font_color' => $style['font_color'] ?? '#000000',
                'bg_color' => $style['bg_color'] ?? '#FFFFFF',
                'bg_image_size' => $style['bg_image_size'] ?? 'cover',
                'font_file' => $fontFile,
                'font_weight' => $style['font_weight'] ?? 0,
                'pos_x' => $style['pos_x'] ?? 50,
                'pos_y' => $style['pos_y'] ?? 50,
                'effect' => $style['effect'] ?? 'none',
                'random_color' => $style['random_color'] ?? false,
                'auto_size' => $style['auto_size'] ?? false,
                'wrap_auto' => array_key_exists('wrap_auto', $style) ? (bool) $style['wrap_auto'] : true,
                'wrap_limit' => isset($style['wrap_limit']) ? (int) $style['wrap_limit'] : 0,
                'layout_mode' => $style['layout_mode'] ?? 'standard',
                'chat_bg' => $style['chat_bg'] ?? '',
                'chat_mirror' => $style['chat_mirror'] ?? false,
                'chat_slice_x_start' => $style['chat_slice_x_start'] ?? null,
                'chat_slice_x_end' => $style['chat_slice_x_end'] ?? null,
                'chat_slice_y_start' => $style['chat_slice_y_start'] ?? null,
                'chat_slice_y_end' => $style['chat_slice_y_end'] ?? null,
                'chat_padding_left' => $style['chat_padding_left'] ?? null,
                'chat_padding_right' => $style['chat_padding_right'] ?? null,
                'chat_padding_top' => $style['chat_padding_top'] ?? null,
                'chat_padding_bottom' => $style['chat_padding_bottom'] ?? null,
                'chat_max_width' => $style['chat_max_width'] ?? null,
                'chat_min_width' => $style['chat_min_width'] ?? null,
                'chat_min_height' => $style['chat_min_height'] ?? null
            ];
            
            // 如果启用了随机颜色，生成新的随机颜色
            if ($params['random_color']) {
                $params['font_color'] = sprintf('#%06X', mt_rand(0, 0xFFFFFF)); // 生成随机颜色
            } else {
                // 确保使用固定颜色
                $params['font_color'] = $style['font_color'] ?? '#000000';
            }
            
            $imageInfo = generateImage($text, $params);
            if ($imageInfo) {
                $items[] = [
                    'title' => time() . rand(1000, 9999),
                    'url' => $imageInfo['url']
                ];
            }
        }
        
        // 分页处理
        $totalSize = count($items);
        $items = array_slice($items, $start, $limit);
        
        echo json_encode([
            'items' => $items,
            'pageNum' => floor($start / $limit) + 1,
            'pageSize' => $limit,
            'totalPages' => ceil($totalSize / $limit),
            'totalSize' => $totalSize
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case 'config':
        // 处理配置保存和获取
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = saveConfig($data);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } else {
            $id = $_GET['id'] ?? '';
            $result = getConfig($id);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'items':
        // 处理分页查询
        $keyword = $_GET['keyword'] ?? '';
        $pageNum = intval($_GET['pageNum'] ?? 1);
        $pageSize = intval($_GET['pageSize'] ?? 30);
        
        $items = [];
        if (!empty($keyword)) {
            $defaultFont = resolveFontPath($config['default_font']);
            if ($defaultFont === null) {
                error_log('Default font not found when handling items action.');
                echo json_encode([
                    'totalSize' => 0,
                    'totalPages' => 0,
                    'pageSize' => $pageSize,
                    'items' => [],
                    'msg' => '未找到可用字体文件，请先上传字体'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $params = [
                'font_size' => 32,
                'font_color' => '#000000',
                'bg_color' => '#FFFFFF',
                'font_file' => $defaultFont,
                'padding' => 20,
                'auto_size' => true,
                'wrap_auto' => true,
                'wrap_limit' => 0,
                'layout_mode' => 'standard'
            ];
            
            $imageInfo = generateImage($keyword, $params);
            if ($imageInfo) {
                $items[] = [
                    'title' => $keyword,
                    'url' => $imageInfo['url']
                ];
            }
        }
        
        echo json_encode([
            'totalSize' => count($items),
            'totalPages' => 1,
            'pageSize' => $pageSize,
            'items' => $items
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case 'fonts':
        // 获取字体列表
        $fonts = getFontList();
        echo json_encode([
            'code' => 1,
            'msg' => '获取成功',
            'data' => $fonts
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    // case 'authorize':
    //     // 授权 wxid
    //     if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //         $data = json_decode(file_get_contents('php://input'), true);
    //         $wxid = trim($data['wxid'] ?? '');
            
    //         // 验证 wxid
    //         if (empty($wxid)) {
    //             echo json_encode(['code' => 0, 'msg' => 'wxid不能为空'], JSON_UNESCAPED_UNICODE);
    //             break;
    //         }
            
    //         if (strlen($wxid) > 30) {
    //             echo json_encode(['code' => 0, 'msg' => 'wxid不能超过30个字符'], JSON_UNESCAPED_UNICODE);
    //             break;
    //         }
            
    //         // 读取现有的 wxid 列表
    //         $wxidFile = __DIR__ . '/wxid.txt';
    //         $existingWxids = [];
            
    //         if (file_exists($wxidFile)) {
    //             $existingWxids = file($wxidFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    //         }
            
    //         // 检查是否已存在
    //         if (in_array($wxid, $existingWxids)) {
    //             echo json_encode(['code' => 0, 'msg' => '该wxid已经授权过了'], JSON_UNESCAPED_UNICODE);
    //             break;
    //         }
            
    //         // 添加新的 wxid
    //         if (file_put_contents($wxidFile, $wxid . PHP_EOL, FILE_APPEND | LOCK_EX)) {
    //             echo json_encode(['code' => 1, 'msg' => '授权成功'], JSON_UNESCAPED_UNICODE);
    //         } else {
    //             echo json_encode(['code' => 0, 'msg' => '授权失败，请检查文件权限'], JSON_UNESCAPED_UNICODE);
    //         }
    //     } else {
    //         echo json_encode(['code' => 0, 'msg' => '请使用POST方法'], JSON_UNESCAPED_UNICODE);
    //     }
    //     break;
        
    case 'upload':
        // 上传字体文件
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 检查是否有文件上传
            if (!isset($_FILES['font']) || $_FILES['font']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['code' => 0, 'msg' => '文件上传失败'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $file = $_FILES['font'];
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileTmpPath = $file['tmp_name'];
            
            // 验证文件扩展名
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($fileExtension !== 'ttf' && $fileExtension !== 'ttc') {
                echo json_encode(['code' => 0, 'msg' => '只支持TTF/TTC格式的字体文件'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // 验证文件大小 (50MB = 50 * 1024 * 1024 bytes)
            if ($fileSize > 50 * 1024 * 1024) {
                echo json_encode(['code' => 0, 'msg' => '文件大小不能超过50MB'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // 确保字体目录存在
            $fontPath = $config['font_path'];
            if (!is_dir($fontPath)) {
                mkdir($fontPath, 0777, true);
            }
            
            // 保存文件
            $destPath = $fontPath . $fileName;
            
            // 检查文件是否已存在
            if (file_exists($destPath)) {
                echo json_encode(['code' => 0, 'msg' => '文件已存在，请重命名后再上传'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // 移动文件到目标目录
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // 设置文件权限
                chmod($destPath, 0644);
                echo json_encode([
                    'code' => 1,
                    'msg' => '上传成功',
                    'data' => [
                        'filename' => $fileName,
                        'size' => $fileSize
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['code' => 0, 'msg' => '保存文件失败'], JSON_UNESCAPED_UNICODE);
            }
        } else {
            echo json_encode(['code' => 0, 'msg' => '请使用POST方法'], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'subset':
        // 获取字体子集
        $text = $_GET['text'] ?? '预览文字';  // 要显示的文字
        $font = $_GET['font'] ?? '';  // 字体文件名
        $fontSize = intval($_GET['size'] ?? 32);
        $fontWeight = intval($_GET['weight'] ?? 0);  // 字体粗细 0-10
        $posX = intval($_GET['posX'] ?? 50);  // 水平位置百分比 0-100
        $posY = intval($_GET['posY'] ?? 50);  // 垂直位置百分比 0-100
        $fontColor = $_GET['color'] ?? '#000000';
        $bgColor = $_GET['bgcolor'] ?? '#FFFFFF';
        $bgImageSize = $_GET['bgsize'] ?? 'cover';  // 背景图缩放方式
        $effect = $_GET['effect'] ?? 'none';
        $isRandom = filter_var($_GET['random'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $autoSize = filter_var($_GET['autoSize'] ?? false, FILTER_VALIDATE_BOOLEAN);  // 自动字号
        $wrapAuto = toBoolean($_GET['wrapAuto'] ?? true, true);
        $wrapLimitValue = intval($_GET['wrapLimit'] ?? 0);
        $layoutMode = $_GET['layoutMode'] ?? 'standard';
        $chatBg = $_GET['chatBg'] ?? '';
        $chatMirror = toBoolean($_GET['chatMirror'] ?? false, false);
        $chatSliceXStart = isset($_GET['chatSliceXStart']) ? (float) $_GET['chatSliceXStart'] : null;
        $chatSliceXEnd = isset($_GET['chatSliceXEnd']) ? (float) $_GET['chatSliceXEnd'] : null;
        $chatSliceYStart = isset($_GET['chatSliceYStart']) ? (float) $_GET['chatSliceYStart'] : null;
        $chatSliceYEnd = isset($_GET['chatSliceYEnd']) ? (float) $_GET['chatSliceYEnd'] : null;
        $chatPadLeft = isset($_GET['chatPadLeft']) ? (float) $_GET['chatPadLeft'] : null;
        $chatPadRight = isset($_GET['chatPadRight']) ? (float) $_GET['chatPadRight'] : null;
        $chatPadTop = isset($_GET['chatPadTop']) ? (float) $_GET['chatPadTop'] : null;
        $chatPadBottom = isset($_GET['chatPadBottom']) ? (float) $_GET['chatPadBottom'] : null;
        $chatMaxWidth = isset($_GET['chatMaxWidth']) ? (int) $_GET['chatMaxWidth'] : null;
        $chatMinWidth = isset($_GET['chatMinWidth']) ? (int) $_GET['chatMinWidth'] : null;
        $chatMinHeight = isset($_GET['chatMinHeight']) ? (int) $_GET['chatMinHeight'] : null;
        
        if ($font === '') {
            echo json_encode(['code' => 0, 'msg' => '字体不能为空'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $fontPath = resolveFontPath($font);
        if ($fontPath === null) {
            echo json_encode(['code' => 0, 'msg' => '字体文件不存在或不可读'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $params = [
            'font_size' => $fontSize,
            'font_color' => $fontColor,
            'bg_color' => $bgColor,
            'bg_image_size' => $bgImageSize,
            'font_file' => $fontPath,
            'font_weight' => $fontWeight,
            'pos_x' => $posX,
            'pos_y' => $posY,
            'effect' => $effect,
            'random_color' => $isRandom,
            'auto_size' => $autoSize,
            'wrap_auto' => $wrapAuto,
            'wrap_limit' => $wrapLimitValue,
            'layout_mode' => $layoutMode,
            'chat_bg' => $chatBg,
            'chat_mirror' => $chatMirror,
            'chat_slice_x_start' => $chatSliceXStart,
            'chat_slice_x_end' => $chatSliceXEnd,
            'chat_slice_y_start' => $chatSliceYStart,
            'chat_slice_y_end' => $chatSliceYEnd,
            'chat_padding_left' => $chatPadLeft,
            'chat_padding_right' => $chatPadRight,
            'chat_padding_top' => $chatPadTop,
            'chat_padding_bottom' => $chatPadBottom,
            'chat_max_width' => $chatMaxWidth,
            'chat_min_width' => $chatMinWidth,
            'chat_min_height' => $chatMinHeight
        ];
        
        $result = generateImage($text, $params);
        if ($result) {
            $relativeUrl = parse_url($result['url'], PHP_URL_PATH);
            $relativeUrl = $relativeUrl !== false ? ltrim($relativeUrl, '/') : '';
            if ($relativeUrl === '') {
                $relativeUrl = 'output/' . basename($result['url']);
            }
            if (strpos($relativeUrl, 'output/') !== 0) {
                $relativeUrl = 'output/' . basename($relativeUrl);
            }
            
            echo json_encode([
                'code' => 1,
                'msg' => '成功',
                'data' => [
                    'url' => $relativeUrl
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'code' => 0,
                'msg' => '生成失败'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    default:
        echo json_encode([
            'code' => 0,
            'msg' => '无效的请求'
        ], JSON_UNESCAPED_UNICODE);
}

/**
 * 获取样式列表
 */
function getStyles($configId = '') {
    global $config;
    
    // 如果有配置ID，尝试读取配置
    if (!empty($configId)) {
        $configFile = $config['config_path'] . $configId . '.json';
        if (file_exists($configFile)) {
            $userConfig = json_decode(file_get_contents($configFile), true);
            if ($userConfig && isset($userConfig['styles'])) {
                return $userConfig['styles'];
            }
        }
    }
    
    // 默认样式
    return [
        [
            'title' => '默认黑',
            'font_size' => 32,
            'font_color' => '#000000',
            'bg_color' => '#FFFFFF',
            'layout_mode' => 'standard'
        ],
        [
            'title' => '反转白',
            'font_size' => 32,
            'font_color' => '#FFFFFF',
            'bg_color' => '#000000',
            'layout_mode' => 'standard'
        ],
        // ... 其他默认样式 ...
    ];
}

/**
 * 保存配置
 */
function saveConfig($data) {
    global $config;
    
    if (empty($data['id'])) {
        return ['code' => 0, 'msg' => '配置ID不能为空'];
    }
    
    $configFile = $config['config_path'] . $data['id'] . '.json';
    if (file_put_contents($configFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
        return ['code' => 1, 'msg' => '保存成功'];
    }
    
    return ['code' => 0, 'msg' => '保存失败'];
}

/**
 * 获取配置
 */
function getConfig($id) {
    global $config;
    
    try {
        // 尝试读取配置文件
        $configFile = $config['config_path'] . $id . '.json';
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            $data = json_decode($content, true);
            return [
                'code' => 1,
                'msg' => '获取成功',
                'data' => $data
            ];
        }
        return [
            'code' => 0,
            'msg' => '配置不存在'
        ];
    } catch (Exception $e) {
        return [
            'code' => 0,
            'msg' => '读取配置失败'
        ];
    }
}

/**
 * 生成图片
 */
function generateImage($text, $params) {
    try {
        $fontFile = $params['font_file'];
        $requestedFontSize = isset($params['font_size']) ? max(12, (int) $params['font_size']) : 32;
        $layoutMode = $params['layout_mode'] ?? 'standard';
        
        if ($layoutMode === 'chat') {
            return generateChatBubbleImage($text, $params);
        }
        
        $autoSize = toBoolean($params['auto_size'] ?? true, true);
        $wrapAuto = toBoolean($params['wrap_auto'] ?? true, true);
        $wrapLimitInput = isset($params['wrap_limit']) ? (int) $params['wrap_limit'] : 0;
        
        $canvasSize = 1024;
        $padding = 60;
        $maxCanvasWidth = max(200, $canvasSize - ($padding * 2));
        $maxCanvasHeight = max(200, $canvasSize - ($padding * 2));
        
        $effectiveWrapLimit = 0;
        if ($wrapAuto) {
            if ($wrapLimitInput > 0) {
                $effectiveWrapLimit = min($wrapLimitInput, $maxCanvasWidth);
            } else {
                $effectiveWrapLimit = $maxCanvasWidth;
            }
        } else {
            if ($wrapLimitInput > 0) {
                $effectiveWrapLimit = max(50, $wrapLimitInput);
            } else {
                $effectiveWrapLimit = 0;
            }
        }
        
        $measureLayout = function($fontSize, $wrapLimit) use ($text, $fontFile, $wrapAuto) {
            $lines = prepareTextLines($text, $fontFile, $fontSize, $wrapLimit);
            if ($wrapAuto && $wrapLimit > 0 && count($lines) === 1 && mb_strlen($text, 'UTF-8') > 12) {
                $adjustWrap = $wrapLimit;
                $attempts = 0;
                while (count($lines) === 1 && $adjustWrap > 180 && $attempts < 4) {
                    $adjustWrap = max(180, (int) ($adjustWrap * 0.75));
                    $lines = prepareTextLines($text, $fontFile, $fontSize, $adjustWrap);
                    $attempts++;
                }
            }
            $metrics = calculateTextMetrics($lines, $fontFile, $fontSize);
            return [$lines, $metrics];
        };
        
        $minFontSize = 12;
        $maxFontSize = 900;
        
        $searchMax = $autoSize ? $maxFontSize : max($minFontSize, min($requestedFontSize, $maxFontSize));
        $low = $minFontSize;
        $high = $searchMax;
        $bestSize = max($minFontSize, min($requestedFontSize, $searchMax));
        $bestLayout = $measureLayout($bestSize, $effectiveWrapLimit);
        if ($bestLayout[1]['max_width'] > $maxCanvasWidth || $bestLayout[1]['total_height'] > $maxCanvasHeight) {
            $bestLayout = null;
        }
        
        while ($low <= $high) {
            $mid = (int) (($low + $high) / 2);
            $layout = $measureLayout($mid, $effectiveWrapLimit);
            $metrics = $layout[1];
            
            if ($metrics['max_width'] <= $maxCanvasWidth && $metrics['total_height'] <= $maxCanvasHeight) {
                $bestSize = $mid;
                $bestLayout = $layout;
                $low = $mid + 1;
                if (!$autoSize && $mid >= $requestedFontSize) {
                    break;
                }
            } else {
                $high = $mid - 1;
            }
        }
        
        if ($bestLayout === null) {
            $bestSize = $minFontSize;
            $bestLayout = $measureLayout($bestSize, $effectiveWrapLimit);
        }
        
        $fontSize = $bestSize;
        $lines = $bestLayout[0];
        $metrics = $bestLayout[1];
        $textWidth = $metrics['max_width'];
        $textHeight = $metrics['total_height'];
        $lineHeight = $metrics['line_height'];
        $lineSpacing = $metrics['line_spacing'];
        $lineBoxes = $metrics['boxes'];
        
        $width = $canvasSize;
        $height = $canvasSize;
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        
        $bgColorSetting = $params['bg_color'] ?? '#FFFFFF';
        if ($bgColorSetting === 'image') {
            $bg_image_path = __DIR__ . '/img/background.png';
            if (file_exists($bg_image_path)) {
                $imageInfo = @getimagesize($bg_image_path);
                $bg_image = false;
                
                if ($imageInfo !== false) {
                    switch ($imageInfo[2]) {
                        case IMAGETYPE_PNG:
                            $bg_image = @imagecreatefrompng($bg_image_path);
                            break;
                        case IMAGETYPE_JPEG:
                            $bg_image = @imagecreatefromjpeg($bg_image_path);
                            break;
                        case IMAGETYPE_GIF:
                            $bg_image = @imagecreatefromgif($bg_image_path);
                            break;
                    }
                }
                
                if ($bg_image !== false) {
                    $bg_width = imagesx($bg_image);
                    $bg_height = imagesy($bg_image);
                    $bgImageSize = $params['bg_image_size'] ?? 'cover';
                    
                    imagealphablending($image, false);
                    
                    switch ($bgImageSize) {
                        case 'cover':
                            $ratio_w = $width / $bg_width;
                            $ratio_h = $height / $bg_height;
                            $ratio = max($ratio_w, $ratio_h);
                            $new_w = max(1, (int) ($bg_width * $ratio));
                            $new_h = max(1, (int) ($bg_height * $ratio));
                            $x_offset = (int) (($width - $new_w) / 2);
                            $y_offset = (int) (($height - $new_h) / 2);
                            imagecopyresampled($image, $bg_image, $x_offset, $y_offset, 0, 0, $new_w, $new_h, $bg_width, $bg_height);
                            break;
                            
                        case 'contain':
                            $ratio_w = $width / $bg_width;
                            $ratio_h = $height / $bg_height;
                            $ratio = min($ratio_w, $ratio_h);
                            $new_w = max(1, (int) ($bg_width * $ratio));
                            $new_h = max(1, (int) ($bg_height * $ratio));
                            $x_offset = (int) (($width - $new_w) / 2);
                            $y_offset = (int) (($height - $new_h) / 2);
                            $white = imagecolorallocate($image, 255, 255, 255);
                            imagefill($image, 0, 0, $white);
                            imagecopyresampled($image, $bg_image, $x_offset, $y_offset, 0, 0, $new_w, $new_h, $bg_width, $bg_height);
                            break;
                            
                        case 'stretch':
                            imagecopyresampled($image, $bg_image, 0, 0, 0, 0, $width, $height, $bg_width, $bg_height);
                            break;
                            
                        case 'tile':
                            for ($x_pos = 0; $x_pos < $width; $x_pos += $bg_width) {
                                for ($y_pos = 0; $y_pos < $height; $y_pos += $bg_height) {
                                    imagecopy($image, $bg_image, $x_pos, $y_pos, 0, 0, $bg_width, $bg_height);
                                }
                            }
                            break;
                            
                        default:
                            imagecopyresampled($image, $bg_image, 0, 0, 0, 0, $width, $height, $bg_width, $bg_height);
                    }
                    
                    imagealphablending($image, true);
                    imagedestroy($bg_image);
                }
            } else {
                $bg = imagecolorallocate($image, 255, 255, 255);
                imagefill($image, 0, 0, $bg);
            }
        } elseif ($bgColorSetting === 'transparent') {
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
        } else {
            $bg_color = hex2rgb($bgColorSetting);
            $bg = imagecolorallocate($image, $bg_color[0], $bg_color[1], $bg_color[2]);
            imagefill($image, 0, 0, $bg);
        }
        
        $isRandomColor = !empty($params['random_color']);
        if ($isRandomColor) {
            $r = rand(0, 255);
            $g = rand(0, 255);
            $b = rand(0, 255);
            $color = imagecolorallocate($image, $r, $g, $b);
        } else {
            $fontColorHex = $params['font_color'] ?? '#000000';
            $fontColor = hex2rgb($fontColorHex);
            $color = imagecolorallocate($image, $fontColor[0], $fontColor[1], $fontColor[2]);
        }
        
        $pos_x = isset($params['pos_x']) ? (float) $params['pos_x'] : 50;
        $pos_y = isset($params['pos_y']) ? (float) $params['pos_y'] : 50;
        $effect = $params['effect'] ?? 'none';
        $fontWeight = isset($params['font_weight']) ? (int) $params['font_weight'] : 0;
        
        $availableWidth = max(0, $width - $textWidth - ($padding * 2));
        $availableHeight = max(0, $height - $textHeight - ($padding * 2));
        
        if ($pos_x == 50) {
            $baseLeft = ($width - $textWidth) / 2;
        } else {
            $baseLeft = $padding + ($availableWidth * $pos_x / 100);
        }
        
        if ($pos_y == 50) {
            $baseBaseline = (($height - $textHeight) / 2) + $lineHeight;
        } else {
            $baseBaseline = $padding + ($availableHeight * $pos_y / 100) + $lineHeight;
        }
        
        foreach ($lines as $index => $line) {
            $lineText = $line === '' ? ' ' : $line;
            $lineBox = $lineBoxes[$index] ?? imagettfbbox($fontSize, 0, $fontFile, $lineText);
            $lineX = $baseLeft - $lineBox[0];
            $lineY = $baseBaseline + $index * ($lineHeight + $lineSpacing);
            
            drawTextLineWithEffects(
                $image,
                $fontSize,
                $fontFile,
                $lineText,
                $lineX,
                $lineY,
                $color,
                $effect,
                $fontWeight
            );
        }
        
        $filename = md5($text . json_encode($params)) . '.png';
        $filepath = $GLOBALS['config']['output_path'] . $filename;
        imagepng($image, $filepath);
        
        imagedestroy($image);
        
        return [
            'url' => 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/output/' . $filename
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 生成聊天气泡模式图片
 */
function generateChatBubbleImage($text, $params) {
    try {
        $fontFile = $params['font_file'];
        $fontSize = isset($params['font_size']) ? max(12, (int) $params['font_size']) : 32;
        $wrapAuto = toBoolean($params['wrap_auto'] ?? true, true);
        $wrapLimitInput = isset($params['wrap_limit']) ? (int) $params['wrap_limit'] : 0;
        
        $chatMaxWidth = isset($params['chat_max_width']) ? max(160, (int) $params['chat_max_width']) : 720;
        $chatMinWidth = isset($params['chat_min_width']) ? max(120, (int) $params['chat_min_width']) : 220;
        $chatMinHeight = isset($params['chat_min_height']) ? max(80, (int) $params['chat_min_height']) : 140;
        
        $padLeftRatio = clampFloat($params['chat_padding_left'] ?? 0.22, 0.0, 0.45, 0.22);
        $padRightRatio = clampFloat($params['chat_padding_right'] ?? 0.22, 0.0, 0.45, 0.22);
        $padTopRatio = clampFloat($params['chat_padding_top'] ?? 0.25, 0.0, 0.45, 0.25);
        $padBottomRatio = clampFloat($params['chat_padding_bottom'] ?? 0.25, 0.0, 0.45, 0.25);
        
        $horizontalRatio = max(0.05, 1 - ($padLeftRatio + $padRightRatio));
        $verticalRatio = max(0.05, 1 - ($padTopRatio + $padBottomRatio));
        
        $effectiveWrapLimit = 0;
        if ($wrapAuto) {
            if ($wrapLimitInput > 0) {
                $effectiveWrapLimit = min($wrapLimitInput, $chatMaxWidth);
            } else {
                $effectiveWrapLimit = $chatMaxWidth;
            }
        } else {
            $effectiveWrapLimit = $wrapLimitInput > 0 ? $wrapLimitInput : 2048;
        }
        $effectiveWrapLimit = max(40, $effectiveWrapLimit);
        
        $lines = prepareTextLines($text, $fontFile, $fontSize, $effectiveWrapLimit);
        $metrics = calculateTextMetrics($lines, $fontFile, $fontSize);
        $textWidth = max(1, $metrics['max_width']);
        $textHeight = max(1, $metrics['total_height']);
        $lineHeight = $metrics['line_height'];
        $lineSpacing = $metrics['line_spacing'];
        $lineBoxes = $metrics['boxes'];
        
        $attempts = 0;
        while ($wrapAuto && $textWidth / $horizontalRatio > $chatMaxWidth && $attempts < 4) {
            $effectiveWrapLimit = max(80, (int) ($effectiveWrapLimit * 0.85));
            $lines = prepareTextLines($text, $fontFile, $fontSize, $effectiveWrapLimit);
            $metrics = calculateTextMetrics($lines, $fontFile, $fontSize);
            $textWidth = max(1, $metrics['max_width']);
            $textHeight = max(1, $metrics['total_height']);
            $lineHeight = $metrics['line_height'];
            $lineSpacing = $metrics['line_spacing'];
            $lineBoxes = $metrics['boxes'];
            $attempts++;
        }
        
        $bubbleWidth = max($chatMinWidth, (int) ceil($textWidth / $horizontalRatio));
        if ($wrapAuto) {
            $bubbleWidth = min($bubbleWidth, max($chatMinWidth, $chatMaxWidth));
        }
        $bubbleHeight = max($chatMinHeight, (int) ceil($textHeight / $verticalRatio));
        
        $bubbleWidth = min(2048, $bubbleWidth);
        $bubbleHeight = min(2048, $bubbleHeight);
        
        $paddingLeft = max(8, (int) round($bubbleWidth * $padLeftRatio));
        $paddingRight = max(8, (int) round($bubbleWidth * $padRightRatio));
        $paddingTop = max(8, (int) round($bubbleHeight * $padTopRatio));
        $paddingBottom = max(8, (int) round($bubbleHeight * $padBottomRatio));
        
        $contentWidth = max(20, $bubbleWidth - $paddingLeft - $paddingRight);
        if ($contentWidth < $textWidth) {
            $bubbleWidth = $textWidth + $paddingLeft + $paddingRight + 6;
            $bubbleWidth = max($bubbleWidth, $chatMinWidth);
            $paddingLeft = max(8, (int) round($bubbleWidth * $padLeftRatio));
            $paddingRight = max(8, (int) round($bubbleWidth * $padRightRatio));
            $contentWidth = max(20, $bubbleWidth - $paddingLeft - $paddingRight);
        }
        
        $contentHeight = max(20, $bubbleHeight - $paddingTop - $paddingBottom);
        if ($contentHeight < $textHeight) {
            $bubbleHeight = $textHeight + $paddingTop + $paddingBottom + 6;
            $bubbleHeight = max($bubbleHeight, $chatMinHeight);
            $paddingTop = max(8, (int) round($bubbleHeight * $padTopRatio));
            $paddingBottom = max(8, (int) round($bubbleHeight * $padBottomRatio));
            $contentHeight = max(20, $bubbleHeight - $paddingTop - $paddingBottom);
        }
        
        $bubbleWidth = max($bubbleWidth, $paddingLeft + $paddingRight + $textWidth + 10);
        $bubbleHeight = max($bubbleHeight, $paddingTop + $paddingBottom + $textHeight + 10);
        $bubbleWidth = min(2048, $bubbleWidth);
        $bubbleHeight = min(2048, $bubbleHeight);
        
        $bgDefault = __DIR__ . '/img/background.png';
        $chatBgPath = resolveImagePath($params['chat_bg'] ?? '', $bgDefault);
        $chatMirror = toBoolean($params['chat_mirror'] ?? false, false);
        
        $slice = [
            'x_start' => clampFloat($params['chat_slice_x_start'] ?? 0.35, 0.05, 0.95, 0.35),
            'x_end' => clampFloat($params['chat_slice_x_end'] ?? 0.65, 0.05, 1.0, 0.65),
            'y_start' => clampFloat($params['chat_slice_y_start'] ?? 0.35, 0.05, 0.95, 0.35),
            'y_end' => clampFloat($params['chat_slice_y_end'] ?? 0.65, 0.05, 1.0, 0.65),
        ];
        if ($slice['x_end'] <= $slice['x_start']) {
            $slice['x_end'] = min(0.95, $slice['x_start'] + 0.1);
        }
        if ($slice['y_end'] <= $slice['y_start']) {
            $slice['y_end'] = min(0.95, $slice['y_start'] + 0.1);
        }
        
        $image = imagecreatetruecolor($bubbleWidth, $bubbleHeight);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        
        $drawn = drawNinePatch($image, $chatBgPath, $bubbleWidth, $bubbleHeight, $slice, $chatMirror);
        if (!$drawn) {
            $fallback = imagecolorallocatealpha($image, 255, 238, 200, 0);
            imagefill($image, 0, 0, $fallback);
        }
        imagealphablending($image, true);
        
        $isRandomColor = !empty($params['random_color']);
        if ($isRandomColor) {
            $r = rand(0, 255);
            $g = rand(0, 255);
            $b = rand(0, 255);
            $color = imagecolorallocate($image, $r, $g, $b);
        } else {
            $fontColorHex = $params['font_color'] ?? '#000000';
            $fontColor = hex2rgb($fontColorHex);
            $color = imagecolorallocate($image, $fontColor[0], $fontColor[1], $fontColor[2]);
        }
        
        $posX = isset($params['pos_x']) ? max(0, min(100, (float) $params['pos_x'])) : 50;
        $posY = isset($params['pos_y']) ? max(0, min(100, (float) $params['pos_y'])) : 50;
        $effect = $params['effect'] ?? 'none';
        $fontWeight = isset($params['font_weight']) ? (int) $params['font_weight'] : 0;
        
        $contentWidth = max(10, $bubbleWidth - $paddingLeft - $paddingRight);
        $contentHeight = max(10, $bubbleHeight - $paddingTop - $paddingBottom);
        
        $blockLeft = $paddingLeft;
        if ($contentWidth > $textWidth) {
            if ($posX == 50) {
                $blockLeft = $paddingLeft + ($contentWidth - $textWidth) / 2;
            } elseif ($posX == 100) {
                $blockLeft = $paddingLeft + $contentWidth - $textWidth;
            } elseif ($posX == 0) {
                $blockLeft = $paddingLeft;
            } else {
                $blockLeft = $paddingLeft + ($contentWidth - $textWidth) * ($posX / 100);
            }
        }
        
        $blockTop = $paddingTop;
        if ($contentHeight > $textHeight) {
            if ($posY == 50) {
                $blockTop = $paddingTop + ($contentHeight - $textHeight) / 2;
            } elseif ($posY == 100) {
                $blockTop = $paddingTop + $contentHeight - $textHeight;
            } elseif ($posY == 0) {
                $blockTop = $paddingTop;
            } else {
                $blockTop = $paddingTop + ($contentHeight - $textHeight) * ($posY / 100);
            }
        }
        
        $blockBaseline = $blockTop + $lineHeight;
        foreach ($lines as $index => $line) {
            $lineText = $line === '' ? ' ' : $line;
            $lineBox = $lineBoxes[$index] ?? imagettfbbox($fontSize, 0, $fontFile, $lineText);
            $lineX = $blockLeft - $lineBox[0];
            $lineY = $blockBaseline + $index * ($lineHeight + $lineSpacing);
            
            drawTextLineWithEffects(
                $image,
                $fontSize,
                $fontFile,
                $lineText,
                $lineX,
                $lineY,
                $color,
                $effect,
                $fontWeight
            );
        }
        
        $filename = md5($text . json_encode($params)) . '.png';
        $filepath = $GLOBALS['config']['output_path'] . $filename;
        imagepng($image, $filepath);
        imagedestroy($image);
        
        return [
            'url' => 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/output/' . $filename
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 根据配置定期清理 output 目录中的过期文件
 */
function maybeCleanupOutputs($settings) {
    if (!is_array($settings) || empty($settings['enabled'])) {
        return;
    }
    
    $outputDir = __DIR__ . '/output';
    if (!is_dir($outputDir) || !is_writable($outputDir)) {
        return;
    }
    
    $ttlMinutes = isset($settings['ttl_minutes']) ? (int) $settings['ttl_minutes'] : 1440;
    $intervalMinutes = isset($settings['interval_minutes']) ? (int) $settings['interval_minutes'] : 60;
    
    $ttlMinutes = max(1, $ttlMinutes);
    $intervalMinutes = max(5, $intervalMinutes);
    
    $stateFile = $outputDir . '/.cleanup_state.json';
    $state = [
        'last_run' => 0,
        'ttl_minutes' => $ttlMinutes
    ];
    
    if (is_file($stateFile)) {
        $content = @file_get_contents($stateFile);
        $decoded = $content ? json_decode($content, true) : null;
        if (is_array($decoded)) {
            $state = array_merge($state, $decoded);
        }
    }
    
    $now = time();
    $elapsed = $now - (int) ($state['last_run'] ?? 0);
    $ttlChanged = (int) ($state['ttl_minutes'] ?? 0) !== $ttlMinutes;
    if ($elapsed < ($intervalMinutes * 60) && !$ttlChanged) {
        return;
    }
    
    $ttlSeconds = $ttlMinutes * 60;
    $deleted = 0;
    
    $iterator = new DirectoryIterator($outputDir);
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDot() || !$fileInfo->isFile()) {
            continue;
        }
        $basename = $fileInfo->getBasename();
        if ($basename[0] === '.') {
            continue;
        }
        
        $age = $now - $fileInfo->getMTime();
        if ($age > $ttlSeconds) {
            @unlink($fileInfo->getPathname());
            $deleted++;
        }
    }
    
    $state['last_run'] = $now;
    $state['ttl_minutes'] = $ttlMinutes;
    $state['deleted_last_run'] = $deleted;
    
    @file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * 创建渐变背景
 */
function createGradientBackground($image, $width, $height, $gradient) {
    $width = max(1, (int) $width);
    $height = max(1, (int) $height);
    
    $temp = imagecreatetruecolor($width, $height);
    
    $start_color = hex2rgb($gradient['start']);
    $end_color = hex2rgb($gradient['end']);
    
    for($i = 0; $i < $height; $i++) {
        $ratio = $i / $height;
        $r = $start_color[0] * (1 - $ratio) + $end_color[0] * $ratio;
        $g = $start_color[1] * (1 - $ratio) + $end_color[1] * $ratio;
        $b = $start_color[2] * (1 - $ratio) + $end_color[2] * $ratio;
        
        $color = imagecolorallocate($temp, $r, $g, $b);
        imageline($temp, 0, $i, $width, $i, $color);
    }
    
    return $temp;
}

/**
 * 颜色代码转RGB
 */
function hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return [$r, $g, $b];
}

/**
 * 获取字体列表
 */
function getFontList() {
    global $config;
    $fonts = [];
    
    try {
        // 输出完整的字体目录路径和当前工作目录
        $currentDir = getcwd();
        $fontPath = realpath($config['font_path']);
        error_log("Current directory: " . $currentDir);
        error_log("Font directory path: " . $fontPath);
        error_log("Font directory config: " . $config['font_path']);
        
        // 确保字体目录存在
        if (!is_dir($config['font_path'])) {
            error_log("Font directory does not exist, creating...");
            mkdir($config['font_path'], 0777, true);
        }
        
        // 检查目录权限
        error_log("Directory exists: " . (is_dir($config['font_path']) ? 'yes' : 'no'));
        error_log("Directory readable: " . (is_readable($config['font_path']) ? 'yes' : 'no'));
        
        // 列出目录中的所有文件
        $files = scandir($config['font_path']);
        if ($files === false) {
            error_log("Failed to scan directory");
            throw new Exception("Failed to scan directory");
        }
        
        error_log("Files in directory: " . implode(", ", $files));
        
        // 过滤字体文件
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $fullPath = $config['font_path'] . $file;
            error_log("Processing file: " . $file);
            error_log("Full path: " . $fullPath);
            error_log("File exists: " . (file_exists($fullPath) ? 'yes' : 'no'));
            error_log("File readable: " . (is_readable($fullPath) ? 'yes' : 'no'));
            
            if (preg_match('/\.(ttf|ttc|otf)$/i', $file)) {
                $fonts[] = [
                    'file' => $file,
                    'name' => pathinfo($file, PATHINFO_FILENAME)
                ];
                error_log("Added font: " . $file);
            }
        }
        
        error_log("Total fonts found: " . count($fonts));
        error_log("Font list: " . json_encode($fonts));
        
        return $fonts;
        
    } catch (Exception $e) {
        error_log("Error in getFontList: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        return [];
    }
}
