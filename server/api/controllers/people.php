<?php
declare(strict_types=1);

// 학생, 교사, 반.
//
// 열람(viewer) 역할에게는 연락처·주소·생일·보호자 같은 개인정보를 서버에서 빼고 내려준다. (화면에서 숨기는 것만으로는 안 된다.)

const BAPTISM = ['-', '유아', '입교', '학습', '세례'];

/** 01012345678 → 010-1234-5678. 숫자·하이픈·공백·점 이외가 섞여 있으면 그대로 둔다. */
function normalize_phone(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || preg_match('/[^0-9\-\s.]/u', $raw)) return $raw;
    $d = preg_replace('/\D/', '', $raw);
    $len = strlen($d);
    if ($len === 11) return substr($d, 0, 3) . '-' . substr($d, 3, 4) . '-' . substr($d, 7);
    if ($len === 10) {
        return str_starts_with($d, '02')
            ? substr($d, 0, 2) . '-' . substr($d, 2, 4) . '-' . substr($d, 6)
            : substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6);
    }
    return $raw;
}

/** 최근 n주 일요일 중 가장 오래된 것부터 */
function oldest_first_sundays(int $n): array
{
    return array_reverse(recent_sundays($n));
}

/**
 * @param array[] $students DB 행 (class_name 포함)
 * @return array[] 화면용 학생 목록
 */
function map_students(array $students, array $grades, int $rank, bool $withRecent = true): array
{
    if (!$students) return [];
    $ids = array_map(fn($s) => (int)$s['id'], $students);

    $guardians = [];
    if ($rank >= 2) {
        foreach (rows('SELECT student_id, relation, name, phone FROM ' . t('guardians') . ' WHERE student_id IN (' . marks($ids) . ') ORDER BY id', $ids) as $g) {
            $guardians[(int)$g['student_id']][] = ['relation' => $g['relation'], 'name' => $g['name'], 'phone' => $g['phone']];
        }
    }
    $recent = [];
    if ($withRecent) {
        $sundays = oldest_first_sundays(4);
        $att = rows(
            'SELECT student_id, attend_date, status FROM ' . t('attendance') . ' WHERE attend_date IN (' . marks($sundays) . ') AND student_id IN (' . marks($ids) . ')',
            [...$sundays, ...$ids]
        );
        $map = [];
        foreach ($att as $a) $map[(int)$a['student_id']][$a['attend_date']] = $a['status'] !== 'absent';
        foreach ($ids as $id) $recent[$id] = array_map(fn($d) => $map[$id][$d] ?? false, $sundays);
    }

    $full = $rank >= 2;
    return array_map(function ($s) use ($grades, $guardians, $recent, $full) {
        $id = (int)$s['id'];
        return [
            'id' => $id,
            'deptId' => (int)$s['department_id'],
            'name' => $s['name'],
            'gender' => $s['gender'] ?? '',
            'birthDate' => $full ? ($s['birth_date'] ?? '') : '',
            'phone' => $full ? $s['phone'] : '',
            'school' => $s['school'],
            'gradeId' => (int)$s['grade_level_id'],
            'classId' => $s['class_id'] === null ? null : (int)$s['class_id'],
            'status' => $s['status'],
            'baptism' => $s['baptism'],
            'mentor' => $s['mentor'],
            'address' => $full ? $s['address'] : '',
            'guardians' => $full ? ($guardians[$id] ?? []) : [],
            'notes' => $full ? (string)$s['notes'] : '',
            'custom' => $full ? custom_decode($s['custom'] ?? null) : (object)[],
            'tone' => tone_of_grade($grades, (int)$s['grade_level_id']),
            'gradeLabel' => label_of_grade($grades, (int)$s['grade_level_id']),
            'className' => (string)($s['class_name'] ?? ''),
            'photo' => $full ? $s['photo_path'] : null,
            'recent' => $recent[$id] ?? [],
        ];
    }, $students);
}

function fetch_students(int $deptId, array $opts = []): array
{
    $sql = 'SELECT s.*, c.name AS class_name FROM ' . t('students') . ' s'
        . ' LEFT JOIN ' . t('grade_levels') . ' gl ON gl.id = s.grade_level_id'
        . ' LEFT JOIN ' . t('classes') . ' c ON c.id = s.class_id WHERE s.department_id = ?';
    $params = [$deptId];
    $status = $opts['status'] ?? 'enrolled';
    if ($status === 'enrolled') $sql .= " AND s.status <> 'inactive'";
    elseif ($status === 'inactive') $sql .= " AND s.status = 'inactive'";
    if (!empty($opts['grade'])) { $sql .= ' AND s.grade_level_id = ?'; $params[] = (int)$opts['grade']; }
    if (!empty($opts['classId'])) { $sql .= ' AND s.class_id = ?'; $params[] = (int)$opts['classId']; }
    if (($opts['q'] ?? '') !== '') {
        $like = '%' . addcslashes((string)$opts['q'], '%_\\') . '%';
        $sql .= ' AND (s.name LIKE ? OR s.school LIKE ?)';
        array_push($params, $like, $like);
    }
    $sql .= ' ORDER BY gl.sort, (c.sort IS NULL), c.sort, s.name';
    return rows($sql, $params);
}

function visit_visible_to(array $v, array $user, string $role): bool
{
    if ($role === 'owner') return true;
    if (rank($role) < 2) return false; // 열람 역할은 심방 기록을 볼 수 없다
    if ($v['author_user_id'] !== null && (int)$v['author_user_id'] === (int)$user['id']) return true;
    return match ($v['visibility']) {
        'staff' => true,
        'pastors' => rank($role) >= 3,
        default => false,
    };
}

route('GET', '/d/{dept}/students', function (array $p) {
    $ctx = require_dept((int)$p['dept']);
    $grades = grade_levels_of($ctx['deptId']);
    $opts = [
        'q' => trim((string)($_GET['q'] ?? '')),
        'grade' => (int)($_GET['grade'] ?? 0),
        'classId' => (int)($_GET['class'] ?? 0),
        'status' => in_array($_GET['status'] ?? '', ['all', 'inactive'], true) ? $_GET['status'] : 'enrolled',
    ];
    // 이름·학교로 찾기는 개인정보가 가려진 열람 역할에게는 허용하지 않는다. (결과로 가려진 글자를 추측하지 못하게)
    if (rank($ctx['role']) < 2) $opts['q'] = '';
    return map_students(fetch_students($ctx['deptId'], $opts), $grades, rank($ctx['role']));
});

function load_student(int $id): array
{
    $s = row('SELECT s.*, c.name AS class_name FROM ' . t('students') . ' s LEFT JOIN ' . t('classes') . ' c ON c.id = s.class_id WHERE s.id = ?', [$id]);
    if (!$s) json_fail('not_found', '학생을 찾을 수 없습니다.', 404);
    return $s;
}

route('GET', '/students/{id}', function (array $p) {
    $s = load_student((int)$p['id']);
    $ctx = require_dept((int)$s['department_id']);
    $grades = grade_levels_of($ctx['deptId']);
    [$student] = map_students([$s], $grades, rank($ctx['role']), false);

    $history = [];
    foreach (recent_sundays(12) as $d) {
        $st = val('SELECT status FROM ' . t('attendance') . ' WHERE student_id = ? AND attend_date = ?', [$s['id'], $d]);
        $history[] = ['date' => $d, 'status' => $st ?? 'absent'];
    }
    $visits = [];
    foreach (rows(
        'SELECT v.* FROM ' . t('visits') . ' v JOIN ' . t('visit_targets') . " vt ON vt.visit_id = v.id AND vt.target_type = 'student' AND vt.target_id = ? ORDER BY v.visit_date DESC, v.id DESC",
        [$s['id']]
    ) as $v) {
        $roleThere = dept_role($ctx['user'], (int)$v['department_id']);
        if ($roleThere !== null && visit_visible_to($v, $ctx['user'], $roleThere)) $visits[] = visit_json($v, [['type' => 'student', 'id' => (int)$s['id']]]);
    }
    return [
        'student' => $student,
        'tone' => $student['tone'], 'gradeLabel' => $student['gradeLabel'], 'className' => $student['className'],
        'history' => $history, 'visits' => $visits,
    ];
});

/** 입력값을 검사해서 DB 에 넣을 값으로 바꾼다. */
function student_input(array $b, int $deptId): array
{
    $gradeId = in_int($b, 'gradeId');
    if (!$gradeId || !val('SELECT 1 FROM ' . t('grade_levels') . ' WHERE id = ? AND department_id = ?', [$gradeId, $deptId])) {
        json_fail('invalid', '학년을 선택해 주세요.', 422);
    }
    $classId = in_int($b, 'classId');
    if ($classId && !val('SELECT 1 FROM ' . t('classes') . ' WHERE id = ? AND department_id = ?', [$classId, $deptId])) {
        json_fail('invalid', '반이 올바르지 않습니다.', 422);
    }
    $gender = in_str($b, 'gender', 2);
    $guardians = [];
    foreach (array_slice(is_array($b['guardians'] ?? null) ? $b['guardians'] : [], 0, 4) as $g) {
        if (!is_array($g)) continue;
        $name = in_str($g, 'name', 50);
        if ($name === '') continue;
        $guardians[] = [in_str($g, 'relation', 20) ?: '보호자', $name, normalize_phone(in_str($g, 'phone', 20))];
    }
    return [
        'name' => in_str($b, 'name', 50, true, '이름'),
        'gender' => in_array($gender, ['남', '여'], true) ? $gender : null,
        'birth' => in_date($b, 'birthDate', false, '생년월일'),
        'phone' => normalize_phone(in_str($b, 'phone', 20)),
        'school' => in_str($b, 'school', 50),
        'gradeId' => $gradeId,
        'classId' => $classId ?: null,
        'status' => in_enum($b, 'status', ['active', 'new', 'inactive'], 'active', '상태'),
        'baptism' => in_enum($b, 'baptism', BAPTISM, '-', '세례'),
        'mentor' => in_str($b, 'mentor', 50),
        'address' => in_str($b, 'address', 255),
        'notes' => in_str($b, 'notes', 2000),
        'guardians' => $guardians,
        'custom' => clean_custom($b['custom'] ?? null, $deptId, 'student'),
    ];
}

function save_guardians(int $studentId, array $guardians): void
{
    q('DELETE FROM ' . t('guardians') . ' WHERE student_id = ?', [$studentId]);
    foreach ($guardians as [$rel, $name, $phone]) {
        q('INSERT INTO ' . t('guardians') . ' (student_id, relation, name, phone) VALUES (?,?,?,?)', [$studentId, $rel, $name, $phone]);
    }
}

route('POST', '/d/{dept}/students', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $in = student_input(body(), $ctx['deptId']);
    $id = insert_id(
        'INSERT INTO ' . t('students') . ' (department_id, name, gender, birth_date, phone, school, grade_level_id, class_id, status, baptism, mentor, address, notes, custom) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$ctx['deptId'], $in['name'], $in['gender'], $in['birth'], $in['phone'], $in['school'], $in['gradeId'], $in['classId'], $in['status'], $in['baptism'], $in['mentor'], $in['address'], $in['notes'], custom_encode($in['custom'])]
    );
    save_guardians($id, $in['guardians']);
    audit('student.create', 'student', $id);
    return ['id' => $id];
});

/** 교사는 자기가 담임인 반의 학생만 고칠 수 있다. */
function can_edit_student(array $ctx, array $student): bool
{
    if (rank($ctx['role']) >= 3) return true;
    if ($ctx['role'] !== 'teacher' || $student['class_id'] === null) return false;
    $tid = memberships((int)$ctx['user']['id'])[$ctx['deptId']]['teacher_id'] ?? null;
    return $tid !== null && (bool)val('SELECT 1 FROM ' . t('class_teachers') . ' WHERE teacher_id = ? AND class_id = ?', [$tid, $student['class_id']]);
}

route('PUT', '/students/{id}', function (array $p) {
    $s = load_student((int)$p['id']);
    $ctx = require_dept((int)$s['department_id'], 'teacher');
    if (!can_edit_student($ctx, $s)) json_fail('forbidden', '담당 반의 학생만 고칠 수 있습니다.', 403);
    $in = student_input(body(), $ctx['deptId']);
    if (rank($ctx['role']) < 3 && ($in['classId'] !== (int)$s['class_id'] && $in['classId'] !== null)) {
        json_fail('forbidden', '반을 옮기는 것은 부서 관리자만 할 수 있습니다.', 403);
    }
    q(
        'UPDATE ' . t('students') . ' SET name=?, gender=?, birth_date=?, phone=?, school=?, grade_level_id=?, class_id=?, status=?, baptism=?, mentor=?, address=?, notes=?, custom=? WHERE id=?',
        [$in['name'], $in['gender'], $in['birth'], $in['phone'], $in['school'], $in['gradeId'], $in['classId'], $in['status'], $in['baptism'], $in['mentor'], $in['address'], $in['notes'], custom_encode($in['custom']), $s['id']]
    );
    save_guardians((int)$s['id'], $in['guardians']);
    audit('student.update', 'student', (int)$s['id']); // 값은 남기지 않고 바뀌었다는 사실만 기록한다
    return null;
});

route('DELETE', '/students/{id}', function (array $p) {
    $s = load_student((int)$p['id']);
    require_dept((int)$s['department_id'], 'admin');
    q('DELETE FROM ' . t('students') . ' WHERE id = ?', [$s['id']]);
    delete_photo($s['photo_path'] ?? null);
    audit('student.delete', 'student', (int)$s['id']);
    return null;
});

// ===== 반 =====

route('GET', '/d/{dept}/classes', function (array $p) {
    $ctx = require_dept((int)$p['dept']);
    $grades = grade_levels_of($ctx['deptId']);
    $classes = rows('SELECT * FROM ' . t('classes') . ' WHERE department_id = ? ORDER BY year DESC, sort, id', [$ctx['deptId']]);
    if (isset($_GET['year'])) $classes = array_values(array_filter($classes, fn($c) => (int)$c['year'] === (int)$_GET['year']));
    elseif ($classes) { $y = $classes[0]['year']; $classes = array_values(array_filter($classes, fn($c) => $c['year'] === $y)); }

    $students = map_students(fetch_students($ctx['deptId']), $grades, rank($ctx['role']));
    $leaders = [];
    foreach (rows('SELECT ct.class_id, t.name FROM ' . t('class_teachers') . ' ct JOIN ' . t('teachers') . ' t ON t.id = ct.teacher_id WHERE t.department_id = ? ORDER BY t.id', [$ctx['deptId']]) as $r) {
        $leaders[(int)$r['class_id']][] = $r['name'];
    }
    return array_map(fn($c) => [
        'id' => (int)$c['id'], 'deptId' => $ctx['deptId'], 'year' => (int)$c['year'], 'name' => $c['name'], 'tone' => $c['tone'],
        'students' => array_values(array_filter($students, fn($s) => $s['classId'] === (int)$c['id'])),
        'leaders' => $leaders[(int)$c['id']] ?? [],
    ], $classes);
});

route('POST', '/d/{dept}/classes', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $b = body();
    $tone = in_enum($b, 'tone', TONES, 'blue', '색');
    $id = insert_id(
        'INSERT INTO ' . t('classes') . ' (department_id, year, name, tone, sort) VALUES (?,?,?,?,?)',
        [$ctx['deptId'], in_int($b, 'year', (int)date('Y')), in_str($b, 'name', 40, true, '반 이름'), $tone, (int)val('SELECT COALESCE(MAX(sort),0)+1 FROM ' . t('classes') . ' WHERE department_id = ?', [$ctx['deptId']])]
    );
    audit('class.create', 'class', $id);
    return ['id' => $id];
});

route('PUT', '/classes/{id}', function (array $p) {
    $c = row('SELECT * FROM ' . t('classes') . ' WHERE id = ?', [(int)$p['id']]);
    if (!$c) json_fail('not_found', '반을 찾을 수 없습니다.', 404);
    $ctx = require_dept((int)$c['department_id'], 'admin');
    $b = body();
    q('UPDATE ' . t('classes') . ' SET name = ?, tone = ? WHERE id = ?', [in_str($b, 'name', 40, true, '반 이름'), in_enum($b, 'tone', TONES, $c['tone'], '색'), $c['id']]);
    if (isset($b['teacherIds']) && is_array($b['teacherIds'])) {
        q('DELETE FROM ' . t('class_teachers') . ' WHERE class_id = ?', [$c['id']]);
        foreach ($b['teacherIds'] as $tid) {
            if (val('SELECT 1 FROM ' . t('teachers') . ' WHERE id = ? AND department_id = ?', [(int)$tid, $ctx['deptId']])) {
                q('INSERT IGNORE INTO ' . t('class_teachers') . ' (class_id, teacher_id) VALUES (?,?)', [$c['id'], (int)$tid]);
            }
        }
    }
    audit('class.update', 'class', (int)$c['id']);
    return null;
});

route('DELETE', '/classes/{id}', function (array $p) {
    $c = row('SELECT * FROM ' . t('classes') . ' WHERE id = ?', [(int)$p['id']]);
    if (!$c) json_fail('not_found', '반을 찾을 수 없습니다.', 404);
    require_dept((int)$c['department_id'], 'admin');
    q('UPDATE ' . t('students') . ' SET class_id = NULL WHERE class_id = ?', [$c['id']]);
    q('DELETE FROM ' . t('classes') . ' WHERE id = ?', [$c['id']]);
    audit('class.delete', 'class', (int)$c['id']);
    return null;
});

// ===== 교사 =====

function teacher_json(array $t, int $rank, array $classNames): array
{
    $full = $rank >= 2;
    return [
        'id' => (int)$t['id'], 'deptId' => (int)$t['department_id'], 'name' => $t['name'], 'position' => $t['position'],
        'officerRole' => $t['officer_role'], 'birthDate' => $full ? ($t['birth_date'] ?? '') : '', 'phone' => $full ? $t['phone'] : '',
        'status' => $t['status'], 'notes' => $full ? (string)$t['notes'] : '', 'photo' => $full ? $t['photo_path'] : null,
        'custom' => $full ? custom_decode($t['custom'] ?? null) : (object)[],
        'classIds' => array_keys($classNames), 'classNames' => array_values($classNames),
    ];
}

route('GET', '/d/{dept}/teachers', function (array $p) {
    $ctx = require_dept((int)$p['dept']);
    $byTeacher = [];
    foreach (rows('SELECT ct.teacher_id, c.id, c.name FROM ' . t('class_teachers') . ' ct JOIN ' . t('classes') . ' c ON c.id = ct.class_id JOIN ' . t('teachers') . ' t ON t.id = ct.teacher_id WHERE t.department_id = ? ORDER BY c.sort', [$ctx['deptId']]) as $r) {
        $byTeacher[(int)$r['teacher_id']][(int)$r['id']] = $r['name'];
    }
    return array_map(fn($t) => teacher_json($t, rank($ctx['role']), $byTeacher[(int)$t['id']] ?? []),
        rows('SELECT * FROM ' . t('teachers') . ' WHERE department_id = ? ORDER BY status, id', [$ctx['deptId']]));
});

function teacher_input(array $b, int $deptId): array
{
    return [
        in_str($b, 'name', 50, true, '이름'), in_str($b, 'position', 20), in_str($b, 'officerRole', 20),
        in_date($b, 'birthDate', false, '생년월일'), normalize_phone(in_str($b, 'phone', 20)),
        in_enum($b, 'status', ['active', 'inactive'], 'active', '상태'), in_str($b, 'notes', 2000),
        custom_encode(clean_custom($b['custom'] ?? null, $deptId, 'teacher')),
    ];
}

function save_teacher_classes(int $teacherId, int $deptId, array $classIds): void
{
    q('DELETE FROM ' . t('class_teachers') . ' WHERE teacher_id = ?', [$teacherId]);
    foreach ($classIds as $cid) {
        if (val('SELECT 1 FROM ' . t('classes') . ' WHERE id = ? AND department_id = ?', [(int)$cid, $deptId])) {
            q('INSERT IGNORE INTO ' . t('class_teachers') . ' (class_id, teacher_id) VALUES (?,?)', [(int)$cid, $teacherId]);
        }
    }
}

route('POST', '/d/{dept}/teachers', function (array $p) {
    $ctx = require_dept((int)$p['dept'], 'admin');
    $b = body();
    $id = insert_id('INSERT INTO ' . t('teachers') . ' (department_id, name, position, officer_role, birth_date, phone, status, notes, custom) VALUES (?,?,?,?,?,?,?,?,?)', [$ctx['deptId'], ...teacher_input($b, $ctx['deptId'])]);
    save_teacher_classes($id, $ctx['deptId'], is_array($b['classIds'] ?? null) ? $b['classIds'] : []);
    audit('teacher.create', 'teacher', $id);
    return ['id' => $id];
});

route('PUT', '/teachers/{id}', function (array $p) {
    $t = row('SELECT * FROM ' . t('teachers') . ' WHERE id = ?', [(int)$p['id']]);
    if (!$t) json_fail('not_found', '교사를 찾을 수 없습니다.', 404);
    $ctx = require_dept((int)$t['department_id'], 'admin');
    $b = body();
    q('UPDATE ' . t('teachers') . ' SET name=?, position=?, officer_role=?, birth_date=?, phone=?, status=?, notes=?, custom=? WHERE id=?', [...teacher_input($b, $ctx['deptId']), $t['id']]);
    if (isset($b['classIds']) && is_array($b['classIds'])) save_teacher_classes((int)$t['id'], $ctx['deptId'], $b['classIds']);
    audit('teacher.update', 'teacher', (int)$t['id']);
    return null;
});

route('DELETE', '/teachers/{id}', function (array $p) {
    $t = row('SELECT * FROM ' . t('teachers') . ' WHERE id = ?', [(int)$p['id']]);
    if (!$t) json_fail('not_found', '교사를 찾을 수 없습니다.', 404);
    require_dept((int)$t['department_id'], 'admin');
    q('DELETE FROM ' . t('teachers') . ' WHERE id = ?', [$t['id']]);
    delete_photo($t['photo_path'] ?? null);
    audit('teacher.delete', 'teacher', (int)$t['id']);
    return null;
});
