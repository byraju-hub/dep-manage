<?php
declare(strict_types=1);

// 사진, 백업, 업데이트(마이그레이션).

// ===== 사진 =====

/** 사진이 속한 표와 그 행을 찾는다. */
function photo_owner(string $type, int $id): array
{
    $table = ['student' => 'students', 'teacher' => 'teachers'][$type] ?? null;
    if (!$table) json_fail('not_found', '찾을 수 없습니다.', 404);
    $row = row('SELECT id, department_id, photo_path' . ($type === 'student' ? ', class_id' : '') . ' FROM ' . t($table) . ' WHERE id = ?', [$id]);
    if (!$row) json_fail('not_found', '찾을 수 없습니다.', 404);
    return [$table, $row];
}

// 사진은 개인정보라서 로그인한 교사 이상에게만 내려준다. (열람 역할에게는 보이지 않는다.)
route('GET', '/photos/{type}/{id}', function (array $p) {
    [, $row] = photo_owner($p['type'], (int)$p['id']);
    require_dept((int)$row['department_id'], 'teacher');
    $file = $row['photo_path'] ? photo_path_for($row['photo_path'], ($_GET['size'] ?? '') === 'thumb') : null;
    if (!$file || !is_file($file)) json_fail('not_found', '사진이 없습니다.', 404);
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=86400'); // 사진을 바꾸면 주소(v=)가 바뀌므로 오래 캐시해도 된다
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
});

route('POST', '/photos/{type}/{id}', function (array $p) {
    [$table, $row] = photo_owner($p['type'], (int)$p['id']);
    $ctx = require_dept((int)$row['department_id'], 'teacher');
    if ($p['type'] === 'student' ? !can_edit_student($ctx, $row) : rank($ctx['role']) < 3) {
        json_fail('forbidden', '이 사진을 바꿀 권한이 없습니다.', 403);
    }
    $token = store_photo($_FILES['photo'] ?? []);
    delete_photo($row['photo_path']);
    q('UPDATE ' . t($table) . ' SET photo_path = ? WHERE id = ?', [$token, $row['id']]);
    audit('photo.upload', $p['type'], (int)$row['id']);
    return ['photo' => $token];
});

route('DELETE', '/photos/{type}/{id}', function (array $p) {
    [$table, $row] = photo_owner($p['type'], (int)$p['id']);
    $ctx = require_dept((int)$row['department_id'], 'teacher');
    if ($p['type'] === 'student' ? !can_edit_student($ctx, $row) : rank($ctx['role']) < 3) {
        json_fail('forbidden', '이 사진을 지울 권한이 없습니다.', 403);
    }
    delete_photo($row['photo_path']);
    q('UPDATE ' . t($table) . ' SET photo_path = NULL WHERE id = ?', [$row['id']]);
    audit('photo.delete', $p['type'], (int)$row['id']);
    return null;
});

// ===== 백업 =====

/** 접두어가 붙은 모든 표를 SQL 파일로 쓴다. 다른 도구(phpMyAdmin 등)로도 그대로 복원할 수 있다. */
function write_sql_dump($fh): void
{
    $pdo = db();
    $prefix = dm_config()['prefix'];
    fwrite($fh, "-- dep-manage 백업 (" . DM_VERSION . ") " . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "-- 이 파일에는 비밀번호 해시와 개인정보가 들어 있어요. 안전한 곳에만 보관하세요.\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET time_zone = '+09:00';\n\n");

    $like = $pdo->quote(str_replace(['_', '%'], ['\\_', '\\%'], $prefix) . '%');
    $tables = $pdo->query('SHOW TABLES LIKE ' . $like)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $tbl = '`' . str_replace('`', '', (string)$table) . '`';
        $create = $pdo->query('SHOW CREATE TABLE ' . $tbl)->fetch(PDO::FETCH_NUM);
        fwrite($fh, "DROP TABLE IF EXISTS $tbl;\n" . $create[1] . ";\n\n");
        if (str_ends_with((string)$table, 'login_attempts')) continue; // 잠깐 쓰는 기록은 담지 않는다
        $st = $pdo->query('SELECT * FROM ' . $tbl, PDO::FETCH_NUM);
        $batch = [];
        foreach ($st as $r) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $r);
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 200) {
                fwrite($fh, "INSERT INTO $tbl VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) fwrite($fh, "INSERT INTO $tbl VALUES\n" . implode(",\n", $batch) . ";\n");
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
}

// 데이터베이스 전체(SQL)와 사진을 zip 하나로 내려준다. 교회 관리자만 받을 수 있다.
route('GET', '/system/backup', function () {
    require_owner();
    if (!class_exists('ZipArchive')) json_fail('no_zip', '이 서버에서는 zip 을 만들 수 없어요.', 501);
    @set_time_limit(120);

    $sql = tempnam(sys_get_temp_dir(), 'dmsql');
    $zipPath = tempnam(sys_get_temp_dir(), 'dmzip');
    $fh = fopen($sql, 'wb');
    write_sql_dump($fh);
    fclose($fh);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) json_fail('zip_failed', '백업 파일을 만들지 못했어요.', 500);
    $zip->addFile($sql, 'backup.sql');
    $dir = photo_dir();
    foreach (glob($dir . '/*.jpg') ?: [] as $f) $zip->addFile($f, 'photos/' . basename($f));
    $zip->addFromString('README.txt', "dep-manage 백업\n\nbackup.sql : 데이터베이스 전체 (phpMyAdmin 의 가져오기로 복원할 수 있어요)\nphotos/    : 학생·교사 사진\n\n이 파일에는 개인정보와 비밀번호 해시가 들어 있어요. 안전한 곳에만 보관하세요.\n");
    $zip->close();

    audit('backup.download');
    $name = 'dep-manage-backup-' . date('Ymd-His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-store');
    readfile($zipPath);
    @unlink($zipPath);
    @unlink($sql);
    exit;
});

// ===== 업데이트 (데이터베이스 구조) =====

route('GET', '/system/migrations', function () {
    require_owner();
    $dir = DM_ROOT . '/migrations';
    $cur = current_schema_version(db(), dm_config()['prefix']);
    $latest = latest_schema_version($dir);
    return ['current' => $cur, 'latest' => $latest, 'pending' => $latest > $cur];
});

route('POST', '/system/migrate', function () {
    require_owner();
    $p = dm_config()['prefix'];
    $n = run_migrations(db(), $p, DM_ROOT . '/migrations');
    audit('system.migrate', null, null, "{$n}개 적용");
    return ['applied' => $n, 'current' => current_schema_version(db(), $p)];
});
