<?php
declare(strict_types=1);

// 추가 항목(사용자 정의 항목)의 정의를 읽고, 사용자가 보낸 값을 항목 정의에 맞게 검사한다.

const CUSTOM_TYPES = ['text', 'longtext', 'bool', 'select', 'date', 'number'];
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
