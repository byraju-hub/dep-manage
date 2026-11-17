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

/**
 * 교적(ch-manage)의 최고관리자 계정. 아이디가 같고 '사용' 상태인 최고관리자일 때만 돌려준다. (읽기만 한다.)
 * 이 계정은 부서 관리에서도 교회 관리자로 로그인할 수 있고, 비밀번호는 교적 것을 쓴다.
 * @return array{name:string,hash:string}|null
 */
function cm_super_user(string $loginId): ?array
{
    if (cm_links_table() === null) return null;
    try {
        $prefix = (dm_config() ?? [])['cm_prefix'] ?? 'cm_';
        $u = row('SELECT * FROM `' . $prefix . 'users` WHERE login_id = ?', [$loginId]);
        if (!$u || (int)$u['is_super'] !== 1 || $u['status'] !== '사용') return null;
        if ((int)($u['is_demo'] ?? 0) === 1) return null; // 교적의 체험 계정은 연결하지 않는다
        // 부서 쪽의 체험 계정(보기 전용)도 연결 대상이 아니다
        if ((int)val('SELECT COUNT(*) FROM ' . t('users') . ' WHERE login_id = ? AND is_demo = 1', [$loginId]) > 0) return null;
        return ['name' => (string)$u['name'], 'hash' => (string)$u['password_hash']];
    } catch (Throwable) {
        return null;
    }
}

/**
 * 교적 최고관리자가 부서 관리에 로그인했을 때 부서 쪽 계정을 맞춘다.
 * 같은 아이디의 계정이 있으면 같은 사람으로 보고 연결하고, 없으면 새로 만든다. 둘 다 교회 관리자로 둔다.
 * 부서 쪽 비밀번호는 쓸 수 없는 값으로 바꿔 둔다. (비밀번호는 항상 교적 것으로 확인하므로 한 곳에서만 관리된다.)
 */
function cm_sync_dm_user(string $loginId, string $name): array
{
    $u = row('SELECT * FROM ' . t('users') . ' WHERE login_id = ?', [$loginId]);
    $unusable = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    if ($u) {
        q('UPDATE ' . t('users') . " SET is_owner = 1, status = 'active', must_change_password = 0, password_hash = ? WHERE id = ?", [$unusable, $u['id']]);
        $linked = 'cm.link';
    } else {
        q('INSERT INTO ' . t('users') . " (login_id, password_hash, name, is_owner, status) VALUES (?, ?, ?, 1, 'active')", [$loginId, $unusable, $name]);
        $linked = 'cm.create';
    }
    $u = row('SELECT * FROM ' . t('users') . ' WHERE login_id = ?', [$loginId]);
    $_SESSION['cm_audit'] = $linked;
    return $u;
}
