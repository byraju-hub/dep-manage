<?php
declare(strict_types=1);

// 새가족 정착, 기도 제목, 행사 참가. 모두 교사 이상만 볼 수 있다. (열람 역할에게는 개인 정보라서 보이지 않는다.)

const NEWCOMER_STEPS = ['welcome', 'attend', 'visit', 'register'];

/** 사람을 지울 때 그 사람의 기도 제목과 행사 참가 기록도 지운다. 아직 업데이트(마이그레이션 006)를 적용하지 않은 서버에서는 조용히 넘어간다. */
function purge_person_links(string $type, int $id): void
{
    try {
        q('DELETE FROM ' . t('prayer_requests') . ' WHERE target_type = ? AND target_id = ?', [$type, $id]);
        q('DELETE FROM ' . t('event_participants') . ' WHERE person_type = ? AND person_id = ?', [$type, $id]);
    } catch (Throwable) {
    }
}

/** 이 기능에 필요한 데이터베이스 변경(마이그레이션)이 적용되지 않았으면 안내한다. */
function need_schema(int $version): void
{
    if (current_schema_version(db(), dm_config()['prefix']) < $version) {
        json_fail('needs_update', "새 기능을 쓰려면 교회 관리자가 설정 → 데이터에서 '업데이트 적용'을 먼저 눌러 주세요.", 409);
    }
}

// ===== 새가족 정착 =====

route('GET', '/d/{dept}/newcomers', function (array $p) {
    need_schema(6);
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $deptId = $ctx['deptId'];
    $students = rows(
        'SELECT s.id, s.name, s.school, s.grade_level_id, s.created_at, c.name AS class_name FROM ' . t('students') . ' s LEFT JOIN ' . t('classes') . " c ON c.id = s.class_id WHERE s.department_id = ? AND s.status = 'new' ORDER BY s.created_at DESC, s.id DESC",
        [$deptId]
    );
    $ids = array_map(fn($s) => (int)$s['id'], $students);
    $steps = [];
    $attended = [];
    $visited = [];
    if ($ids) {
        foreach (rows('SELECT student_id, step, done_date FROM ' . t('newcomer_steps') . ' WHERE student_id IN (' . marks($ids) . ')', $ids) as $r) {
            $steps[(int)$r['student_id']][$r['step']] = $r['done_date'];
        }
        // 자동 확인: 최근 두 번의 주일에 모두 나왔는지, 심방 기록이 있는지
        $two = recent_sundays(2);
        foreach (rows('SELECT student_id, COUNT(*) AS n FROM ' . t('attendance') . ' WHERE student_id IN (' . marks($ids) . ") AND status <> 'absent' AND attend_date IN (" . marks($two) . ') GROUP BY student_id', [...$ids, ...$two]) as $r) {
            $attended[(int)$r['student_id']] = (int)$r['n'] >= 2;
        }
        foreach (rows('SELECT DISTINCT target_id FROM ' . t('visit_targets') . " WHERE target_type = 'student' AND target_id IN (" . marks($ids) . ')', $ids) as $r) {
            $visited[(int)$r['target_id']] = true;
        }
    }
    $grades = [];
    foreach (rows('SELECT id, label FROM ' . t('grade_levels') . ' WHERE department_id = ?', [$deptId]) as $g) $grades[(int)$g['id']] = $g['label'];
    return array_map(fn($s) => [
        'id' => (int)$s['id'],
        'name' => $s['name'],
        'school' => $s['school'],
        'gradeLabel' => $grades[(int)$s['grade_level_id']] ?? '',
        'className' => (string)($s['class_name'] ?? ''),
        'since' => substr((string)$s['created_at'], 0, 10),
        'steps' => (object)($steps[(int)$s['id']] ?? []),
        'autoAttend' => $attended[(int)$s['id']] ?? false,
        'autoVisit' => $visited[(int)$s['id']] ?? false,
    ], $students);
});

// 단계를 마쳤다고 표시하거나 취소한다. '정식 등록'을 마치면 재적(active)으로 바꾼다.
route('PUT', '/newcomers/{id}/steps/{step}', function (array $p) {
    need_schema(6);
    $s = row('SELECT id, department_id FROM ' . t('students') . ' WHERE id = ?', [(int)$p['id']]);
    if (!$s) json_fail('not_found', '학생을 찾을 수 없습니다.', 404);
    $ctx = require_dept((int)$s['department_id'], 'teacher');
    if (!in_array($p['step'], NEWCOMER_STEPS, true)) json_fail('invalid', '알 수 없는 단계입니다.', 422);
    $done = !empty(body()['done']);
    if ($done) {
        q(
            'INSERT INTO ' . t('newcomer_steps') . ' (student_id, step, done_date, done_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE done_date = VALUES(done_date), done_by = VALUES(done_by)',
            [$s['id'], $p['step'], today_str(), mb_substr((string)$ctx['user']['name'], 0, 50)]
        );
        if ($p['step'] === 'register') q('UPDATE ' . t('students') . " SET status = 'active' WHERE id = ? AND status = 'new'", [$s['id']]);
    } else {
        q('DELETE FROM ' . t('newcomer_steps') . ' WHERE student_id = ? AND step = ?', [$s['id'], $p['step']]);
    }
    audit('newcomer.step', 'student', (int)$s['id'], $p['step'] . ($done ? ' 완료' : ' 취소'));
    return null;
});

// ===== 기도 제목 =====

function prayer_target_names(int $deptId): array
{
    $out = ['student' => [], 'teacher' => []];
    foreach (rows('SELECT id, name FROM ' . t('students') . ' WHERE department_id = ?', [$deptId]) as $r) $out['student'][(int)$r['id']] = $r['name'];
    foreach (rows('SELECT id, name FROM ' . t('teachers') . ' WHERE department_id = ?', [$deptId]) as $r) $out['teacher'][(int)$r['id']] = $r['name'];
    return $out;
}

function prayer_json(array $r, array $names): array
{
    return [
        'id' => (int)$r['id'], 'targetType' => $r['target_type'], 'targetId' => (int)$r['target_id'],
        'targetName' => $names[$r['target_type']][(int)$r['target_id']] ?? '(삭제됨)',
        'content' => $r['content'], 'status' => $r['status'], 'date' => $r['request_date'], 'answeredDate' => $r['answered_date'],
        'author' => $r['author_name'],
    ];
}

route('GET', '/d/{dept}/prayers', function (array $p) {
    need_schema(6);
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $status = in_enum($_GET, 'status', ['praying', 'answered', 'all'], 'praying', '상태');
    $sql = 'SELECT * FROM ' . t('prayer_requests') . ' WHERE department_id = ?';
    $args = [$ctx['deptId']];
    if ($status !== 'all') {
        $sql .= ' AND status = ?';
        $args[] = $status;
    }
    $names = prayer_target_names($ctx['deptId']);
    return array_map(fn($r) => prayer_json($r, $names), rows($sql . ' ORDER BY request_date DESC, id DESC LIMIT 500', $args));
});

route('POST', '/d/{dept}/prayers', function (array $p) {
    need_schema(6);
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $b = body();
    $type = in_enum($b, 'targetType', ['student', 'teacher'], '', '대상');
    if ($type === '') json_fail('invalid', '누구의 기도 제목인지 골라 주세요.', 422);
    $tid = in_int($b, 'targetId');
    $ok = $tid && val('SELECT 1 FROM ' . t($type === 'student' ? 'students' : 'teachers') . ' WHERE id = ? AND department_id = ?', [$tid, $ctx['deptId']]);
    if (!$ok) json_fail('invalid', '대상을 찾을 수 없습니다.', 422);
    $id = insert_id(
        'INSERT INTO ' . t('prayer_requests') . ' (department_id, target_type, target_id, content, request_date, author_user_id, author_name) VALUES (?,?,?,?,?,?,?)',
        [$ctx['deptId'], $type, $tid, in_str($b, 'content', 500, true, '기도 제목'), in_date($b, 'date') ?? today_str(), $ctx['user']['id'], mb_substr((string)$ctx['user']['name'], 0, 50)]
    );
    audit('prayer.create', 'prayer', $id);
    return ['id' => $id];
});

function load_prayer(int $id, bool $needAuthor): array
{
    $r = row('SELECT * FROM ' . t('prayer_requests') . ' WHERE id = ?', [$id]);
    if (!$r) json_fail('not_found', '기도 제목을 찾을 수 없습니다.', 404);
    $ctx = require_dept((int)$r['department_id'], 'teacher');
    if ($needAuthor) {
        $mine = $r['author_user_id'] !== null && (int)$r['author_user_id'] === (int)$ctx['user']['id'];
        if (!$mine && rank($ctx['role']) < 3) json_fail('forbidden', '작성자나 부서 관리자만 바꿀 수 있습니다.', 403);
    }
    return $r;
}

route('PUT', '/prayers/{id}', function (array $p) {
    need_schema(6);
    $r = load_prayer((int)$p['id'], true);
    q('UPDATE ' . t('prayer_requests') . ' SET content = ? WHERE id = ?', [in_str(body(), 'content', 500, true, '기도 제목'), $r['id']]);
    return null;
});

// 응답받았다고 표시하거나 되돌린다.
route('POST', '/prayers/{id}/answer', function (array $p) {
    need_schema(6);
    $r = load_prayer((int)$p['id'], false);
    $on = !empty(body()['answered']);
    q('UPDATE ' . t('prayer_requests') . ' SET status = ?, answered_date = ? WHERE id = ?', [$on ? 'answered' : 'praying', $on ? today_str() : null, $r['id']]);
    return null;
});

route('DELETE', '/prayers/{id}', function (array $p) {
    need_schema(6);
    $r = load_prayer((int)$p['id'], true);
    q('DELETE FROM ' . t('prayer_requests') . ' WHERE id = ?', [$r['id']]);
    audit('prayer.delete', 'prayer', (int)$r['id']);
    return null;
});

// ===== 행사 참가 =====

function load_event_for_people(int $id): array
{
    $e = row('SELECT * FROM ' . t('events') . ' WHERE id = ?', [$id]);
    if (!$e) json_fail('not_found', '일정을 찾을 수 없습니다.', 404);
    $ctx = require_dept((int)$e['department_id'], 'teacher');
    return [$e, $ctx];
}

route('GET', '/events/{id}/participants', function (array $p) {
    need_schema(6);
    [$e, $ctx] = load_event_for_people((int)$p['id']);
    $joined = [];
    foreach (rows('SELECT * FROM ' . t('event_participants') . ' WHERE event_id = ?', [$e['id']]) as $r) $joined[$r['person_type'] . ':' . $r['person_id']] = $r;
    $grades = [];
    foreach (rows('SELECT id, label FROM ' . t('grade_levels') . ' WHERE department_id = ?', [$ctx['deptId']]) as $g) $grades[(int)$g['id']] = $g['label'];
    $people = [];
    $add = function (string $type, array $r, string $detail) use (&$people, $joined) {
        $j = $joined[$type . ':' . $r['id']] ?? null;
        $people[] = [
            'type' => $type, 'id' => (int)$r['id'], 'name' => $r['name'], 'detail' => $detail,
            'applied' => $j !== null, 'paid' => $j ? (bool)(int)$j['paid'] : false,
            'amount' => $j && $j['amount'] !== null ? (int)$j['amount'] : null, 'note' => $j ? $j['note'] : '',
        ];
    };
    foreach (rows('SELECT id, name, grade_level_id FROM ' . t('students') . " WHERE department_id = ? AND status <> 'inactive' ORDER BY grade_level_id, name", [$ctx['deptId']]) as $r) {
        $add('student', $r, $grades[(int)$r['grade_level_id']] ?? '');
    }
    foreach (rows('SELECT id, name, position FROM ' . t('teachers') . " WHERE department_id = ? AND status = 'active' ORDER BY name", [$ctx['deptId']]) as $r) {
        $add('teacher', $r, (string)$r['position']);
    }
    return ['event' => ['id' => (int)$e['id'], 'title' => $e['title'], 'date' => $e['event_date'], 'endDate' => $e['end_date']], 'people' => $people];
});

// 한 사람의 신청·납부 상태를 저장한다. 신청을 해제하면 기록을 지운다.
route('PUT', '/events/{id}/participants/{type}/{pid}', function (array $p) {
    need_schema(6);
    [$e, $ctx] = load_event_for_people((int)$p['id']);
    if (!in_array($p['type'], ['student', 'teacher'], true)) json_fail('invalid', '대상이 올바르지 않습니다.', 422);
    $pid = (int)$p['pid'];
    $ok = val('SELECT 1 FROM ' . t($p['type'] === 'student' ? 'students' : 'teachers') . ' WHERE id = ? AND department_id = ?', [$pid, $ctx['deptId']]);
    if (!$ok) json_fail('not_found', '대상을 찾을 수 없습니다.', 404);
    $b = body();
    if (empty($b['applied'])) {
        q('DELETE FROM ' . t('event_participants') . ' WHERE event_id = ? AND person_type = ? AND person_id = ?', [$e['id'], $p['type'], $pid]);
        return null;
    }
    $amount = in_int($b, 'amount');
    if ($amount !== null && ($amount < 0 || $amount > 100000000)) json_fail('invalid', '금액이 올바르지 않습니다.', 422);
    q(
        'INSERT INTO ' . t('event_participants') . ' (event_id, person_type, person_id, paid, amount, note) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE paid = VALUES(paid), amount = VALUES(amount), note = VALUES(note)',
        [$e['id'], $p['type'], $pid, empty($b['paid']) ? 0 : 1, $amount, in_str($b, 'note', 100)]
    );
    return null;
});
