<?php
declare(strict_types=1);

// 교적 관리(ch-manage)와의 연동. 같은 DB 에 교적 프로그램이 설치되어 있고 연결 표(cm_links)가 있을 때만 동작한다.
//
// 교적과 연결된 학생·교사는 인적사항의 주인이 교적이다.
//   학생: 이름·성별·생년월일·연락처·주소·세례  /  교사: 이름·생년월일·연락처
// 이 칸들은 여기서 고쳐도 반영하지 않고(서버가 무시), 화면에서는 잠가서 "교적에서 수정"이라고 안내한다.
// 부서·학년·반·출석·부서 심방 같은 부서 관리의 일은 그대로 여기서 한다.
// 교적 프로그램이 없으면 아무 일도 하지 않는다. (한쪽만 쓰는 교회, 나중에 하나를 추가하는 교회 모두 지원)

/** 연결 표 이름. 교적 프로그램이 없거나 구조가 다르면 null. */
function cm_links_table(): ?string
{
    static $t = false;
    if ($t !== false) return $t;
    $cfg = dm_config() ?? [];
    $prefix = $cfg['cm_prefix'] ?? 'cm_'; // 교적을 다른 접두어로 설치했다면 data/config.php 에 cm_prefix 를 적는다
    if (!preg_match('/^[a-z][a-z0-9]{0,14}_$/', (string)$prefix) || $prefix === ($cfg['prefix'] ?? 'dm_')) return $t = null;
    try {
        $name = $prefix . 'links';
        $have = array_column(rows('SELECT COLUMN_NAME AS cn FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name]), 'cn');
        foreach (['person_id', 'dm_kind', 'dm_id'] as $c) if (!in_array($c, $have, true)) return $t = null;
        return $t = '`' . $name . '`';
    } catch (Throwable) {
        return $t = null; // 연동 확인에 실패해도 부서 관리의 일은 막지 않는다
    }
}

/** 교적과 연결된 학생(또는 교사) 번호 모음 @return array<int, true> */
function cm_linked_set(string $kind, array $ids): array
{
    $tbl = cm_links_table();
    if ($tbl === null || !$ids) return [];
    $out = [];
    foreach (rows("SELECT dm_id FROM {$tbl} WHERE dm_kind = ? AND dm_id IN (" . marks($ids) . ')', [$kind, ...array_map('intval', $ids)]) as $r) $out[(int)$r['dm_id']] = true;
    return $out;
}

function cm_is_linked(string $kind, int $id): bool
{
    return isset(cm_linked_set($kind, [$id])[$id]);
}

/** 학생·교사가 지워지면 연결도 지운다. */
function cm_unlink(string $kind, int $id): void
{
    $tbl = cm_links_table();
    if ($tbl === null) return;
    try {
        q("DELETE FROM {$tbl} WHERE dm_kind = ? AND dm_id = ?", [$kind, $id]);
    } catch (Throwable) {
    }
}

/**
 * 교적(ch-manage)이 같은 DB 에 있으면 교적의 개인정보 책임자·문의처를 읽어 온다. (읽기만 한다.)
 * 책임자는 church_info.privacyOfficer, 전화·메일은 privacyPhone/privacyEmail, 비었으면 대표 전화·메일.
 * @return array{officer:string,contact:string}|null 교적이 없거나 비어 있으면 null
 */
function cm_privacy_contact(): ?array
{
    if (cm_links_table() === null) return null;
    try {
        $prefix = (dm_config() ?? [])['cm_prefix'] ?? 'cm_';
        $raw = val('SELECT church_info FROM `' . $prefix . 'site_settings` WHERE id = 1');
        $ci = json_decode((string)$raw, true);
        if (!is_array($ci)) return null;
        $s = fn(string $k): string => is_string($ci[$k] ?? null) ? trim($ci[$k]) : '';
        $officer = $s('privacyOfficer');
        $phone = $s('privacyPhone') ?: $s('phone');
        $email = $s('privacyEmail') ?: $s('email');
        $contact = trim($phone . ($phone !== '' && $email !== '' ? ' · ' : '') . $email);
        if ($officer === '' && $contact === '') return null;
        return ['officer' => $officer, 'contact' => $contact];
    } catch (Throwable) {
        return null;
    }
}
