<?php
declare(strict_types=1);

// 엑셀로 올린 명단 가져오기, 교사 출석, 출석률 통계, 학년 승급.

// ===== 명단 가져오기 (엑셀을 화면에서 읽어 한 줄씩 보낸다) =====

function bulk_limit(array $items): array
{
    if (count($items) > 500) json_fail('too_many', '한 번에 500명까지만 올릴 수 있어요. 나눠서 올려 주세요.', 413);
    return $items;
}

route('POST', '/d/{dept}/students/bulk', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $items = bulk_limit(is_array(body()['items'] ?? null) ? body()['items'] : []);
    $deptId = $ctx['deptId'];

    // 이미 있는 사람(이름 + 생년월일이 같으면 같은 사람으로 본다)은 건너뛴다.
    $have = [];
    foreach (rows('SELECT name, birth_date FROM ' . t('students') . ' WHERE department_id = ?', [$deptId]) as $r) $have[$r['name'] . '|' . $r['birth_date']] = true;

    $created = 0;
    $errors = [];
    $skipped = [];
    $GLOBALS['dm_throw'] = true;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($items as $i => $it) {
            $row = (int)($it['row'] ?? ($i + 1));
            $name = is_array($it) ? trim((string)($it['name'] ?? '')) : '';
            try {
                if (!is_array($it)) throw new ApiFail('invalid', '읽을 수 없는 줄입니다.');
                $in = student_input($it, $deptId);
                $key = $in['name'] . '|' . ($in['birth'] ?? '');
                if ($in['birth'] !== null && isset($have[$key])) {
                    $skipped[] = ['row' => $row, 'name' => $in['name']];
                    continue;
                }
                $id = insert_id(
                    'INSERT INTO ' . t('students') . ' (department_id, name, gender, birth_date, phone, school, grade_level_id, class_id, status, baptism, mentor, address, notes, custom) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$deptId, $in['name'], $in['gender'], $in['birth'], $in['phone'], $in['school'], $in['gradeId'], $in['classId'], $in['status'], $in['baptism'], $in['mentor'], $in['address'], $in['notes'], custom_encode($in['custom'])]
                );
                save_guardians($id, $in['guardians']);
                $have[$key] = true;
                $created++;
            } catch (ApiFail $e) {
                $errors[] = ['row' => $row, 'name' => $name, 'message' => $e->getMessage()];
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $GLOBALS['dm_throw'] = false;
        throw $e;
    }
    $GLOBALS['dm_throw'] = false;
    audit('student.import', 'student', null, "{$created}명");
    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
});

route('POST', '/d/{dept}/teachers/bulk', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $items = bulk_limit(is_array(body()['items'] ?? null) ? body()['items'] : []);
    $deptId = $ctx['deptId'];

    $have = [];
    foreach (rows('SELECT name, birth_date FROM ' . t('teachers') . ' WHERE department_id = ?', [$deptId]) as $r) $have[$r['name'] . '|' . $r['birth_date']] = true;

    $created = 0;
    $errors = [];
    $skipped = [];
    $GLOBALS['dm_throw'] = true;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($items as $i => $it) {
            $row = (int)($it['row'] ?? ($i + 1));
            $name = is_array($it) ? trim((string)($it['name'] ?? '')) : '';
            try {
                if (!is_array($it)) throw new ApiFail('invalid', '읽을 수 없는 줄입니다.');
                $in = teacher_input($it, $deptId);
                $key = $in[0] . '|' . ($in[3] ?? '');
                if ($in[3] !== null && isset($have[$key])) {
                    $skipped[] = ['row' => $row, 'name' => $in[0]];
                    continue;
                }
                $id = insert_id(
                    'INSERT INTO ' . t('teachers') . ' (department_id, name, position, officer_role, birth_date, phone, status, notes, custom) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$deptId, ...$in]
                );
                save_teacher_classes($id, $deptId, is_array($it['classIds'] ?? null) ? $it['classIds'] : []);
                $have[$key] = true;
                $created++;
            } catch (ApiFail $e) {
                $errors[] = ['row' => $row, 'name' => $name, 'message' => $e->getMessage()];
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $GLOBALS['dm_throw'] = false;
        throw $e;
    }
    $GLOBALS['dm_throw'] = false;
    audit('teacher.import', 'teacher', null, "{$created}명");
    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
});

// ===== 교사 출석 =====

route('GET', '/d/{dept}/teacher-attendance', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $date = in_date($_GET, 'date') ?? sunday_of(today_str());
    $map = array_column(rows(
        'SELECT a.teacher_id, a.status FROM ' . t('teacher_attendance') . ' a JOIN ' . t('teachers') . ' t ON t.id = a.teacher_id WHERE t.department_id = ? AND a.attend_date = ?',
        [$ctx['deptId'], $date]
    ), 'status', 'teacher_id');
    $teachers = rows('SELECT * FROM ' . t('teachers') . " WHERE department_id = ? AND status = 'active' ORDER BY id", [$ctx['deptId']]);
    return [
        'date' => $date,
        'teachers' => array_map(fn($tc) => [
            'id' => (int)$tc['id'], 'name' => $tc['name'], 'position' => $tc['position'], 'officerRole' => $tc['officer_role'],
            'photo' => $tc['photo_path'],
            'status' => $map[(int)$tc['id']] ?? 'present', 'checked' => isset($map[(int)$tc['id']]),
        ], $teachers),
    ];
});

route('PUT', '/d/{dept}/teacher-attendance', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $b = body();
    $date = in_date($b, 'date', true, '날짜');
    $entries = is_array($b['entries'] ?? null) ? $b['entries'] : [];
    $valid = array_flip(array_map('intval', array_column(rows('SELECT id FROM ' . t('teachers') . ' WHERE department_id = ?', [$ctx['deptId']]), 'id')));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO ' . t('teacher_attendance') . ' (teacher_id, attend_date, status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status = VALUES(status)');
        $n = 0;
        foreach ($entries as $e) {
            if (!is_array($e) || count($e) < 2) continue;
            [$tid, $status] = [(int)$e[0], (string)$e[1]];
            if (!isset($valid[$tid]) || !in_array($status, ['present', 'online', 'absent'], true)) continue;
            $st->execute([$tid, $date, $status]);
            $n++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    audit('teacher_attendance.save', 'attendance', null, "$date · {$n}명");
    return ['saved' => $n];
});

// ===== 출석률 통계 =====

route('GET', '/d/{dept}/attendance/stats', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'teacher');
    $deptId = $ctx['deptId'];
    $teachers = ($_GET['who'] ?? '') === 'teachers';
    $weeks = max(1, min(104, (int)($_GET['weeks'] ?? 12)));
    $to = sunday_of(today_str());
    $from = add_days($to, -7 * ($weeks - 1));

    // 누군가 출석을 체크한 날만 "모임이 있었던 주"로 본다. (체크하지 않은 주를 결석으로 세지 않는다.)
    $attT = $teachers ? t('teacher_attendance') : t('attendance');
    $peopleT = $teachers ? t('teachers') : t('students');
    $idCol = $teachers ? 'teacher_id' : 'student_id';
    $activeWhere = $teachers ? "p.status = 'active'" : "p.status <> 'inactive'";

    $dates = array_column(rows(
        "SELECT DISTINCT a.attend_date AS d FROM $attT a JOIN $peopleT p ON p.id = a.$idCol WHERE p.department_id = ? AND a.attend_date BETWEEN ? AND ? ORDER BY a.attend_date",
        [$deptId, $from, $to]
    ), 'd');
    $checked = count($dates);

    $counts = []; // id → [present, online, absent]
    foreach (rows(
        "SELECT a.$idCol AS id, a.status, COUNT(*) AS n FROM $attT a JOIN $peopleT p ON p.id = a.$idCol WHERE p.department_id = ? AND a.attend_date BETWEEN ? AND ? GROUP BY a.$idCol, a.status",
        [$deptId, $from, $to]
    ) as $r) $counts[(int)$r['id']][$r['status']] = (int)$r['n'];

    $grades = grade_levels_of($deptId);
    $people = [];
    if ($teachers) {
        $list = rows('SELECT p.id, p.name, p.position FROM ' . t('teachers') . " p WHERE p.department_id = ? AND $activeWhere ORDER BY p.id", [$deptId]);
    } else {
        $list = rows('SELECT p.id, p.name, p.grade_level_id, p.class_id, c.name AS class_name FROM ' . t('students') . ' p LEFT JOIN ' . t('grade_levels') . ' gl ON gl.id = p.grade_level_id LEFT JOIN ' . t('classes') . " c ON c.id = p.class_id WHERE p.department_id = ? AND $activeWhere ORDER BY gl.sort, (c.sort IS NULL), c.sort, p.name", [$deptId]);
    }
    $sumPresent = $sumOnline = $sumAbsent = 0;
    $byClass = [];
    foreach ($list as $r) {
        $c = $counts[(int)$r['id']] ?? [];
        $pr = $c['present'] ?? 0;
        $on = $c['online'] ?? 0;
        // 체크한 주 중 기록이 없는 주는 결석으로 센다.
        $ab = max(0, $checked - $pr - $on);
        $rate = $checked > 0 ? round(($pr + $on) / $checked * 100) : null;
        $sumPresent += $pr;
        $sumOnline += $on;
        $sumAbsent += $ab;
        $people[] = $teachers
            ? ['id' => (int)$r['id'], 'name' => $r['name'], 'tone' => 'violet', 'gradeLabel' => $r['position'], 'className' => '', 'present' => $pr, 'online' => $on, 'absent' => $ab, 'rate' => $rate]
            : ['id' => (int)$r['id'], 'name' => $r['name'], 'tone' => tone_of_grade($grades, (int)$r['grade_level_id']), 'gradeLabel' => label_of_grade($grades, (int)$r['grade_level_id']), 'className' => (string)$r['class_name'], 'present' => $pr, 'online' => $on, 'absent' => $ab, 'rate' => $rate];
        if (!$teachers && $r['class_id'] !== null) {
            $k = (int)$r['class_id'];
            $byClass[$k]['name'] = $r['class_name'];
            $byClass[$k]['attended'] = ($byClass[$k]['attended'] ?? 0) + $pr + $on;
            $byClass[$k]['members'] = ($byClass[$k]['members'] ?? 0) + 1;
        }
    }
    $classTone = array_column(rows('SELECT id, tone FROM ' . t('classes') . ' WHERE department_id = ?', [$deptId]), 'tone', 'id');
    $classes = [];
    foreach ($byClass as $id => $c) {
        $classes[] = ['id' => $id, 'name' => $c['name'], 'tone' => $classTone[$id] ?? 'blue', 'members' => $c['members'], 'rate' => $checked > 0 ? round($c['attended'] / ($c['members'] * $checked) * 100) : null];
    }

    $byDate = array_map(fn($r) => ['date' => $r['d'], 'attended' => (int)$r['a'], 'recorded' => (int)$r['n']], rows(
        "SELECT a.attend_date AS d, SUM(a.status <> 'absent') AS a, COUNT(*) AS n FROM $attT a JOIN $peopleT p ON p.id = a.$idCol WHERE p.department_id = ? AND $activeWhere AND a.attend_date BETWEEN ? AND ? GROUP BY a.attend_date ORDER BY a.attend_date",
        [$deptId, $from, $to]
    ));

    $total = $sumPresent + $sumOnline + $sumAbsent;
    return [
        'who' => $teachers ? 'teachers' : 'students', 'weeks' => $weeks, 'from' => $from, 'to' => $to, 'checkedWeeks' => $checked,
        'overall' => ['rate' => $total > 0 ? round(($sumPresent + $sumOnline) / $total * 100) : null, 'present' => $sumPresent, 'online' => $sumOnline, 'absent' => $sumAbsent],
        'people' => $people, 'classes' => $classes, 'byDate' => $byDate,
    ];
});

// ===== 학년 승급 =====

/** 승급 대상과 결과를 계산한다. 학년 단계의 next_id 순서를 따라가고, 마지막 단계(졸업)에 있는 학생은 대상이 아니다. */
function promotion_plan(int $deptId): array
{
    $byId = [];
    foreach (grade_levels_of($deptId) as $g) $byId[(int)$g['id']] = $g;
    $order = array_keys($byId);
    $out = [];
    foreach (rows('SELECT id, name, grade_level_id, class_id, status FROM ' . t('students') . ' WHERE department_id = ?', [$deptId]) as $s) {
        $g = $byId[(int)$s['grade_level_id']] ?? null;
        if (!$g || (int)$g['is_graduate'] || $g['next_id'] === null) continue;
        $next = $byId[(int)$g['next_id']] ?? null;
        if (!$next) continue;
        $out[] = [
            'id' => (int)$s['id'], 'name' => $s['name'], 'fromId' => (int)$g['id'], 'fromLabel' => $g['label'],
            'toId' => (int)$next['id'], 'toLabel' => $next['label'], 'toTone' => $next['tone'] ?: 'slate',
            'graduate' => (bool)(int)$next['is_graduate'],
            'enroll' => str_starts_with((string)$g['label'], '예비'), // 예비 → 정식 학년은 새가족으로 등록
            'order' => array_search((int)$g['id'], $order, true),
        ];
    }
    usort($out, fn($a, $b) => [$a['order'], $a['name']] <=> [$b['order'], $b['name']]);
    return $out;
}

route('GET', '/d/{dept}/promotions', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $year = (int)date('Y');
    $log = row('SELECT applied_at, student_count FROM ' . t('promotion_log') . ' WHERE department_id = ? AND year = ?', [$ctx['deptId'], $year]);
    return [
        'year' => $year, 'month' => (int)date('n'),
        'applied' => $log ? ['at' => $log['applied_at'], 'count' => (int)$log['student_count']] : null,
        'changes' => array_map(fn($c) => array_diff_key($c, ['order' => 1]), promotion_plan($ctx['deptId'])),
    ];
});

route('POST', '/d/{dept}/promotions/apply', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $b = body();
    if (($b['confirm'] ?? '') !== 'promote') json_fail('invalid', '확인이 필요합니다.', 422);
    $keepClass = !empty($b['keepClass']);
    $year = (int)date('Y');
    $deptId = $ctx['deptId'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // 같은 해에 두 번 하지 못하게 먼저 기록한다. (이미 있으면 기본키 충돌로 실패한다.)
        $changes = promotion_plan($deptId);
        try {
            q('INSERT INTO ' . t('promotion_log') . ' (department_id, year, applied_by, student_count) VALUES (?,?,?,?)', [$deptId, $year, $ctx['user']['id'], count($changes)]);
        } catch (PDOException) {
            json_fail('already', "{$year}년 승급은 이미 반영되었어요. (같은 해에는 한 번만 할 수 있어요.)", 409);
        }
        foreach ($changes as $c) {
            if ($c['graduate']) {
                q('UPDATE ' . t('students') . " SET grade_level_id = ?, class_id = NULL, status = 'inactive' WHERE id = ?", [$c['toId'], $c['id']]);
            } elseif ($c['enroll']) {
                q('UPDATE ' . t('students') . " SET grade_level_id = ?, class_id = NULL, status = 'new' WHERE id = ?", [$c['toId'], $c['id']]);
            } elseif ($keepClass) {
                q('UPDATE ' . t('students') . ' SET grade_level_id = ? WHERE id = ?', [$c['toId'], $c['id']]);
            } else {
                q('UPDATE ' . t('students') . ' SET grade_level_id = ?, class_id = NULL WHERE id = ?', [$c['toId'], $c['id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    audit('promotion.apply', 'department', $deptId, "{$year}년 " . count($changes) . '명');
    return ['year' => $year, 'count' => count($changes)];
});

// 학생을 다른 부서로 옮긴다 (졸업 후 상위 부서로, 전도 이동 등). 출석 기록은 학생을 따라가고, 심방 기록은 옛 부서에 남는다.
route('POST', '/d/{dept}/transfer', function (array $p) {
    $src = require_dept((int)$p['dept'], 'admin');
    $b = body();
    $toDept = (int)($b['toDeptId'] ?? 0);
    $gradeId = (int)($b['gradeId'] ?? 0);
    $status = in_enum($b, 'status', ['new', 'active', 'inactive'], 'new', '상태');
    $ids = array_values(array_unique(array_map('intval', is_array($b['studentIds'] ?? null) ? $b['studentIds'] : [])));
    if (!$ids) json_fail('invalid', '옮길 학생을 선택해 주세요.', 422);
    if (count($ids) > 500) json_fail('invalid', '한 번에 500명까지 옮길 수 있어요.', 422);
    if ($toDept === $src['deptId']) json_fail('invalid', '같은 부서로는 옮길 수 없어요.', 422);
    require_dept($toDept, 'admin');
    if (!val('SELECT 1 FROM ' . t('grade_levels') . ' WHERE id = ? AND department_id = ?', [$gradeId, $toDept])) {
        json_fail('invalid', '옮겨 갈 부서의 학년을 골라 주세요.', 422);
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $found = (int)val('SELECT COUNT(*) FROM ' . t('students') . " WHERE department_id = ? AND id IN ($in)", [$src['deptId'], ...$ids]);
    if ($found !== count($ids)) json_fail('invalid', '선택한 학생 중 이 부서 학생이 아닌 사람이 있어요.', 422);
    q(
        'UPDATE ' . t('students') . " SET department_id = ?, grade_level_id = ?, class_id = NULL, status = ? WHERE department_id = ? AND id IN ($in)",
        [$toDept, $gradeId, $status, $src['deptId'], ...$ids]
    );
    audit('student.transfer', 'department', $src['deptId'], count($ids) . "명 → 부서 {$toDept}");
    return ['moved' => count($ids)];
});
