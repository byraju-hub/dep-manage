<?php
declare(strict_types=1);

// 추가 항목(사용자 정의 항목) 관리. 정의는 부서 관리자가 만들고, 값은 학생·교사를 저장할 때 함께 들어간다.

/** 선택 목록의 항목들을 정리한다. (공백 제거, 중복 제거, 최대 20개) */
function clean_options(mixed $in): array
{
    if (!is_array($in)) return [];
    $out = [];
    foreach ($in as $o) {
        $s = trim((string)$o);
        if ($s === '' || mb_strlen($s) > 30 || in_array($s, $out, true)) continue;
        $out[] = $s;
        if (count($out) >= 20) break;
    }
    return $out;
}

route('GET', '/d/{dept}/custom-fields', function (array $p) {
    $ctx = require_dept((int)$p['dept']);
    $out = [];
    foreach (['student', 'teacher'] as $entity) {
        foreach (custom_defs($ctx['deptId'], $entity) as $d) $out[] = custom_def_json($d);
    }
    return $out;
});

route('POST', '/d/{dept}/custom-fields', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $b = body();
    $entity = in_enum($b, 'entity', ['student', 'teacher'], 'student', '대상');
    $type = in_enum($b, 'type', CUSTOM_TYPES, 'text', '종류');
    $label = in_str($b, 'label', 40, true, '항목 이름');
    $options = $type === 'select' ? clean_options($b['options'] ?? []) : [];
    if ($type === 'select' && count($options) < 2) json_fail('invalid', '선택 항목은 고를 수 있는 값을 두 개 이상 적어 주세요.', 422);
    $defs = custom_defs($ctx['deptId'], $entity);
    if (count($defs) >= CUSTOM_MAX_PER_ENTITY) json_fail('too_many', '추가 항목은 ' . CUSTOM_MAX_PER_ENTITY . '개까지 만들 수 있어요.', 409);
    foreach ($defs as $d) if ($d['label'] === $label) json_fail('exists', '같은 이름의 항목이 이미 있어요.', 409);
    $id = insert_id(
        'INSERT INTO ' . t('custom_fields') . ' (department_id, entity, label, field_type, options, sort, show_in_list) VALUES (?,?,?,?,?,?,?)',
        [$ctx['deptId'], $entity, $label, $type, $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null, count($defs), bool_int($b['showInList'] ?? 0)]
    );
    audit('custom_field.create', 'custom_field', $id, $label);
    return ['id' => $id];
});

function load_custom_field(int $id): array
{
    $f = row('SELECT * FROM ' . t('custom_fields') . ' WHERE id = ?', [$id]);
    if (!$f) json_fail('not_found', '항목을 찾을 수 없습니다.', 404);
    require_dept((int)$f['department_id'], 'admin');
    return $f;
}

// 종류는 한 번 정하면 바꾸지 않는다. (이미 입력된 값의 뜻이 달라지지 않게)
route('PUT', '/custom-fields/{id}', function (array $p) {
    $f = load_custom_field((int)$p['id']);
    $b = body();
    $label = in_str($b, 'label', 40, true, '항목 이름');
    $options = $f['field_type'] === 'select' ? clean_options($b['options'] ?? []) : [];
    if ($f['field_type'] === 'select' && count($options) < 2) json_fail('invalid', '선택 항목은 고를 수 있는 값을 두 개 이상 적어 주세요.', 422);
    q(
        'UPDATE ' . t('custom_fields') . ' SET label = ?, options = ?, show_in_list = ? WHERE id = ?',
        [$label, $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null, bool_int($b['showInList'] ?? 0), $f['id']]
    );
    audit('custom_field.update', 'custom_field', (int)$f['id']);
    return null;
});

// 항목을 지우면 정의만 사라진다. 이미 입력된 값은 화면에서 보이지 않게 되고, 다음에 저장할 때 정리된다.
route('DELETE', '/custom-fields/{id}', function (array $p) {
    $f = load_custom_field((int)$p['id']);
    q('DELETE FROM ' . t('custom_fields') . ' WHERE id = ?', [$f['id']]);
    audit('custom_field.delete', 'custom_field', (int)$f['id'], (string)$f['label']);
    return null;
});
