<?php
// 配置目标域名（请修改为实际域名）
$targetDomain = 'https://th.duanju55.top';

// 读取路由配置
$routes = @file('api.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($routes === false) {
    http_response_code(500);
    die('I/O error.');
}

// 获取请求信息
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];
$fullUrl = $targetDomain . $_SERVER['REQUEST_URI'];

// 检查是否是跨域请求
if ($requestMethod == '') {
    http_response_code(200);
    die();
}

// 路由匹配检查
$matched = false;
foreach ($routes as $route) {
    // 转换路由模式到正则表达式
    $pattern = '#^' . preg_replace('/\{(\w+)\}/', '(?<$1>[^/]+)', $route) . '$#';
    if (preg_match($pattern, $requestUri)) {
        $matched = true;
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    die('Not Found');
}

// 初始化cURL
$ch = curl_init($fullUrl);

// 设置基本选项
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_CUSTOMREQUEST => $requestMethod,
    CURLOPT_FOLLOWLOCATION => false,
]);

// 设置请求数据
if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'])) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

// 设置请求头
$headers = [];
foreach (getallheaders() as $name => $value) {
    if (strtolower($name) === 'host') continue;
    $headers[] = "$name: $value";
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// 执行请求
$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    die('Gateway Error: ' . curl_error($ch));
}

// 解析响应
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);

// 关闭连接
curl_close($ch);

// 发送响应头
header_remove();
foreach (explode("\r\n", $responseHeaders) as $header) {
    if (!empty($header) && stripos($header, 'Transfer-Encoding') === false) {
        header($header);
    }
}

// 发送状态码和响应体
http_response_code($statusCode);
echo $responseBody;