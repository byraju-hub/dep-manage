<?php
declare(strict_types=1);

// 출석, 심방, 공지, 일정(연간 계획), 회의록, 사역 계획, 캘린더, 대시보드.

// ===== 출석 =====

route('GET', '/d/{dept}/attendance', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $date = in_date($_GET, 'date') ?? sunday_of(today_str());
    $grades = grade_levels_of($ctx['deptId']);
    $students = map_students(fetch_students($ctx['deptId']), $grades, rank($ctx['role']), false);
    $map = array_column(rows(
        'SELECT a.student_id, a.status FROM ' . t('attendance') . ' a JOIN ' . t('students') . ' s ON s.id = a.student_id WHERE s.department_id = ? AND a.attend_date = ?',
        [$ctx['deptId'], $date]
    ), 'status', 'student_id');
    foreach ($students as &$s) {
        // 학생의 상태(status: 재적/새가족/비활동)와 섞이지 않도록 출석 상태는 attendance 에 담는다.
        $s['attendance'] = $map[$s['id']] ?? 'present';
        $s['checked'] = isset($map[$s['id']]);
    }
    unset($s);
    $classes = array_map(fn($c) => ['id' => (int)$c['id'], 'deptId' => $ctx['deptId'], 'year' => (int)$c['year'], 'name' => $c['name'], 'tone' => $c['tone']],
        rows('SELECT * FROM ' . t('classes') . ' WHERE department_id = ? AND year = (SELECT MAX(year) FROM ' . t('classes') . ' WHERE department_id = ?) ORDER BY sort, id', [$ctx['deptId'], $ctx['deptId']]));
    return ['date' => $date, 'classes' => $classes, 'students' => $students];
});

route('PUT', '/d/{dept}/attendance', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $b = body();
    $date = in_date($b, 'date', true, '날짜');
    $entries = is_array($b['entries'] ?? null) ? $b['entries'] : [];
    if (count($entries) > 1000) json_fail('invalid', '한 번에 저장할 수 있는 인원이 너무 많습니다.', 422);
    $valid = array_flip(array_map('intval', array_column(rows('SELECT id FROM ' . t('students') . ' WHERE department_id = ?', [$ctx['deptId']]), 'id')));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO ' . t('attendance') . ' (student_id, attend_date, status, checked_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status = VALUES(status), checked_by = VALUES(checked_by)');
        $n = 0;
        foreach ($entries as $e) {
            if (!is_array($e) || count($e) < 2) continue;
            [$sid, $status] = [(int)$e[0], (string)$e[1]];
            if (!isset($valid[$sid]) || !in_array($status, ['present', 'online', 'absent'], true)) continue;
            $st->execute([$sid, $date, $status, $ctx['user']['id']]);
            $n++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    audit('attendance.save', 'attendance', null, "$date · {$n}명");
    return ['saved' => $n];
});

// ===== 심방 =====

function visit_json(array $v, array $targets, array $names = []): array
{
    return [
        'id' => (int)$v['id'], 'deptId' => (int)$v['department_id'], 'date' => $v['visit_date'], 'targets' => $targets,
        'method' => $v['method'], 'companions' => $v['companions'], 'title' => $v['title'], 'content' => (string)$v['content'],
        'followUp' => $v['follow_up'], 'followUpDue' => $v['follow_up_due'], 'followUpDone' => (bool)(int)$v['follow_up_done'],
        'visibility' => $v['visibility'], 'author' => $v['author_name'], 'targetNames' => $names,
    ];
}

/** 이 사용자가 볼 수 있는 심방 기록 (대상 이름 포함). $pendingOnly 이면 후속 조치가 남은 것만. */
function visits_for(array $ctx, bool $pendingOnly = false): array
{
    if (rank($ctx['role']) < 2) return [];
    $sql = 'SELECT * FROM ' . t('visits') . ' WHERE department_id = ?';
    if ($pendingOnly) $sql .= " AND follow_up <> '' AND follow_up_done = 0";
    $visible = array_values(array_filter(rows($sql . ' ORDER BY visit_date DESC, id DESC', [$ctx['deptId']]), fn($v) => visit_visible_to($v, $ctx['user'], $ctx['role'])));
    if (!$visible) return [];
    $ids = array_map(fn($v) => (int)$v['id'], $visible);
    $targets = [];
    foreach (rows('SELECT visit_id, target_type, target_id FROM ' . t('visit_targets') . ' WHERE visit_id IN (' . marks($ids) . ')', $ids) as $r) {
        $targets[(int)$r['visit_id']][] = ['type' => $r['target_type'], 'id' => (int)$r['target_id']];
    }
    $names = ['student' => [], 'teacher' => []];
    foreach (['student' => 'students', 'teacher' => 'teachers'] as $type => $table) {
        $want = [];
        foreach ($targets as $list) foreach ($list as $t) if ($t['type'] === $type) $want[] = $t['id'];
        if ($want) foreach (rows('SELECT id, name FROM ' . t($table) . ' WHERE id IN (' . marks($want) . ')', $want) as $r) $names[$type][(int)$r['id']] = $r['name'];
    }
    return array_map(fn($v) => visit_json($v, $targets[(int)$v['id']] ?? [], array_map(fn($t) => $names[$t['type']][$t['id']] ?? '(삭제됨)', $targets[(int)$v['id']] ?? [])), $visible);
}

route('GET', '/d/{dept}/visits', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    return visits_for($ctx, ($_GET['filter'] ?? '') === 'pending');
});

route('GET', '/d/{dept}/people', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $k = trim((string)($_GET['q'] ?? ''));
    if ($k === '') return [];
    $like = '%' . addcslashes($k, '%_\\') . '%';
    $out = [];
    foreach (rows('SELECT id, name FROM ' . t('students') . ' WHERE department_id = ? AND name LIKE ? ORDER BY name LIMIT 8', [$ctx['deptId'], $like]) as $r) $out[] = ['type' => 'student', 'id' => (int)$r['id'], 'name' => $r['name'], 'hint' => '학생'];
    foreach (rows('SELECT id, name FROM ' . t('teachers') . ' WHERE department_id = ? AND name LIKE ? ORDER BY name LIMIT 8', [$ctx['deptId'], $like]) as $r) $out[] = ['type' => 'teacher', 'id' => (int)$r['id'], 'name' => $r['name'], 'hint' => '교사'];
    return array_slice($out, 0, 8);
});

route('POST', '/d/{dept}/visits', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $b = body();
    $targets = [];
    foreach (is_array($b['targets'] ?? null) ? array_slice($b['targets'], 0, 20) : [] as $t) {
        $type = $t['type'] ?? '';
        $id = (int)($t['id'] ?? 0);
        $table = $type === 'student' ? 'students' : ($type === 'teacher' ? 'teachers' : null);
        if ($table && val('SELECT 1 FROM ' . t($table) . ' WHERE id = ? AND department_id = ?', [$id, $ctx['deptId']])) $targets[] = [$type, $id];
    }
    if (!$targets) json_fail('invalid', '대상을 한 명 이상 골라 주세요.', 422);
    $follow = in_str($b, 'followUp', 255);
    $due = $follow !== '' ? in_date($b, 'followUpDue') : null;
    $visibility = in_enum($b, 'visibility', ['private', 'pastors', 'staff'], 'pastors', '열람 범위');
    $method = in_enum($b, 'method', ['가정', '전화', '문자·카톡', '학교', '교회', '온라인', '카페'], '가정', '방법');
    $id = insert_id(
        'INSERT INTO ' . t('visits') . ' (department_id, visit_date, method, companions, title, content, follow_up, follow_up_due, visibility, author_user_id, author_name) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$ctx['deptId'], in_date($b, 'date', true, '날짜'), $method, in_str($b, 'companions', 200), in_str($b, 'title', 200, true, '제목'), in_str($b, 'content', 10000), $follow, $due, $visibility, $ctx['user']['id'], $ctx['user']['name']]
    );
    foreach ($targets as [$type, $tid]) q('INSERT IGNORE INTO ' . t('visit_targets') . ' (visit_id, target_type, target_id) VALUES (?,?,?)', [$id, $type, $tid]);
    audit('visit.create', 'visit', $id);
    return ['id' => $id];
});

function load_visit_for_change(int $id): array
{
    $v = row('SELECT * FROM ' . t('visits') . ' WHERE id = ?', [$id]);
    if (!$v) json_fail('not_found', '심방 기록을 찾을 수 없습니다.', 404);
    $ctx = require_dept((int)$v['department_id'], 'teacher');
    if (!visit_visible_to($v, $ctx['user'], $ctx['role'])) json_fail('not_found', '심방 기록을 찾을 수 없습니다.', 404);
    $mine = $v['author_user_id'] !== null && (int)$v['author_user_id'] === (int)$ctx['user']['id'];
    if (!$mine && rank($ctx['role']) < 3) json_fail('forbidden', '작성자나 부서 관리자만 바꿀 수 있습니다.', 403);
    return $v;
}

route('POST', '/visits/{id}/complete', function (array $p) {
    $v = load_visit_for_change((int)$p['id']);
    q('UPDATE ' . t('visits') . ' SET follow_up_done = 1 WHERE id = ?', [$v['id']]);
    audit('visit.complete', 'visit', (int)$v['id']);
    return null;
});

route('DELETE', '/visits/{id}', function (array $p) {
    $v = load_visit_for_change((int)$p['id']);
    q('DELETE FROM ' . t('visits') . ' WHERE id = ?', [$v['id']]);
    audit('visit.delete', 'visit', (int)$v['id']);
    return null;
});

// ===== 공지 =====

function notice_json(array $n): array
{
    return ['id' => (int)$n['id'], 'deptId' => $n['department_id'] === null ? null : (int)$n['department_id'], 'title' => $n['title'], 'body' => (string)$n['body'], 'date' => $n['publish_date'], 'pinned' => (bool)(int)$n['pinned']];
}

function notices_for(int $deptId): array
{
    return array_map('notice_json', rows(
        'SELECT * FROM ' . t('notices') . ' WHERE department_id IS NULL OR department_id = ? ORDER BY pinned DESC, publish_date DESC, id DESC',
        [$deptId]
    ));
}

route('GET', '/d/{dept}/notices', fn(array $p) => notices_for(require_dept((int)$p['dept'])['deptId']));

route('POST', '/d/{dept}/notices', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $b = body();
    $id = insert_id(
        'INSERT INTO ' . t('notices') . ' (department_id, title, body, pinned, publish_date, author_user_id) VALUES (?,?,?,?,?,?)',
        [!empty($b['churchWide']) ? null : $ctx['deptId'], in_str($b, 'title', 200, true, '제목'), in_str($b, 'body', 10000), bool_int($b['pinned'] ?? 0), today_str(), $ctx['user']['id']]
    );
    audit('notice.create', 'notice', $id);
    return ['id' => $id];
});

function load_notice_for_change(int $id): array
{
    $n = row('SELECT * FROM ' . t('notices') . ' WHERE id = ?', [$id]);
    if (!$n) json_fail('not_found', '공지를 찾을 수 없습니다.', 404);
    $user = require_user();
    if ($n['department_id'] === null) {
        $mine = $n['author_user_id'] !== null && (int)$n['author_user_id'] === (int)$user['id'];
        if ((int)$user['is_owner'] !== 1 && !$mine) json_fail('forbidden', '교회 전체 공지는 교회 관리자나 작성자만 바꿀 수 있습니다.', 403);
    } else {
        require_dept((int)$n['department_id'], 'admin');
    }
    return $n;
}

route('PUT', '/notices/{id}', function (array $p) {
    $n = load_notice_for_change((int)$p['id']);
    $b = body();
    q('UPDATE ' . t('notices') . ' SET title = ?, body = ?, pinned = ? WHERE id = ?', [in_str($b, 'title', 200, true, '제목'), in_str($b, 'body', 10000), bool_int($b['pinned'] ?? 0), $n['id']]);
    return null;
});

route('DELETE', '/notices/{id}', function (array $p) {
    $n = load_notice_for_change((int)$p['id']);
    q('DELETE FROM ' . t('notices') . ' WHERE id = ?', [$n['id']]);
    audit('notice.delete', 'notice', (int)$n['id']);
    return null;
});

// ===== 일정 (연간 계획) =====

const EVENT_KINDS = ['ministry', 'birthday', 'visit', 'due', 'meeting'];

function event_json(array $e): array
{
    return ['id' => (int)$e['id'], 'deptId' => (int)$e['department_id'], 'date' => $e['event_date'], 'endDate' => $e['end_date'], 'kind' => $e['kind'], 'title' => $e['title'], 'prayer' => $e['prayer'], 'notes' => $e['notes']];
}

route('GET', '/d/{dept}/events', function (array $p) {
    $ctx = require_dept((int)$p['dept']);
    $year = in_int($_GET, 'year', (int)date('Y'));
    return array_map('event_json', rows(
        'SELECT * FROM ' . t('events') . ' WHERE department_id = ? AND YEAR(event_date) = ? ORDER BY event_date, id',
        [$ctx['deptId'], $year]
    ));
});

function event_values(array $b): array
{
    $end = in_date($b, 'endDate');
    $date = in_date($b, 'date', true, '날짜');
    if ($end !== null && $end < $date) json_fail('invalid', '끝나는 날짜가 시작 날짜보다 빠릅니다.', 422);
    return [$date, $end, in_enum($b, 'kind', EVENT_KINDS, 'ministry', '종류'), in_str($b, 'title', 200, true, '제목'), in_str($b, 'prayer', 100), in_str($b, 'notes', 255)];
}

route('POST', '/d/{dept}/events', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $id = insert_id('INSERT INTO ' . t('events') . ' (department_id, event_date, end_date, kind, title, prayer, notes) VALUES (?,?,?,?,?,?,?)', [$ctx['deptId'], ...event_values(body())]);
    audit('event.create', 'event', $id);
    return ['id' => $id];
});

/** 엑셀에서 붙여넣은 여러 줄을 한꺼번에 넣는다. */
route('POST', '/d/{dept}/events/bulk', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $list = is_array(body()['events'] ?? null) ? array_slice(body()['events'], 0, 500) : [];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO ' . t('events') . ' (department_id, event_date, end_date, kind, title, prayer, notes) VALUES (?,?,?,?,?,?,?)');
        foreach ($list as $e) if (is_array($e)) $st->execute([$ctx['deptId'], ...event_values($e)]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    audit('event.bulk', 'event', null, count($list) . '건');
    return ['saved' => count($list)];
});

function load_event(int $id): array
{
    $e = row('SELECT * FROM ' . t('events') . ' WHERE id = ?', [$id]);
    if (!$e) json_fail('not_found', '일정을 찾을 수 없습니다.', 404);
    require_dept((int)$e['department_id'], 'admin');
    return $e;
}

route('PUT', '/events/{id}', function (array $p) {
    $e = load_event((int)$p['id']);
    q('UPDATE ' . t('events') . ' SET event_date=?, end_date=?, kind=?, title=?, prayer=?, notes=? WHERE id=?', [...event_values(body()), $e['id']]);
    return null;
});

route('DELETE', '/events/{id}', function (array $p) {
    $e = load_event((int)$p['id']);
    q('DELETE FROM ' . t('events') . ' WHERE id = ?', [$e['id']]);
    audit('event.delete', 'event', (int)$e['id']);
    return null;
});

// ===== 회의록 =====

function meeting_json(array $m): array
{
    return ['id' => (int)$m['id'], 'deptId' => (int)$m['department_id'], 'date' => $m['meeting_date'], 'title' => $m['title'], 'content' => (string)$m['content'], 'attendees' => $m['attendees']];
}

route('GET', '/d/{dept}/meetings', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    return array_map('meeting_json', rows('SELECT * FROM ' . t('meetings') . ' WHERE department_id = ? ORDER BY meeting_date DESC, id DESC', [$ctx['deptId']]));
});

route('POST', '/d/{dept}/meetings', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $b = body();
    $id = insert_id(
        'INSERT INTO ' . t('meetings') . ' (department_id, meeting_date, title, content, attendees, author_user_id) VALUES (?,?,?,?,?,?)',
        [$ctx['deptId'], in_date($b, 'date', true, '날짜'), in_str($b, 'title', 200, true, '제목'), in_str($b, 'content', 50000), in_str($b, 'attendees', 300), $ctx['user']['id']]
    );
    audit('meeting.create', 'meeting', $id);
    return ['id' => $id];
});

function load_meeting_for_change(int $id): array
{
    $m = row('SELECT * FROM ' . t('meetings') . ' WHERE id = ?', [$id]);
    if (!$m) json_fail('not_found', '회의록을 찾을 수 없습니다.', 404);
    $ctx = require_dept((int)$m['department_id'], 'teacher');
    $mine = $m['author_user_id'] !== null && (int)$m['author_user_id'] === (int)$ctx['user']['id'];
    if (!$mine && rank($ctx['role']) < 3) json_fail('forbidden', '작성자나 부서 관리자만 바꿀 수 있습니다.', 403);
    return $m;
}

route('PUT', '/meetings/{id}', function (array $p) {
    $m = load_meeting_for_change((int)$p['id']);
    $b = body();
    q('UPDATE ' . t('meetings') . ' SET meeting_date=?, title=?, content=?, attendees=? WHERE id=?', [in_date($b, 'date', true, '날짜'), in_str($b, 'title', 200, true, '제목'), in_str($b, 'content', 50000), in_str($b, 'attendees', 300), $m['id']]);
    return null;
});

route('DELETE', '/meetings/{id}', function (array $p) {
    $m = load_meeting_for_change((int)$p['id']);
    q('DELETE FROM ' . t('meetings') . ' WHERE id = ?', [$m['id']]);
    audit('meeting.delete', 'meeting', (int)$m['id']);
    return null;
});

// ===== 사역 계획 =====

route('GET', '/d/{dept}/ministry-plans/{year}', function (array $p) {
    $ctx = require_dept((int)$p['dept']);
    $plan = row('SELECT * FROM ' . t('ministry_plans') . ' WHERE department_id = ? AND year = ?', [$ctx['deptId'], (int)$p['year']]);
    if (!$plan) return null;
    $items = rows('SELECT * FROM ' . t('ministry_plan_items') . ' WHERE plan_id = ? ORDER BY sort, id', [$plan['id']]);
    return [
        'year' => (int)$plan['year'], 'theme' => $plan['theme'], 'themeVerse' => $plan['theme_verse'], 'vision' => (string)$plan['vision'], 'goals' => (string)$plan['goals'], 'status' => $plan['status'],
        'items' => array_map(fn($i) => ['id' => (int)$i['id'], 'area' => $i['area'], 'title' => $i['title'], 'description' => (string)$i['description'], 'period' => $i['period'], 'owner' => $i['owner'], 'budget' => (int)$i['budget'], 'status' => $i['status']], $items),
    ];
});

route('PUT', '/d/{dept}/ministry-plans/{year}', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $year = (int)$p['year'];
    if ($year < 2000 || $year > 2100) json_fail('invalid', '연도가 올바르지 않습니다.', 422);
    $b = body();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q(
            'INSERT INTO ' . t('ministry_plans') . ' (department_id, year, theme, theme_verse, vision, goals, status) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE theme = VALUES(theme), theme_verse = VALUES(theme_verse), vision = VALUES(vision), goals = VALUES(goals), status = VALUES(status)',
            [$ctx['deptId'], $year, in_str($b, 'theme', 200), in_str($b, 'themeVerse', 200), in_str($b, 'vision', 5000), in_str($b, 'goals', 5000), in_enum($b, 'status', ['draft', 'final'], 'draft', '상태')]
        );
        $planId = (int)val('SELECT id FROM ' . t('ministry_plans') . ' WHERE department_id = ? AND year = ?', [$ctx['deptId'], $year]);
        q('DELETE FROM ' . t('ministry_plan_items') . ' WHERE plan_id = ?', [$planId]);
        foreach (array_slice(is_array($b['items'] ?? null) ? $b['items'] : [], 0, 200) as $i => $it) {
            if (!is_array($it) || in_str($it, 'title', 200) === '') continue;
            q(
                'INSERT INTO ' . t('ministry_plan_items') . ' (plan_id, area, title, description, period, owner, budget, status, sort) VALUES (?,?,?,?,?,?,?,?,?)',
                [$planId, in_str($it, 'area', 20), in_str($it, 'title', 200), in_str($it, 'description', 5000), in_str($it, 'period', 60), in_str($it, 'owner', 60), max(0, in_int($it, 'budget', 0) ?? 0), in_enum($it, 'status', ['todo', 'doing', 'done'], 'todo', '진행 상태'), $i]
            );
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    audit('plan.save', 'plan', $planId, (string)$year);
    return null;
});

// ===== 사역 캘린더 =====

route('GET', '/d/{dept}/calendar', function (array $p) {
    $ctx = require_dept((int)$p['dept']);
    $month = (string)($_GET['month'] ?? date('Y-m'));
    if (!month_valid($month)) json_fail('invalid', '달 형식이 올바르지 않습니다.', 422);
    [$y, $m] = array_map('intval', explode('-', $month));
    $dept = row('SELECT terms FROM ' . t('departments') . ' WHERE id = ?', [$ctx['deptId']]);
    $terms = clean_terms(json_decode((string)$dept['terms'], true) ?: []);
    $short = fn(string $name) => mb_substr($name, 1) ?: $name; // 성을 뺀 이름
    $items = [];

    foreach (rows('SELECT event_date, kind, title FROM ' . t('events') . " WHERE department_id = ? AND DATE_FORMAT(event_date, '%Y-%m') = ?", [$ctx['deptId'], $month]) as $e) {
        $items[] = ['date' => $e['event_date'], 'kind' => $e['kind'], 'label' => $e['title']];
    }
    if (rank($ctx['role']) >= 2) {
        $people = [
            ...rows('SELECT name, birth_date FROM ' . t('students') . " WHERE department_id = ? AND status <> 'inactive' AND birth_date IS NOT NULL AND MONTH(birth_date) = ?", [$ctx['deptId'], $m]),
            ...rows('SELECT name, birth_date FROM ' . t('teachers') . " WHERE department_id = ? AND status = 'active' AND birth_date IS NOT NULL AND MONTH(birth_date) = ?", [$ctx['deptId'], $m]),
        ];
        foreach ($people as $pe) {
            $day = (int)substr($pe['birth_date'], 8, 2);
            if (checkdate($m, $day, $y)) $items[] = ['date' => sprintf('%04d-%02d-%02d', $y, $m, $day), 'kind' => 'birthday', 'label' => $short($pe['name']) . ' 생일'];
        }
        foreach (visits_for($ctx) as $v) {
            $first = $short($v['targetNames'][0] ?? '');
            if (substr($v['date'], 0, 7) === $month) $items[] = ['date' => $v['date'], 'kind' => 'visit', 'label' => trim($first . ' ' . $terms['visit'])];
            if ($v['followUp'] !== '' && !$v['followUpDone'] && $v['followUpDue'] && substr($v['followUpDue'], 0, 7) === $month) {
                $items[] = ['date' => $v['followUpDue'], 'kind' => 'due', 'label' => trim($first . ' 후속')];
            }
        }
    }
    return $items;
});

// ===== 대시보드 =====

route('GET', '/d/{dept}/dashboard', function (array $p) {
    $ctx = require_dept((int)$p['dept']);
    $deptId = $ctx['deptId'];
    $grades = grade_levels_of($deptId);
    $sundays = recent_sundays(12); // 최근 순
    [$last, $prev] = $sundays;
    $staff = rank($ctx['role']) >= 2;

    $enrolled = rows('SELECT s.id, s.name, s.grade_level_id, c.name AS class_name FROM ' . t('students') . ' s LEFT JOIN ' . t('classes') . " c ON c.id = s.class_id WHERE s.department_id = ? AND s.status <> 'inactive'", [$deptId]);
    $attByDate = []; // 날짜 → 출석(출석+온라인) 인원
    foreach (rows(
        'SELECT a.attend_date, COUNT(*) AS n FROM ' . t('attendance') . ' a JOIN ' . t('students') . " s ON s.id = a.student_id WHERE s.department_id = ? AND s.status <> 'inactive' AND a.status <> 'absent' AND a.attend_date IN (" . marks($sundays) . ') GROUP BY a.attend_date',
        [$deptId, ...$sundays]
    ) as $r) $attByDate[$r['attend_date']] = (int)$r['n'];

    $teachers = (int)val('SELECT COUNT(*) FROM ' . t('teachers') . " WHERE department_id = ? AND status = 'active'", [$deptId]);
    $tAtt = fn(string $d) => (int)val('SELECT COUNT(*) FROM ' . t('teacher_attendance') . ' a JOIN ' . t('teachers') . " t ON t.id = a.teacher_id WHERE t.department_id = ? AND t.status = 'active' AND a.attend_date = ? AND a.status <> 'absent'", [$deptId, $d]);

    // 연속 결석 주수 (가장 최근 주일부터 거슬러 올라가며 센다. 기록이 없으면 결석으로 본다.)
    $absentees = [];
    if ($staff && $enrolled) {
        $ids = array_map(fn($s) => (int)$s['id'], $enrolled);
        $status = [];
        foreach (rows('SELECT student_id, attend_date, status FROM ' . t('attendance') . ' WHERE attend_date IN (' . marks($sundays) . ') AND student_id IN (' . marks($ids) . ')', [...$sundays, ...$ids]) as $r) {
            $status[(int)$r['student_id']][$r['attend_date']] = $r['status'];
        }
        foreach ($enrolled as $s) {
            $weeks = 0;
            foreach ($sundays as $d) {
                if (($status[(int)$s['id']][$d] ?? 'absent') === 'absent') $weeks++;
                else break;
            }
            if ($weeks >= 3) {
                $absentees[] = [
                    'id' => (int)$s['id'], 'name' => $s['name'], 'tone' => tone_of_grade($grades, (int)$s['grade_level_id']), 'weeks' => $weeks,
                    'gradeLabel' => label_of_grade($grades, (int)$s['grade_level_id']), 'className' => (string)$s['class_name'],
                ];
            }
        }
        usort($absentees, fn($a, $b) => $b['weeks'] <=> $a['weeks']);
    }

    $birthdays = 0;
    if ($staff) {
        $today = today_str();
        $people = [
            ...rows('SELECT birth_date FROM ' . t('students') . " WHERE department_id = ? AND status <> 'inactive' AND birth_date IS NOT NULL", [$deptId]),
            ...rows('SELECT birth_date FROM ' . t('teachers') . " WHERE department_id = ? AND status = 'active' AND birth_date IS NOT NULL", [$deptId]),
        ];
        foreach ($people as $pe) if (days_until_birthday($pe['birth_date'], $today) <= 6) $birthdays++;
    }

    $follow = $staff ? visits_for($ctx, true) : [];
    usort($follow, fn($a, $b) => strcmp((string)$a['followUpDue'], (string)$b['followUpDue']));

    $studentCount = $attByDate[$last] ?? 0;
    $teacherCount = $tAtt($last);
    return [
        'lastSunday' => $last,
        'studentCount' => $studentCount,
        'studentDiff' => $studentCount - ($attByDate[$prev] ?? 0),
        'enrolledTotal' => count($enrolled),
        'teacherCount' => $teacherCount,
        'teacherDiff' => $teacherCount - $tAtt($prev),
        'teacherTotal' => $teachers,
        'newCount' => (int)val('SELECT COUNT(*) FROM ' . t('students') . " WHERE department_id = ? AND status = 'new'", [$deptId]),
        'birthdayCount' => $birthdays,
        'birthdayRange' => date('n/j') . ' ~ ' . date('n/j', strtotime('+6 days')),
        'trend' => array_map(fn($d) => ['date' => $d, 'count' => $attByDate[$d] ?? 0], array_reverse($sundays)),
        'absentees' => $absentees,
        'followUps' => $follow,
        'notices' => notices_for($deptId),
    ];
});
