<?php
declare(strict_types=1);

// 시스템 상태, 교회 설정, 샘플 데이터.

const DEFAULT_FONTS = ['title' => 'noto-sans', 'menu' => 'noto-sans', 'body' => 'noto-sans'];

function church_json(): array
{
    $fonts = setting('fonts', DEFAULT_FONTS);
    $out = DEFAULT_FONTS;
    foreach (DEFAULT_FONTS as $role => $_) {
        if (is_array($fonts) && isset($fonts[$role]) && in_array($fonts[$role], FONT_IDS, true)) $out[$role] = $fonts[$role];
    }
    return ['name' => (string)setting('church_name', '교회'), 'fonts' => $out];
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
