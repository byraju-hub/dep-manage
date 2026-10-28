<?php
declare(strict_types=1);

// 추가 항목(사용자 정의 항목)의 정의를 읽고, 사용자가 보낸 값을 항목 정의에 맞게 검사한다.

const CUSTOM_TYPES = ['text', 'longtext', 'bool', 'select', 'date', 'number', 'lunar'];

/** 새 부서에 처음부터 만들어 두는 학생 항목. (개인정보 보호를 위해 꼭 필요한 것들) */
const CUSTOM_DEFAULTS = [
    ['label' => '알레르기 · 건강 유의사항', 'type' => 'longtext', 'options' => []],
    ['label' => '사진 · 개인정보 이용 동의', 'type' => 'select', 'options' => ['동의', '일부 동의', '미동의', '확인 전']],
];

function seed_custom_defaults(int $deptId): void
{
    foreach (CUSTOM_DEFAULTS as $i => $d) {
        q(
            'INSERT INTO ' . t('custom_fields') . " (department_id, entity, label, field_type, options, sort, show_in_list) VALUES (?, 'student', ?, ?, ?, ?, 0)",
            [$deptId, $d['label'], $d['type'], $d['options'] ? json_encode($d['options'], JSON_UNESCAPED_UNICODE) : null, $i]
        );
    }
}
const CUSTOM_MAX_PER_ENTITY = 20;

/** @return array<int, array> 항목 번호 → 정의(options 는 배열로 풀어 둔다) */
function custom_defs(int $deptId, string $entity): array
{
    $out = [];
    foreach (rows('SELECT * FROM ' . t('custom_fields') . ' WHERE department_id = ? AND entity = ? ORDER BY sort, id', [$deptId, $entity]) as $r) {
        $opts = json_decode((string)$r['options'], true);
        $r['options'] = is_array($opts) ? array_values($opts) : [];
        $out[(int)$r['id']] = $r;
    }
    return $out;
}

function custom_def_json(array $d): array
{
    return [
        'id' => (int)$d['id'], 'deptId' => (int)$d['department_id'], 'entity' => $d['entity'], 'label' => $d['label'],
        'type' => $d['field_type'], 'options' => is_array($d['options']) ? $d['options'] : (json_decode((string)$d['options'], true) ?: []),
        'showInList' => (bool)(int)$d['show_in_list'],
    ];
}

/** 사용자가 보낸 {항목 번호: 값} 을 검사해서, 정의가 있는 항목의 값만 남긴다. 값이 비어 있으면 뺀다. */
function clean_custom(mixed $in, int $deptId, string $entity): array
{
    if (!is_array($in)) return [];
    $out = [];
    foreach (custom_defs($deptId, $entity) as $id => $d) {
        $k = (string)$id;
        if (!array_key_exists($k, $in)) continue;
        $v = $in[$k];
        if ($v === null || $v === '') continue;
        $label = (string)$d['label'];
        switch ($d['field_type']) {
            case 'bool':
                $out[$k] = ($v === true || $v === 1 || $v === '1' || $v === 'true' || $v === '예');
                break;
            case 'select':
                $s = trim((string)$v);
                if (!in_array($s, $d['options'], true)) json_fail('invalid', "{$label}: 목록에 없는 값이에요.", 422);
                $out[$k] = $s;
                break;
            case 'date':
                $s = trim((string)$v);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) || !checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4))) {
                    json_fail('invalid', "{$label}: 날짜 형식이 올바르지 않아요.", 422);
                }
                $out[$k] = $s;
                break;
            case 'lunar':
                // 음력 생년월일. 'YYYY-MM-DD', 윤달이면 끝에 L
                $s = trim((string)$v);
                if (!preg_match('/^[0-9]{4}-([0-9]{2})-([0-9]{2})L?$/', $s, $mm) || (int)$mm[1] < 1 || (int)$mm[1] > 12 || (int)$mm[2] < 1 || (int)$mm[2] > 30) {
                    json_fail('invalid', "{$label}: 음력 날짜 형식이 올바르지 않아요.", 422);
                }
                $out[$k] = $s;
                break;
            case 'number':
                if (!is_numeric($v)) json_fail('invalid', "{$label}: 숫자로 적어 주세요.", 422);
                $out[$k] = $v + 0;
                break;
            case 'longtext':
                $out[$k] = mb_substr(trim((string)$v), 0, 2000);
                break;
            default:
                $out[$k] = mb_substr(trim((string)$v), 0, 200);
        }
    }
    return $out;
}

function custom_encode(array $c): ?string
{
    return $c ? json_encode($c, JSON_UNESCAPED_UNICODE) : null;
}

/** 화면으로 내려줄 모양. 비어 있어도 {} 로 나가도록 객체로 만든다. */
function custom_decode(?string $raw): object
{
    $d = $raw ? json_decode($raw, true) : null;
    return (object)(is_array($d) ? $d : []);
}
