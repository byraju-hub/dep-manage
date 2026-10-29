<?php
declare(strict_types=1);

// 로그인, 로그아웃, 내 정보, 비밀번호 변경, 초대 코드로 가입.

const MIN_PASSWORD = 8;
const ROLE_LABELS = ['owner' => '교회 관리자', 'admin' => '부서 관리자', 'teacher' => '부서 교사', 'viewer' => '열람'];

function me_payload(array $u): array
{
    $mem = memberships((int)$u['id']);
    $best = (int)$u['is_owner'] === 1 ? 'owner' : null;
    $list = [];
    foreach ($mem as $deptId => $m) {
        $list[] = ['deptId' => $deptId, 'role' => $m['role']];
        if ($best !== 'owner' && rank($m['role']) > rank($best)) $best = $m['role'];
    }
    return [
        'user' => [
            'id' => (int)$u['id'],
            'name' => $u['name'],
            'loginId' => $u['login_id'],
            'isOwner' => (int)$u['is_owner'] === 1,
            'mustChangePassword' => (int)$u['must_change_password'] === 1,
            'roleLabel' => ROLE_LABELS[$best ?? 'viewer'],
        ],
        'memberships' => $list,
        'csrf' => csrf_token(),
    ];
}

/** 15분 안에 실패가 많으면 잠시 막는다. 아이디 기준 5번, 접속 주소 기준 20번. */
function login_throttled(string $loginId): bool
{
    $byId = (int)val('SELECT COUNT(*) FROM ' . t('login_attempts') . ' WHERE login_id = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)', [$loginId]);
    $byIp = (int)val('SELECT COUNT(*) FROM ' . t('login_attempts') . ' WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)', [client_ip()]);
    return $byId >= 5 || $byIp >= 20;
}

function check_new_password(string $pw): void
{
    if (mb_strlen($pw) < MIN_PASSWORD) json_fail('weak_password', '비밀번호는 ' . MIN_PASSWORD . '자 이상으로 만들어 주세요.', 422);
    if (mb_strlen($pw) > 200) json_fail('invalid', '비밀번호가 너무 깁니다.', 422);
}

route('POST', '/auth/login', function () {
    check_origin();
    session_boot();
    $b = body();
    $loginId = in_str($b, 'loginId', 50, true, '아이디');
    $pw = (string)($b['password'] ?? '');
    if ($pw === '') json_fail('invalid', '비밀번호를 입력해 주세요.', 422);

    if (login_throttled($loginId)) {
        json_fail('throttled', '로그인에 여러 번 실패했습니다. 15분 뒤에 다시 시도해 주세요.', 429);
    }
    $u = row('SELECT * FROM ' . t('users') . ' WHERE login_id = ?', [$loginId]);
    $ok = $u && password_verify($pw, $u['password_hash']);
    if (!$u) {
        // 없는 아이디도 같은 시간이 걸리게 해서, 응답 시간으로 계정 유무가 드러나지 않게 한다.
        static $dummy = null;
        $dummy ??= password_hash('dm-dummy-password', PASSWORD_DEFAULT);
        password_verify($pw, $dummy);
    }
    if (!$ok) {
        q('INSERT INTO ' . t('login_attempts') . ' (ip, login_id) VALUES (?, ?)', [client_ip(), $loginId]);
        json_fail('bad_credentials', '아이디 또는 비밀번호가 맞지 않습니다.', 401);
    }
    if ($u['status'] === 'pending') json_fail('pending', '관리자가 승인하면 로그인할 수 있어요. 조금만 기다려 주세요.', 403);
    if ($u['status'] !== 'active') json_fail('disabled', '사용할 수 없는 계정입니다. 관리자에게 문의해 주세요.', 403);

    session_regenerate_id(true); // 세션 고정 공격 방지
    $_SESSION['uid'] = (int)$u['id'];
    unset($_SESSION['csrf']);
    q('UPDATE ' . t('users') . ' SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
    q('DELETE FROM ' . t('login_attempts') . ' WHERE login_id = ?', [$loginId]);
    // 오래된 시도 기록 정리 (가끔)
    if (random_int(1, 20) === 1) q('DELETE FROM ' . t('login_attempts') . ' WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    audit('login');
    return me_payload($u);
});

route('GET', '/auth/me', function () {
    $u = require_user(true);
    return me_payload($u);
});

route('POST', '/auth/logout', function () {
    check_origin();
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $c['path'], 'secure' => $c['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
    return null;
});

route('POST', '/auth/password', function () {
    $u = require_user(true);
    $b = body();
    $cur = (string)($b['current'] ?? '');
    $new = (string)($b['newPassword'] ?? '');
    $hash = (string)val('SELECT password_hash FROM ' . t('users') . ' WHERE id = ?', [$u['id']]);
    if (!password_verify($cur, $hash)) json_fail('bad_credentials', '지금 쓰는 비밀번호가 맞지 않습니다.', 403);
    check_new_password($new);
    if ($new === $cur) json_fail('invalid', '새 비밀번호는 지금 것과 달라야 합니다.', 422);
    q('UPDATE ' . t('users') . ' SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
    audit('password.change');
    return null;
});

// 가입 화면에서 초대 코드가 맞는지 먼저 확인한다 (로그인 없이).
route('GET', '/invites/{code}/check', function (array $p) {
    $inv = row('SELECT i.role, i.expires_at, i.max_uses, i.used_count, d.name AS dept FROM ' . t('invites') . ' i LEFT JOIN ' . t('departments') . ' d ON d.id = i.department_id WHERE i.code = ?', [strtoupper($p['code'])]);
    $valid = $inv && ($inv['expires_at'] === null || strtotime($inv['expires_at']) > time()) && (int)$inv['used_count'] < (int)$inv['max_uses'];
    if (!$valid) json_fail('invalid_code', '초대 코드가 올바르지 않거나 기간이 지났습니다.', 404);
    return ['department' => $inv['dept'], 'role' => $inv['role'], 'roleLabel' => ROLE_LABELS[$inv['role']]];
});

route('POST', '/auth/join', function () {
    check_origin();
    $b = body();
    $code = strtoupper(trim((string)($b['code'] ?? '')));
    if ($code === '') {
        // 초대 코드 없는 가입: 교회 관리자가 열어 둔 경우에만. 어느 부서에도 속하지 않은 승인 대기 상태로 들어온다.
        if (!setting('open_signup', false)) json_fail('invalid_code', '초대 코드를 입력해 주세요.', 422);
        $ip = client_ip();
        if ((int)val('SELECT COUNT(*) FROM ' . t('login_attempts') . " WHERE ip = ? AND login_id = '#join' AND attempted_at > (NOW() - INTERVAL 1 HOUR)", [$ip]) >= 5) {
            json_fail('throttled', '가입 신청을 너무 많이 했어요. 한 시간 뒤에 다시 시도해 주세요.', 429);
        }
        q('INSERT INTO ' . t('login_attempts') . " (ip, login_id) VALUES (?, '#join')", [$ip]);
    }
    $loginId = in_str($b, 'loginId', 50, true, '아이디');
    $name = in_str($b, 'name', 50, true, '이름');
    $pw = (string)($b['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $loginId)) json_fail('invalid', '아이디는 영문, 숫자, - _ . 으로 3자 이상 만들어 주세요.', 422);
    check_new_password($pw);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $inv = $code === '' ? null : row('SELECT * FROM ' . t('invites') . ' WHERE code = ? FOR UPDATE', [$code]);
        if ($code !== '' && (!$inv || ($inv['expires_at'] !== null && strtotime($inv['expires_at']) <= time()) || (int)$inv['used_count'] >= (int)$inv['max_uses'])) {
            json_fail('invalid_code', '초대 코드가 올바르지 않거나 기간이 지났습니다.', 404);
        }
        if (val('SELECT 1 FROM ' . t('users') . ' WHERE login_id = ?', [$loginId])) {
            json_fail('taken', '이미 쓰고 있는 아이디입니다.', 409);
        }
        $uid = insert_id(
            'INSERT INTO ' . t('users') . " (login_id, password_hash, name, status) VALUES (?, ?, ?, 'pending')",
            [$loginId, password_hash($pw, PASSWORD_DEFAULT), $name]
        );
        if ($inv && $inv['department_id'] !== null) {
            q('INSERT INTO ' . t('memberships') . ' (user_id, department_id, role) VALUES (?, ?, ?)', [$uid, $inv['department_id'], $inv['role']]);
        }
        if ($inv) q('UPDATE ' . t('invites') . ' SET used_count = used_count + 1 WHERE id = ?', [$inv['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    audit('user.join', 'user', $uid);
    return ['status' => 'pending'];
});
