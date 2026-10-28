<?php
declare(strict_types=1);

// 날짜 계산과 화면용 데이터 모양 바꾸기.

function today_str(): string
{
    return date('Y-m-d');
}

function add_days(string $iso, int $n): string
{
    return date('Y-m-d', strtotime($iso . ' ' . ($n >= 0 ? '+' : '') . $n . ' days'));
}

/** 그 날짜가 속한 주의 일요일 */
function sunday_of(string $iso): string
{
    $dow = (int)date('w', strtotime($iso));
    return add_days($iso, -$dow);
}

/** @return string[] 오늘 이전(포함)의 일요일 n개, 최근 순 */
function recent_sundays(int $n): array
{
    $last = sunday_of(today_str());
    $out = [];
    for ($i = 0; $i < $n; $i++) $out[] = add_days($last, -7 * $i);
    return $out;
}

/** 생일이 기준일부터 며칠 뒤인지. 올해 지났으면 내년. */
function days_until_birthday(string $birth, string $from): int
{
    $base = new DateTimeImmutable($from);
    $b = new DateTimeImmutable($birth);
    $y = (int)$base->format('Y');
    $next = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, (int)$b->format('n'), (int)$b->format('j')));
    if ($next < $base) $next = $next->modify('+1 year');
    return (int)$base->diff($next)->days;
}

function month_valid(string $m): bool
{
    return (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m);
}

function bool_int(mixed $v): int
{
    return ($v === true || $v === 1 || $v === '1' || $v === 'true') ? 1 : 0;
}

/** 길이 n 의 읽기 쉬운 임시 비밀번호 (헷갈리는 글자 제외) */
function temp_password(int $n = 10): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRTUVWXY23456789';
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $chars[random_int(0, strlen($chars) - 1)];
    return $s;
}

/** 서버 설정(settings 표)의 값 읽기·쓰기 */
function setting(string $key, mixed $default = null): mixed
{
    $v = val('SELECT v FROM ' . t('settings') . ' WHERE k = ?', [$key]);
    if ($v === null) return $default;
    $d = json_decode((string)$v, true);
    return $d === null && $v !== 'null' ? $default : $d;
}

function set_setting(string $key, mixed $value): void
{
    q(
        'INSERT INTO ' . t('settings') . ' (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
        [$key, json_encode($value, JSON_UNESCAPED_UNICODE)]
    );
}

const DEFAULT_TERMS = [
    'department' => '중고등부', 'student' => '학생', 'teacher' => '교사', 'class' => '반', 'grade' => '학년',
    'cohortFormat' => '{n}기', 'head' => '부장', 'pastor' => '교역자', 'visit' => '심방', 'newMember' => '새가족',
];

const FONT_IDS = ['noto-sans', 'nanum-gothic', 'gowun-dodum', 'black-han-sans', 'do-hyeon', 'jua', 'system'];
const TONES = ['blue', 'green', 'violet', 'sky', 'pink', 'orange', 'teal', 'amber', 'slate'];

/** 사용자가 보낸 용어 배열을 검사해서 알려진 키만 남긴다. */
function clean_terms(array $in): array
{
    $out = DEFAULT_TERMS;
    foreach (DEFAULT_TERMS as $k => $_) {
        if (isset($in[$k]) && is_string($in[$k])) {
            $v = trim($in[$k]);
            if ($v !== '' && mb_strlen($v) <= 30) $out[$k] = $v;
        }
    }
    return $out;
}

function dept_row_to_json(array $d, array $grades): array
{
    $terms = json_decode((string)$d['terms'], true);
    return [
        'id' => (int)$d['id'],
        'name' => $d['name'],
        'pastorName' => $d['pastor_name'],
        'headName' => $d['head_name'],
        'terms' => clean_terms(is_array($terms) ? $terms : []),
        'gradeLevels' => array_map(fn($g) => [
            'id' => (int)$g['id'], 'label' => $g['label'], 'tone' => $g['tone'],
            'nextId' => $g['next_id'] === null ? null : (int)$g['next_id'], 'isGraduate' => (bool)$g['is_graduate'],
        ], $grades),
    ];
}

/** 부서 id → 학년 단계 목록 (순서대로) */
function grade_levels_of(int $deptId): array
{
    return rows('SELECT id, label, tone, next_id, is_graduate FROM ' . t('grade_levels') . ' WHERE department_id = ? ORDER BY sort, id', [$deptId]);
}

function tone_of_grade(array $grades, int $gradeId): string
{
    foreach ($grades as $g) if ((int)$g['id'] === $gradeId) return $g['tone'] ?: 'slate';
    return 'slate';
}

function label_of_grade(array $grades, int $gradeId): string
{
    foreach ($grades as $g) if ((int)$g['id'] === $gradeId) return (string)$g['label'];
    return '';
}
