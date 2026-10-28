<?php
declare(strict_types=1);

// 요청과 응답 도우미, 아주 작은 라우터.

function json_out(mixed $data = null, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/** 한 건씩 검사하며 오류를 모아야 하는 곳(가져오기)에서는 바로 응답하지 않고 이 예외를 던지게 한다. */
class ApiFail extends Exception
{
    public function __construct(public string $errCode, string $message, public int $httpStatus = 400)
    {
        parent::__construct($message);
    }
}

function json_fail(string $code, string $message, int $status = 400): never
{
    if (!empty($GLOBALS['dm_throw'])) throw new ApiFail($code, $message, $status);
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 요청 본문(JSON)을 배열로. */
function body(): array
{
    static $b = null;
    if ($b === null) {
        $raw = file_get_contents('php://input');
        $d = $raw === false || $raw === '' ? [] : json_decode($raw, true);
        $b = is_array($d) ? $d : [];
    }
    return $b;
}

function req_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// ===== 라우터 =====

/** @var array<int, array{string, string, callable}> */
$GLOBALS['dm_routes'] = [];

/** route('GET', '/d/{dept}/students/{id}', fn(array $p) => ...) — {이름} 은 숫자나 글자 한 조각. */
function route(string $method, string $pattern, callable $handler): void
{
    $re = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
    $GLOBALS['dm_routes'][] = [$method, '#^' . $re . '$#', $handler];
}

function dispatch(string $path): never
{
    $method = req_method();
    $pathExists = false;
    foreach ($GLOBALS['dm_routes'] as [$m, $re, $handler]) {
        if (preg_match($re, $path, $mt)) {
            $pathExists = true;
            if ($m !== $method) continue;
            $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
            $result = $handler($params);
            json_out($result);
        }
    }
    json_fail($pathExists ? 'method_not_allowed' : 'not_found', $pathExists ? '허용되지 않는 요청입니다.' : '찾을 수 없는 주소입니다.', $pathExists ? 405 : 404);
}

// ===== 입력 검사 =====

function in_str(array $a, string $key, int $max = 255, bool $required = false, string $label = ''): string
{
    $v = $a[$key] ?? '';
    $v = is_scalar($v) ? trim((string)$v) : '';
    if ($required && $v === '') json_fail('invalid', ($label ?: $key) . ' 항목을 입력해 주세요.', 422);
    if (mb_strlen($v) > $max) json_fail('invalid', ($label ?: $key) . ' 항목이 너무 깁니다.', 422);
    return $v;
}

function in_int(array $a, string $key, ?int $default = null): ?int
{
    if (!array_key_exists($key, $a) || $a[$key] === '' || $a[$key] === null) return $default;
    return is_numeric($a[$key]) ? (int)$a[$key] : $default;
}

/** YYYY-MM-DD 모양이면 돌려주고, 비었으면 null, 이상하면 오류. */
function in_date(array $a, string $key, bool $required = false, string $label = ''): ?string
{
    $v = in_str($a, $key, 10, $required, $label);
    if ($v === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) || !checkdate((int)substr($v, 5, 2), (int)substr($v, 8, 2), (int)substr($v, 0, 4))) {
        json_fail('invalid', ($label ?: $key) . ' 날짜 형식이 올바르지 않습니다.', 422);
    }
    return $v;
}

function in_enum(array $a, string $key, array $allowed, string $default, string $label = ''): string
{
    $v = in_str($a, $key, 40);
    if ($v === '') return $default;
    if (!in_array($v, $allowed, true)) json_fail('invalid', ($label ?: $key) . ' 값이 올바르지 않습니다.', 422);
    return $v;
}
