<?php
declare(strict_types=1);

// 세션, CSRF 방어, 역할(권한) 확인.
//
// 역할: viewer(열람) < teacher(교사) < admin(부서 관리자) < owner(교회 전체 관리자)
// owner 는 users.is_owner 로 표시하고, 나머지는 memberships 에 부서마다 따로 둔다.

const ROLE_RANK = ['viewer' => 1, 'teacher' => 2, 'admin' => 3, 'owner' => 4];

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // 같은 도메인의 다른 프로그램과 쿠키가 섞이지 않도록 이 사이트 폴더(예: /dm)에만 쿠키를 보낸다.
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('dm_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $base === '' ? '/' : $base,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function is_unsafe_method(): bool
{
    return !in_array(req_method(), ['GET', 'HEAD', 'OPTIONS'], true);
}

/** 다른 사이트에서 보낸 요청이면 막는다 (Origin 의 주소가 이 사이트와 다를 때). */
function check_origin(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return;
    $oh = strtolower((string)parse_url($origin, PHP_URL_HOST));
    $hh = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($oh !== $hh) json_fail('forbidden_origin', '허용되지 않는 요청입니다.', 403);
}

function verify_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $have = $_SESSION['csrf'] ?? '';
    if ($have === '' || !is_string($sent) || !hash_equals($have, $sent)) {
        json_fail('csrf', '페이지가 오래되었습니다. 새로고침한 뒤 다시 시도해 주세요.', 403);
    }
}

/** @return array<string, mixed>|null 로그인한 사용자 (승인된 계정만) */
function current_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    session_boot();
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return $u = null;
    $row = row('SELECT * FROM ' . t('users') . ' WHERE id = ?', [$id]);
    if (!$row || $row['status'] !== 'active') {
        unset($_SESSION['uid']);
        return $u = null;
    }
    return $u = $row;
}

/** 로그인을 요구한다. 변경 요청이면 CSRF 토큰도 확인한다. */
function require_user(bool $allowPasswordChange = false): array
{
    check_origin();
    $u = current_user();
    if (!$u) json_fail('unauthorized', '로그인이 필요합니다.', 401);
    if (is_unsafe_method()) verify_csrf();
    if ((int)$u['must_change_password'] === 1 && !$allowPasswordChange) {
        json_fail('password_change_required', '비밀번호를 먼저 바꿔 주세요.', 403);
    }
    return $u;
}

/** @return array<int, array{role: string, teacher_id: ?int}> 부서 id → 역할 */
function memberships(int $userId): array
{
    $out = [];
    foreach (rows('SELECT department_id, role, teacher_id FROM ' . t('memberships') . ' WHERE user_id = ?', [$userId]) as $r) {
        $out[(int)$r['department_id']] = ['role' => $r['role'], 'teacher_id' => $r['teacher_id'] === null ? null : (int)$r['teacher_id']];
    }
    return $out;
}

function rank(?string $role): int
{
    return $role === null ? 0 : (ROLE_RANK[$role] ?? 0);
}

/** 그 부서에서의 역할. 접근 권한이 없으면 null. */
function dept_role(array $user, int $deptId): ?string
{
    if ((int)$user['is_owner'] === 1) return 'owner';
    return memberships((int)$user['id'])[$deptId]['role'] ?? null;
}

/**
 * 부서 화면의 권한 확인. 부서가 없거나 접근 권한이 없으면 404 (부서의 존재를 알려주지 않는다).
 * @return array{user: array, role: string, deptId: int}
 */
function require_dept(int $deptId, string $min = 'viewer'): array
{
    $user = require_user();
    $role = dept_role($user, $deptId);
    $exists = $role !== null && val('SELECT 1 FROM ' . t('departments') . ' WHERE id = ? AND is_active = 1', [$deptId]);
    if (!$exists) json_fail('not_found', '부서를 찾을 수 없습니다.', 404);
    if (rank($role) < rank($min)) json_fail('forbidden', '이 작업을 할 권한이 없습니다.', 403);
    return ['user' => $user, 'role' => $role, 'deptId' => $deptId];
}

function require_owner(): array
{
    $u = require_user();
    if ((int)$u['is_owner'] !== 1) json_fail('forbidden', '교회 관리자만 할 수 있습니다.', 403);
    return $u;
}

function audit(string $action, ?string $type = null, ?int $id = null, ?string $detail = null): void
{
    try {
        $uid = $_SESSION['uid'] ?? null;
        q(
            'INSERT INTO ' . t('audit_log') . ' (user_id, action, target_type, target_id, detail, ip) VALUES (?,?,?,?,?,?)',
            [$uid, $action, $type, $id, $detail === null ? null : mb_substr($detail, 0, 500), client_ip()]
        );
    } catch (Throwable) {
        // 이력 기록이 실패해도 본 작업은 막지 않는다.
    }
}
