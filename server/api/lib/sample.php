<?php
declare(strict_types=1);

// 체험용 샘플 데이터. 모든 이름과 자료는 가상이고, 날짜는 오늘 기준으로 만들어서 언제 열어도 화면이 비어 보이지 않는다.
// 만든 행에는 is_sample=1 을 달아서 "샘플 데이터 삭제"가 이 행만 지울 수 있게 한다.
// 이 파일도 db()/dm_config() 에 기대지 않고 받은 PDO 와 접두어만 쓴다. (설치 중에도 쓰기 위해)

const SAMPLE_SURNAMES = ['김', '이', '박', '최', '정', '강', '조', '윤', '장', '임', '한', '오', '서', '신', '권', '황', '안', '송', '류', '홍', '배', '문', '양', '손', '백'];
const SAMPLE_GIVEN = [
    '하준', '서윤', '도윤', '예린', '시우', '지안', '민성', '하은', '우진', '채원', '서아', '현우', '지민', '서진', '하늘', '태윤',
    '민재', '도현', '유나', '지호', '예은', '길현', '유진', '도하', '가은', '서우', '지우', '재원', '하린', '승민', '서현', '민호',
    '세린', '수빈', '지유', '세영', '준서', '아름', '다은', '건우', '소윤', '이준', '하영', '주원', '시현', '나윤', '은우', '라희',
];
const SAMPLE_TEACHERS = ['이상현', '박은지', '한소영', '김민석', '정은혜', '박성훈', '이지안', '최영진', '오하나', '장믿음', '서기쁨', '류사랑'];

/** 접두어가 붙은 표 이름 */
function sp(string $p, string $name): string
{
    return "`{$p}{$name}`";
}

/**
 * 한 부서에 샘플 학생·교사·반·출석·심방·일정·공지·회의록을 넣는다.
 * 학년 단계가 없는 부서(직접 설정)에는 학생을 만들지 않는다.
 */
function sample_seed_department(PDO $pdo, string $p, int $deptId, bool $withChurchNotice = false): void
{
    mt_srand(20261025 + $deptId);
    $today = date('Y-m-d');
    $lastSunday = sunday_of($today);
    $year = (int)date('Y');

    $pdo->beginTransaction();
    try {
        $grades = $pdo->prepare('SELECT id, label, is_graduate FROM ' . sp($p, 'grade_levels') . ' WHERE department_id = ? ORDER BY sort, id');
        $grades->execute([$deptId]);
        $grades = $grades->fetchAll();
        $active = array_values(array_filter($grades, fn($g) => !(int)$g['is_graduate'] && !str_starts_with((string)$g['label'], '예비')));
        $labels = array_column($active, 'label');
        $youth = in_array('중1', $labels, true) && in_array('고3', $labels, true);

        // ===== 반 =====
        $classIds = []; // 이름 → id
        $tones = ['blue', 'green', 'orange', 'pink', 'violet', 'teal', 'sky', 'amber'];
        $classNames = $youth ? ['중1 A반', '중1 B반', '중2', '중3', '고등 A반', '고등 B반'] : array_column($active, 'label');
        $insClass = $pdo->prepare('INSERT INTO ' . sp($p, 'classes') . ' (department_id, year, name, tone, sort, is_sample) VALUES (?,?,?,?,?,1)');
        foreach ($classNames as $i => $name) {
            $insClass->execute([$deptId, $year, $name, $tones[$i % count($tones)], $i]);
            $classIds[$name] = (int)$pdo->lastInsertId();
        }
        $classFor = function (string $label) use ($youth, $classIds): ?int {
            if (!$youth) return $classIds[$label] ?? null;
            return match ($label) {
                '중1' => $classIds[mt_rand(0, 1) ? '중1 A반' : '중1 B반'],
                '중2' => $classIds['중2'],
                '중3' => $classIds['중3'],
                default => $classIds[mt_rand(0, 1) ? '고등 A반' : '고등 B반'],
            };
        };

        // ===== 학생 =====
        $used = [];
        $makeName = function () use (&$used): string {
            for ($i = 0; $i < 200; $i++) {
                $n = SAMPLE_SURNAMES[mt_rand(0, count(SAMPLE_SURNAMES) - 1)] . SAMPLE_GIVEN[mt_rand(0, count(SAMPLE_GIVEN) - 1)];
                if (!isset($used[$n])) return $used[$n] = $n;
            }
            return SAMPLE_SURNAMES[0] . SAMPLE_GIVEN[mt_rand(0, count(SAMPLE_GIVEN) - 1)];
        };
        $insStu = $pdo->prepare('INSERT INTO ' . sp($p, 'students') . ' (department_id, name, gender, birth_date, phone, school, grade_level_id, class_id, status, baptism, mentor, is_sample) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)');
        $insGd = $pdo->prepare('INSERT INTO ' . sp($p, 'guardians') . ' (student_id, relation, name, phone) VALUES (?,?,?,?)');
        $phone = fn() => sprintf('010-%04d-%04d', mt_rand(1000, 9999), mt_rand(1000, 9999));
        $schools = ['은혜중', '새빛중', '한빛중', '대성고', '푸른고', '샘물중'];
        $studentIds = [];
        $perGrade = $youth ? ['중1' => [10, 5], '중2' => [6, 2], '중3' => [7, 1], '고1' => [8, 2], '고2' => [7, 1], '고3' => [5, 0]] : [];
        foreach ($active as $gi => $g) {
            [$act, $new] = $perGrade[$g['label']] ?? [8, 2];
            for ($k = 0; $k < $act + $new; $k++) {
                $name = $makeName();
                $age = $youth ? ['중1' => 13, '중2' => 14, '중3' => 15, '고1' => 16, '고2' => 17, '고3' => 18][$g['label']] : 19 + $gi;
                $birth = sprintf('%04d-%02d-%02d', $year - $age, mt_rand(1, 12), mt_rand(1, 28));
                $insStu->execute([
                    $deptId, $name, mt_rand(0, 1) ? '남' : '여', $birth, $phone(), $schools[mt_rand(0, count($schools) - 1)],
                    (int)$g['id'], $classFor((string)$g['label']), $k >= $act ? 'new' : 'active',
                    ['-', '유아', '입교', '학습', '세례'][mt_rand(0, 4)], $k >= $act ? ['이상현', '박은지', '한소영'][mt_rand(0, 2)] : '',
                ]);
                $sid = (int)$pdo->lastInsertId();
                $studentIds[] = $sid;
                $sur = mb_substr($name, 0, 1);
                $insGd->execute([$sid, '모', $sur . ['미선', '은정', '현주', '영숙'][mt_rand(0, 3)], $phone()]);
                $insGd->execute([$sid, '부', $sur . ['성수', '진호', '영훈', '기석'][mt_rand(0, 3)], $phone()]);
            }
        }
        // 졸업·비활동 몇 명
        $gradLevel = null;
        foreach ($grades as $g) if ((int)$g['is_graduate']) $gradLevel = (int)$g['id'];
        if ($gradLevel && $youth) {
            for ($k = 0; $k < 4; $k++) {
                $insStu->execute([$deptId, $makeName(), '남', sprintf('%04d-%02d-%02d', $year - 19, mt_rand(1, 12), mt_rand(1, 28)), $phone(), '', $gradLevel, null, 'inactive', '-', '']);
            }
        }
        // 이번 주에 생일인 학생이 몇 명 있도록 한다
        $bd = $pdo->prepare('UPDATE ' . sp($p, 'students') . ' SET birth_date = ? WHERE id = ?');
        foreach ([0 => 1, 1 => 2, 9 => 4, 19 => 6] as $idx => $plus) {
            if (!isset($studentIds[$idx])) continue;
            $md = substr(add_days($today, $plus), 5);
            if ($md === '02-29') $md = '02-28'; // 윤일이 아닌 해에도 올바른 날짜가 되도록
            $bd->execute([sprintf('%04d-%s', $year - 14, $md), $studentIds[$idx]]);
        }

        // ===== 교사 =====
        $insT = $pdo->prepare('INSERT INTO ' . sp($p, 'teachers') . ' (department_id, name, position, officer_role, birth_date, phone, is_sample) VALUES (?,?,?,?,?,?,1)');
        $insCT = $pdo->prepare('INSERT INTO ' . sp($p, 'class_teachers') . ' (class_id, teacher_id) VALUES (?,?)');
        $teacherIds = [];
        $positions = ['집사', '권사', '청년', '집사', '집사', '권사'];
        $officers = ['부장', '총무', '서기', '회계', '찬양', '', '', '', '', '', '', ''];
        $classList = array_values($classIds);
        foreach (SAMPLE_TEACHERS as $i => $name) {
            $insT->execute([$deptId, $name, $positions[$i % 6], $officers[$i], sprintf('%04d-%02d-%02d', 1975 + $i, 1 + ($i * 5) % 12, 3 + ($i * 7) % 24), sprintf('010-%04d-%04d', 2000 + $i * 137, 5000 + $i * 211)]);
            $tid = (int)$pdo->lastInsertId();
            $teacherIds[] = $tid;
            if ($i < count($classList)) $insCT->execute([$classList[$i], $tid]);
        }

        // ===== 출석 (최근 12주) =====
        $insAtt = $pdo->prepare('INSERT INTO ' . sp($p, 'attendance') . ' (student_id, attend_date, status) VALUES (?,?,?)');
        $insTA = $pdo->prepare('INSERT INTO ' . sp($p, 'teacher_attendance') . ' (teacher_id, attend_date, status) VALUES (?,?,?)');
        $enrolled = [];
        foreach ($pdo->query('SELECT id FROM ' . sp($p, 'students') . ' WHERE department_id = ' . (int)$deptId . " AND status <> 'inactive'")->fetchAll() as $r) $enrolled[] = (int)$r['id'];
        $streak = [];
        foreach ([2 => 4, 5 => 3, 9 => 3] as $idx => $weeks) if (isset($enrolled[$idx])) $streak[$enrolled[$idx]] = $weeks;
        for ($w = 0; $w < 12; $w++) {
            $date = add_days($lastSunday, -7 * $w);
            foreach ($enrolled as $sid) {
                if (isset($streak[$sid])) {
                    $insAtt->execute([$sid, $date, $w < $streak[$sid] ? 'absent' : 'present']);
                    continue;
                }
                $habit = 0.55 + (($sid * 37) % 40) / 100;
                $r = mt_rand() / mt_getrandmax();
                $insAtt->execute([$sid, $date, $r < $habit ? 'present' : ($r < $habit + 0.06 ? 'online' : 'absent')]);
            }
            foreach ($teacherIds as $i => $tid) {
                $insTA->execute([$tid, $date, ($i === 3 && $w === 0) || mt_rand(0, 99) >= 88 ? 'absent' : 'present']);
            }
        }

        // ===== 심방 =====
        $insV = $pdo->prepare('INSERT INTO ' . sp($p, 'visits') . ' (department_id, visit_date, method, companions, title, content, follow_up, follow_up_due, follow_up_done, visibility, author_name, is_sample) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)');
        $insVT = $pdo->prepare('INSERT INTO ' . sp($p, 'visit_targets') . ' (visit_id, target_type, target_id) VALUES (?,?,?)');
        $visits = [
            [-1, '가정', '이상현, 박은지', '어머니와 진로 상담', '시험 기간이라 많이 지쳐 있었음. 다음 달에 진로 상담을 이어가기로 함.', '진로 상담 일정 잡기', 5, 0, 'pastors', [['student', 2]]],
            [-4, '전화', '', '시험 기간 안부', '시험 끝나고 연락하기로 함.', '시험 끝난 뒤 연락하기', 8, 0, 'pastors', [['student', 6]]],
            [-7, '문자·카톡', '', '연속 결석 확인', '카톡 확인했으나 답이 없음.', '', null, 0, 'pastors', [['student', 3]]],
            [-14, '교회', '박은지', '새가족 정착 면담', '예배 후 함께 점심을 먹으며 이야기 나눔.', '', null, 0, 'staff', [['student', 11], ['student', 1]]],
            [-21, '카페', '', '교사 격려 모임', '', '', null, 0, 'private', [['teacher', 2]]],
            [-28, '가정', '한소영', '이사 후 안부 방문', '', '', null, 1, 'pastors', [['student', 4]]],
        ];
        foreach ($visits as [$off, $method, $comp, $title, $content, $fu, $due, $done, $vis, $targets]) {
            $insV->execute([$deptId, add_days($today, $off), $method, $comp, $title, $content, $fu, $due === null ? null : add_days($today, $due), $done, $vis, '김선생']);
            $vid = (int)$pdo->lastInsertId();
            foreach ($targets as [$type, $n]) {
                $id = $type === 'student' ? ($enrolled[$n] ?? null) : ($teacherIds[$n - 1] ?? null);
                if ($id) $insVT->execute([$vid, $type, $id]);
            }
        }

        // ===== 일정 =====
        $insE = $pdo->prepare('INSERT INTO ' . sp($p, 'events') . ' (department_id, event_date, kind, title, prayer, is_sample) VALUES (?,?,?,?,?,1)');
        for ($w = -14; $w <= 14; $w++) {
            $insE->execute([$deptId, add_days($lastSunday, $w * 7), 'ministry', '주일 공과', $w % 2 ? '이상현 선생' : '박은지 선생']);
        }
        $next = add_days($lastSunday, 7);
        $insE->execute([$deptId, add_days($next, 7), 'ministry', '추수감사 예배', '']);
        $insE->execute([$deptId, add_days($next, 14), 'meeting', '교사회의', '']);
        $insE->execute([$deptId, add_days($today, 6), 'meeting', '교사회의', '']);
        $insE->execute([$deptId, add_days($today, 6), 'due', '수련회 신청', '']);
        $insE->execute([$deptId, add_days($next, 21), 'ministry', '겨울 수련회 준비', '']);

        // ===== 공지 · 회의록 =====
        $insN = $pdo->prepare('INSERT INTO ' . sp($p, 'notices') . ' (department_id, title, body, pinned, publish_date, is_sample) VALUES (?,?,?,?,?,1)');
        if ($withChurchNotice) $insN->execute([null, '11월 학부모 간담회 안내', '11월 셋째 주일 예배 후 본당에서 진행합니다.', 0, add_days($today, -3)]);
        $insN->execute([$deptId, '겨울 수련회 장소 투표', '다음 주일까지 투표해 주세요.', 1, add_days($today, -7)]);
        $insN->execute([$deptId, '찬양팀 연습 시간 변경', '연습은 예배 30분 전에 시작합니다.', 0, add_days($today, -14)]);
        $insM = $pdo->prepare('INSERT INTO ' . sp($p, 'meetings') . ' (department_id, meeting_date, title, content, attendees, is_sample) VALUES (?,?,?,?,?,1)');
        $insM->execute([$deptId, add_days($today, -9), '10월 교사회의', "1. 수련회 장소 후보 세 곳 논의\n2. 장기 결석 학생 심방 담당 정하기\n3. 11월 추수감사 예배 순서 확인", '이상현, 박은지, 한소영, 김민석']);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** 샘플 데이터로 시작할 때 첫 부서 외에 보여줄 두 번째 샘플 부서(청년부)를 만든다. @return int 새 부서 id */
function sample_create_young_department(PDO $pdo, string $p): int
{
    $terms = json_encode(array_merge(DEFAULT_TERMS, ['department' => '샘플 청년부', 'student' => '청년', 'teacher' => '리더', 'class' => '목장', 'grade' => '구분', 'head' => '회장', 'visit' => '돌봄', 'newMember' => '새친구']), JSON_UNESCAPED_UNICODE);
    $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM ' . sp($p, 'departments'))->fetchColumn();
    $pdo->prepare('INSERT INTO ' . sp($p, 'departments') . ' (name, pastor_name, head_name, terms, sort, is_sample) VALUES (?,?,?,?,?,1)')
        ->execute(['샘플 청년부', '이소망 목사', '정다윗', $terms, $sort]);
    $id = (int)$pdo->lastInsertId();
    $insG = $pdo->prepare('INSERT INTO ' . sp($p, 'grade_levels') . ' (department_id, label, tone, sort, next_id, is_graduate) VALUES (?,?,?,?,NULL,?)');
    foreach ([['대학 새내기', 'sky', 0], ['대학생', 'blue', 0], ['사회초년생', 'green', 0], ['졸업', 'slate', 1]] as $i => [$label, $tone, $grad]) {
        $insG->execute([$id, $label, $tone, $i, $grad]);
    }
    return $id;
}

/** 샘플 행의 개수 (설정 화면에 보여준다) */
function sample_counts(PDO $pdo, string $p): array
{
    $one = fn(string $t) => (int)$pdo->query('SELECT COUNT(*) FROM ' . sp($p, $t) . ' WHERE is_sample = 1')->fetchColumn();
    return [
        'departments' => $one('departments'), 'students' => $one('students'), 'teachers' => $one('teachers'),
        'visits' => $one('visits'), 'events' => $one('events'), 'notices' => $one('notices'), 'meetings' => $one('meetings'),
    ];
}

/**
 * 샘플 데이터만 지운다. 직접 입력한 자료는 건드리지 않는다.
 * 샘플 부서에 직접 입력한 학생·교사가 있으면 그 부서는 남기고 샘플 표시만 뗀다.
 * @return array<string, int> 지운 개수
 */
function sample_delete(PDO $pdo, string $p): array
{
    $before = sample_counts($pdo, $p);
    $pdo->beginTransaction();
    try {
        foreach (['notices', 'meetings', 'events', 'visits', 'ministry_plans', 'students', 'teachers', 'classes'] as $t) {
            $pdo->exec('DELETE FROM ' . sp($p, $t) . ' WHERE is_sample = 1');
        }
        // 직접 입력한 학생이 샘플 반에 들어 있었다면 반 지정만 푼다
        $pdo->exec('UPDATE ' . sp($p, 'students') . ' SET class_id = NULL WHERE class_id IS NOT NULL AND class_id NOT IN (SELECT id FROM ' . sp($p, 'classes') . ')');

        foreach ($pdo->query('SELECT id FROM ' . sp($p, 'departments') . ' WHERE is_sample = 1')->fetchAll() as $d) {
            $id = (int)$d['id'];
            $has = 0;
            foreach (['students', 'teachers', 'visits', 'events', 'notices', 'meetings'] as $t) {
                $has += (int)$pdo->query('SELECT COUNT(*) FROM ' . sp($p, $t) . ' WHERE department_id = ' . $id)->fetchColumn();
            }
            if ($has === 0) $pdo->exec('DELETE FROM ' . sp($p, 'departments') . ' WHERE id = ' . $id);
            else $pdo->exec('UPDATE ' . sp($p, 'departments') . ' SET is_sample = 0 WHERE id = ' . $id);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return $before;
}
