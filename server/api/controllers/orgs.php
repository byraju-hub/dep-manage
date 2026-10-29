<?php
declare(strict_types=1);

// 부서, 사용자(승인·역할), 초대 코드.

const GRADE_TONES = ['중1' => 'blue', '중2' => 'green', '중3' => 'orange', '고1' => 'pink', '고2' => 'violet', '고3' => 'teal', '졸업' => 'slate'];

// ===== 부서 =====

/** 사용자가 볼 수 있는 부서 id 목록 (owner 는 전부) */
function accessible_dept_ids(array $u): array
{
    if ((int)$u['is_owner'] === 1) {
        return array_map('intval', array_column(rows('SELECT id FROM ' . t('departments') . ' WHERE is_active = 1 ORDER BY sort, id'), 'id'));
    }
    return array_map('intval', array_column(rows(
        'SELECT d.id FROM ' . t('departments') . ' d JOIN ' . t('memberships') . ' m ON m.department_id = d.id WHERE m.user_id = ? AND d.is_active = 1 ORDER BY d.sort, d.id',
        [$u['id']]
    ), 'id'));
}

route('GET', '/departments', function () {
    $u = require_user();
    $ids = accessible_dept_ids($u);
    if (!$ids) return [];
    $out = [];
    foreach (rows('SELECT * FROM ' . t('departments') . ' WHERE id IN (' . marks($ids) . ') ORDER BY sort, id', $ids) as $d) {
        $out[] = dept_row_to_json($d, grade_levels_of((int)$d['id']));
    }
    return $out;
});

/** 학년 단계 이름 목록으로 grade_levels 를 만든다. 순서대로 next_id 를 이어 준다. */
function create_grade_levels(int $deptId, array $labels): void
{
    $cycle = 0;
    $prev = null;
    $ids = [];
    foreach (array_values($labels) as $i => $label) {
        $label = trim((string)$label);
        if ($label === '' || mb_strlen($label) > 30) continue;
        $isGrad = $label === '졸업';
        $tone = GRADE_TONES[$label] ?? ($isGrad ? 'slate' : (str_starts_with($label, '예비') ? null : TONES[$cycle++ % 8]));
        $id = insert_id(
            'INSERT INTO ' . t('grade_levels') . ' (department_id, label, tone, sort, is_graduate) VALUES (?,?,?,?,?)',
            [$deptId, $label, $tone, $i, $isGrad ? 1 : 0]
        );
        if ($prev !== null) q('UPDATE ' . t('grade_levels') . ' SET next_id = ? WHERE id = ?', [$id, $prev]);
        $prev = $id;
        $ids[] = $id;
    }
}

route('POST', '/departments', function () {
    require_owner();
    $b = body();
    $name = in_str($b, 'name', 50, true, '부서 이름');
    $terms = clean_terms(is_array($b['terms'] ?? null) ? $b['terms'] : []);
    $terms['department'] = $name;
    $grades = is_array($b['grades'] ?? null) ? $b['grades'] : [];
    $sort = (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM ' . t('departments'));
    $id = insert_id(
        'INSERT INTO ' . t('departments') . ' (name, pastor_name, head_name, terms, sort) VALUES (?,?,?,?,?)',
        [$name, in_str($b, 'pastorName', 50), in_str($b, 'headName', 50), json_encode($terms, JSON_UNESCAPED_UNICODE), $sort]
    );
    create_grade_levels($id, $grades);
    seed_custom_defaults($id);
    audit('department.create', 'department', $id, $name);
    return dept_row_to_json(row('SELECT * FROM ' . t('departments') . ' WHERE id = ?', [$id]), grade_levels_of($id));
});

route('PUT', '/d/{dept}', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $b = body();
    $name = in_str($b, 'name', 50, true, '부서 이름');
    $d = row('SELECT terms FROM ' . t('departments') . ' WHERE id = ?', [$ctx['deptId']]);
    $terms = clean_terms(json_decode((string)$d['terms'], true) ?: []);
    $terms['department'] = $name;
    q(
        'UPDATE ' . t('departments') . ' SET name = ?, pastor_name = ?, head_name = ?, terms = ? WHERE id = ?',
        [$name, in_str($b, 'pastorName', 50), in_str($b, 'headName', 50), json_encode($terms, JSON_UNESCAPED_UNICODE), $ctx['deptId']]
    );
    audit('department.update', 'department', $ctx['deptId']);
    return null;
});

route('PUT', '/d/{dept}/terms', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $terms = clean_terms(is_array(body()['terms'] ?? null) ? body()['terms'] : []);
    q('UPDATE ' . t('departments') . ' SET name = ?, terms = ? WHERE id = ?', [$terms['department'], json_encode($terms, JSON_UNESCAPED_UNICODE), $ctx['deptId']]);
    audit('department.terms', 'department', $ctx['deptId']);
    return null;
});

// ===== 학년 단계 =====

/** sort 순서대로 next_id 를 다시 이어 준다. (마지막 단계의 next_id 는 비운다.) */
function relink_grades(int $deptId): void
{
    $ids = array_map('intval', array_column(rows('SELECT id FROM ' . t('grade_levels') . ' WHERE department_id = ? ORDER BY sort, id', [$deptId]), 'id'));
    foreach ($ids as $i => $id) {
        q('UPDATE ' . t('grade_levels') . ' SET next_id = ?, sort = ? WHERE id = ?', [$ids[$i + 1] ?? null, $i, $id]);
    }
}

route('POST', '/d/{dept}/grade-levels', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $b = body();
    $label = in_str($b, 'label', 30, true, '이름');
    $tone = in_enum($b, 'tone', TONES, 'blue', '색');
    // 졸업 단계가 있으면 그 앞에 끼워 넣는다.
    $gradSort = val('SELECT MIN(sort) FROM ' . t('grade_levels') . ' WHERE department_id = ? AND is_graduate = 1', [$ctx['deptId']]);
    $sort = $gradSort !== null ? (int)$gradSort : (int)val('SELECT COALESCE(MAX(sort), -1) + 1 FROM ' . t('grade_levels') . ' WHERE department_id = ?', [$ctx['deptId']]);
    if ($gradSort !== null) q('UPDATE ' . t('grade_levels') . ' SET sort = sort + 1 WHERE department_id = ? AND sort >= ?', [$ctx['deptId'], $sort]);
    $id = insert_id('INSERT INTO ' . t('grade_levels') . ' (department_id, label, tone, sort) VALUES (?,?,?,?)', [$ctx['deptId'], $label, $tone, $sort]);
    relink_grades($ctx['deptId']);
    audit('grade.create', 'grade', $id);
    return ['id' => $id];
});

function load_grade(int $id): array
{
    $g = row('SELECT * FROM ' . t('grade_levels') . ' WHERE id = ?', [$id]);
    if (!$g) json_fail('not_found', '학년 단계를 찾을 수 없습니다.', 404);
    require_dept((int)$g['department_id'], 'admin');
    return $g;
}

route('PUT', '/grade-levels/{id}', function (array $p) {
    $g = load_grade((int)$p['id']);
    $b = body();
    q('UPDATE ' . t('grade_levels') . ' SET label = ?, tone = ? WHERE id = ?', [in_str($b, 'label', 30, true, '이름'), in_enum($b, 'tone', TONES, (string)($g['tone'] ?? 'blue'), '색'), $g['id']]);
    audit('grade.update', 'grade', (int)$g['id']);
    return null;
});

route('DELETE', '/grade-levels/{id}', function (array $p) {
    $g = load_grade((int)$p['id']);
    if (val('SELECT 1 FROM ' . t('students') . ' WHERE grade_level_id = ? LIMIT 1', [$g['id']])) {
        json_fail('in_use', '이 단계에 속한 학생이 있어서 지울 수 없어요. 학생을 먼저 다른 단계로 옮겨 주세요.', 409);
    }
    q('DELETE FROM ' . t('grade_levels') . ' WHERE id = ?', [$g['id']]);
    relink_grades((int)$g['department_id']);
    audit('grade.delete', 'grade', (int)$g['id']);
    return null;
});

// ===== 사용자 =====

/** 이 사용자(caller)가 관리할 수 있는 부서 id 목록. owner 는 전부 (null = 제한 없음). */
function managed_dept_ids(array $caller): ?array
{
    if ((int)$caller['is_owner'] === 1) return null;
    return array_map('intval', array_column(rows('SELECT department_id FROM ' . t('memberships') . " WHERE user_id = ? AND role = 'admin'", [$caller['id']]), 'department_id'));
}

function require_user_admin(): array
{
    $u = require_user();
    $managed = managed_dept_ids($u);
    if ($managed !== null && !$managed) json_fail('forbidden', '사용자를 관리할 권한이 없습니다.', 403);
    return $u;
}

/** 대상 사용자를 caller 가 관리할 수 있는지 (owner 이거나, 대상이 caller 가 관리하는 부서에 속해 있을 때) */
function can_manage_user(array $caller, array $target): bool
{
    if ((int)$target['is_owner'] === 1) return false; // owner 는 다른 사람이 바꿀 수 없다
    $managed = managed_dept_ids($caller);
    if ($managed === null) return true;
    $theirs = array_keys(memberships((int)$target['id']));
    // 초대 코드 없이 가입해 아직 어느 부서에도 속하지 않은 승인 대기자는 모든 부서 관리자가 볼 수 있다.
    if (!$theirs && $target['status'] === 'pending') return true;
    return (bool)array_intersect($managed, $theirs);
}

route('GET', '/users', function () {
    $caller = require_user_admin();
    $managed = managed_dept_ids($caller);
    $users = rows('SELECT id, login_id, name, is_owner, status, last_login_at, created_at FROM ' . t('users') . ' ORDER BY (status = \'pending\') DESC, id');
    $depts = array_column(rows('SELECT id, name FROM ' . t('departments')), 'name', 'id');
    $out = [];
    foreach ($users as $u) {
        $mem = memberships((int)$u['id']);
        if ($managed !== null && !array_intersect($managed, array_keys($mem)) && (int)$u['id'] !== (int)$caller['id'] && !(!$mem && $u['status'] === 'pending')) continue;
        $out[] = [
            'id' => (int)$u['id'], 'loginId' => $u['login_id'], 'name' => $u['name'], 'isOwner' => (int)$u['is_owner'] === 1,
            'status' => $u['status'], 'lastLoginAt' => $u['last_login_at'], 'createdAt' => $u['created_at'],
            'memberships' => array_map(fn($id, $m) => ['deptId' => $id, 'deptName' => $depts[$id] ?? '', 'role' => $m['role']], array_keys($mem), $mem),
        ];
    }
    return $out;
});

route('PATCH', '/users/{id}', function (array $p) {
    $caller = require_user_admin();
    $target = row('SELECT * FROM ' . t('users') . ' WHERE id = ?', [(int)$p['id']]);
    if (!$target || !can_manage_user($caller, $target)) json_fail('not_found', '사용자를 찾을 수 없습니다.', 404);
    if ((int)$target['id'] === (int)$caller['id']) json_fail('forbidden', '내 계정은 여기서 바꿀 수 없습니다.', 403);
    $b = body();
    $managed = managed_dept_ids($caller);

    if (isset($b['status'])) {
        if (!in_array($b['status'], ['active', 'disabled'], true)) json_fail('invalid', '상태 값이 올바르지 않습니다.', 422);
        q('UPDATE ' . t('users') . ' SET status = ? WHERE id = ?', [$b['status'], $target['id']]);
        audit('user.status', 'user', (int)$target['id'], $b['status']);
    }
    if (isset($b['name'])) {
        q('UPDATE ' . t('users') . ' SET name = ? WHERE id = ?', [in_str($b, 'name', 50, true, '이름'), $target['id']]);
    }
    if (isset($b['memberships']) && is_array($b['memberships'])) {
        foreach ($b['memberships'] as $m) {
            $deptId = (int)($m['deptId'] ?? 0);
            $role = $m['role'] ?? null;
            if ($managed !== null && !in_array($deptId, $managed, true)) continue; // 내가 관리하지 않는 부서는 건드리지 못한다
            if (!val('SELECT 1 FROM ' . t('departments') . ' WHERE id = ?', [$deptId])) continue;
            if ($role === null) {
                q('DELETE FROM ' . t('memberships') . ' WHERE user_id = ? AND department_id = ?', [$target['id'], $deptId]);
            } elseif (in_array($role, ['admin', 'teacher', 'viewer'], true)) {
                q('INSERT INTO ' . t('memberships') . ' (user_id, department_id, role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE role = VALUES(role)', [$target['id'], $deptId, $role]);
            }
        }
        audit('user.roles', 'user', (int)$target['id']);
    }
    return null;
});

route('POST', '/users/{id}/reset-password', function (array $p) {
    $caller = require_user_admin();
    $target = row('SELECT * FROM ' . t('users') . ' WHERE id = ?', [(int)$p['id']]);
    if (!$target || !can_manage_user($caller, $target)) json_fail('not_found', '사용자를 찾을 수 없습니다.', 404);
    $tmp = temp_password();
    q('UPDATE ' . t('users') . ' SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($tmp, PASSWORD_DEFAULT), $target['id']]);
    audit('user.reset', 'user', (int)$target['id']);
    return ['tempPassword' => $tmp]; // 한 번만 보여준다
});

// ===== 초대 코드 =====

route('GET', '/invites', function () {
    $caller = require_user_admin();
    $managed = managed_dept_ids($caller);
    $sql = 'SELECT i.id, i.code, i.department_id, i.role, i.expires_at, i.max_uses, i.used_count, i.created_at, d.name AS dept FROM ' . t('invites') . ' i LEFT JOIN ' . t('departments') . ' d ON d.id = i.department_id';
    $params = [];
    if ($managed !== null) {
        $sql .= ' WHERE i.department_id IN (' . marks($managed) . ')';
        $params = $managed;
    }
    return array_map(fn($r) => [
        'id' => (int)$r['id'], 'code' => $r['code'], 'deptId' => $r['department_id'] === null ? null : (int)$r['department_id'], 'deptName' => $r['dept'],
        'role' => $r['role'], 'expiresAt' => $r['expires_at'], 'maxUses' => (int)$r['max_uses'], 'usedCount' => (int)$r['used_count'],
    ], rows($sql . ' ORDER BY i.id DESC', $params));
});

route('POST', '/invites', function () {
    $caller = require_user_admin();
    $b = body();
    $deptId = in_int($b, 'deptId');
    $managed = managed_dept_ids($caller);
    if ($deptId === null || !val('SELECT 1 FROM ' . t('departments') . ' WHERE id = ?', [$deptId])) json_fail('invalid', '부서를 선택해 주세요.', 422);
    if ($managed !== null && !in_array($deptId, $managed, true)) json_fail('forbidden', '이 부서의 초대 코드를 만들 권한이 없습니다.', 403);
    $role = in_enum($b, 'role', ['admin', 'teacher', 'viewer'], 'teacher', '역할');
    if ($role === 'admin' && $managed !== null) json_fail('forbidden', '부서 관리자 초대는 교회 관리자만 만들 수 있습니다.', 403);
    $maxUses = max(1, min(100, in_int($b, 'maxUses', 1) ?? 1));
    $days = max(1, min(90, in_int($b, 'days', 7) ?? 7));
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 10; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    $id = insert_id(
        'INSERT INTO ' . t('invites') . ' (code, department_id, role, expires_at, max_uses, created_by) VALUES (?,?,?, (NOW() + INTERVAL ' . $days . ' DAY), ?, ?)',
        [$code, $deptId, $role, $maxUses, $caller['id']]
    );
    audit('invite.create', 'invite', $id);
    return ['id' => $id, 'code' => $code];
});

route('DELETE', '/invites/{id}', function (array $p) {
    $caller = require_user_admin();
    $inv = row('SELECT * FROM ' . t('invites') . ' WHERE id = ?', [(int)$p['id']]);
    $managed = managed_dept_ids($caller);
    if (!$inv || ($managed !== null && !in_array((int)$inv['department_id'], $managed, true))) json_fail('not_found', '초대 코드를 찾을 수 없습니다.', 404);
    q('DELETE FROM ' . t('invites') . ' WHERE id = ?', [$inv['id']]);
    audit('invite.delete', 'invite', (int)$inv['id']);
    return null;
});
