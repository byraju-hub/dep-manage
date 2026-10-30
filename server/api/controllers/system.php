<?php
declare(strict_types=1);

// 시스템 상태, 교회 설정, 샘플 데이터.

const DEFAULT_FONTS = ['title' => 'noto-sans', 'menu' => 'noto-sans', 'body' => 'noto-sans'];

const DEMO_LOGIN = 'demo';
const DEMO_PASSWORD = '1234';

function demo_account_exists(): bool
{
    try {
        return (bool)val('SELECT 1 FROM ' . t('users') . ' WHERE login_id = ? AND is_demo = 1', [DEMO_LOGIN]);
    } catch (Throwable $e) {
        return false; // 업데이트(열 추가)를 아직 적용하지 않은 경우
    }
}

function church_json(): array
{
    $fonts = setting('fonts', DEFAULT_FONTS);
    $out = DEFAULT_FONTS;
    foreach (DEFAULT_FONTS as $role => $_) {
        if (is_array($fonts) && isset($fonts[$role]) && in_array($fonts[$role], FONT_IDS, true)) $out[$role] = $fonts[$role];
    }
    $logo = setting('logo', null);
    return ['name' => (string)setting('church_name', '교회'), 'fonts' => $out, 'logo' => is_string($logo) ? $logo : null, 'openSignup' => (bool)setting('open_signup', false), 'demoAccount' => demo_account_exists()];
}

// 데이터베이스 구조가 코드보다 낡았으면 needsUpdate. (설정 → 데이터에서 교회 관리자가 업데이트를 적용한다.)
route('GET', '/system/status', fn() => [
    'installed' => true,
    'version' => DM_VERSION,
    'needsUpdate' => latest_schema_version(DM_ROOT . '/migrations') > current_schema_version(db(), dm_config()['prefix']),
]);

// 로그인 화면에서도 쓰므로 교회 이름과 글꼴은 로그인 없이 읽을 수 있다.
route('GET', '/church', fn() => church_json());

route('PUT', '/church', function () {
    require_owner();
    $b = body();
    if (array_key_exists('name', $b)) {
        set_setting('church_name', in_str($b, 'name', 50, true, '교회 이름'));
    }
    if (array_key_exists('openSignup', $b)) {
        set_setting('open_signup', (bool)$b['openSignup']);
    }
    if (isset($b['fonts']) && is_array($b['fonts'])) {
        $f = church_json()['fonts'];
        foreach (['title', 'menu', 'body'] as $role) {
            $v = $b['fonts'][$role] ?? null;
            if (is_string($v)) {
                if (!in_array($v, FONT_IDS, true)) json_fail('invalid', '알 수 없는 글꼴입니다.', 422);
                $f[$role] = $v;
            }
        }
        set_setting('fonts', $f);
    }
    audit('church.update');
    return church_json();
});

// ===== 교회 로고 =====

// 로그인 화면과 브라우저 탭 아이콘에도 쓰이므로 로그인 없이 받을 수 있다. (개인정보가 아니다.)
route('GET', '/church/logo', function () {
    $token = setting('logo', null);
    $file = is_string($token) ? logo_path_for($token, ($_GET['size'] ?? '') === 'icon') : null;
    if (!$file || !is_file($file)) json_fail('not_found', '로고가 없습니다.', 404);
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=86400'); // 로고를 바꾸면 주소(v=)가 바뀌므로 오래 캐시해도 된다
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
});

route('POST', '/church/logo', function () {
    require_owner();
    $old = setting('logo', null);
    $token = store_logo($_FILES['logo'] ?? []);
    set_setting('logo', $token);
    if (is_string($old)) delete_logo($old);
    audit('church.logo');
    return ['logo' => $token];
});

route('DELETE', '/church/logo', function () {
    require_owner();
    $old = setting('logo', null);
    set_setting('logo', null);
    if (is_string($old)) delete_logo($old);
    audit('church.logo.delete');
    return null;
});

// ===== 샘플 데이터 =====

route('GET', '/system/sample', function () {
    require_owner();
    return sample_counts(db(), dm_config()['prefix']);
});

route('POST', '/system/sample', function () {
    require_owner();
    $p = dm_config()['prefix'];
    if (array_sum(sample_counts(db(), $p)) > 0) json_fail('exists', '이미 샘플 데이터가 있습니다. 먼저 삭제해 주세요.', 409);
    $dept = val('SELECT id FROM ' . t('departments') . ' WHERE is_sample = 0 AND is_active = 1 ORDER BY sort, id LIMIT 1');
    if (!$dept) json_fail('no_department', '샘플 데이터를 넣을 부서가 없습니다.', 409);
    sample_seed_department(db(), $p, (int)$dept, true);
    $young = sample_create_young_department(db(), $p);
    sample_seed_department(db(), $p, $young);
    audit('sample.add');
    return sample_counts(db(), $p);
});

route('DELETE', '/system/sample', function () {
    require_owner();
    $deleted = sample_delete(db(), dm_config()['prefix']);
    audit('sample.delete', null, null, json_encode($deleted));
    return $deleted;
});

// ===== 체험 계정 (교회 관리자가 만들고 지운다) =====

route('POST', '/system/demo-account', function () {
    require_owner();
    $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
    $cur = row('SELECT id, is_demo FROM ' . t('users') . ' WHERE login_id = ?', [DEMO_LOGIN]);
    if ($cur && (int)$cur['is_demo'] !== 1) json_fail('taken', "'" . DEMO_LOGIN . "' 아이디를 이미 다른 사람이 쓰고 있어요.", 409);
    if ($cur) {
        q('UPDATE ' . t('users') . " SET password_hash = ?, status = 'active', is_owner = 1, must_change_password = 0 WHERE id = ?", [$hash, $cur['id']]);
    } else {
        q('INSERT INTO ' . t('users') . " (login_id, password_hash, name, is_owner, is_demo, status) VALUES (?, ?, '체험 계정', 1, 1, 'active')", [DEMO_LOGIN, $hash]);
    }
    audit('demo.create');
    return ['loginId' => DEMO_LOGIN];
});

route('DELETE', '/system/demo-account', function () {
    require_owner();
    q('DELETE FROM ' . t('users') . ' WHERE login_id = ? AND is_demo = 1', [DEMO_LOGIN]);
    audit('demo.delete');
    return null;
});
