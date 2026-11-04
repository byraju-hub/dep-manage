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

// ===== 백업 복원 =====

/** SQL 한 줄 안에서 따옴표(')가 열린 채 끝나는지. 앞 줄에서 이어진 상태($inQuote)를 받아 갱신한다. */
function sql_scan_line(string $line, bool $inQuote): bool
{
    $n = strlen($line);
    for ($i = 0; $i < $n; $i++) {
        $c = $line[$i];
        if ($inQuote) {
            if ($c === chr(92)) { $i++; continue; } // 역슬래시 다음 글자는 건너뛴다
            if ($c === "'") {
                if ($i + 1 < $n && $line[$i + 1] === "'") { $i++; continue; }
                $inQuote = false;
            }
        } elseif ($c === "'") {
            $inQuote = true;
        }
    }
    return $inQuote;
}

/**
 * 우리 프로그램이 만든 백업 SQL 을 실행한다. 허용된 문장(SET, 접두어가 맞는 DROP·CREATE·INSERT)만 실행하고 나머지는 거부한다.
 * @param callable(string): void $exec
 * @return list<string> 파일에 들어 있던 표 이름
 */
function run_dump_file(string $file, string $prefix, callable $exec): array
{
    $fh = fopen($file, 'rb');
    if (!$fh) json_fail('read_failed', '백업 파일을 읽지 못했어요.', 500);
    $tables = [];
    $stmt = '';
    $inQuote = false;
    while (($line = fgets($fh)) !== false) {
        if ($stmt === '' && !$inQuote) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '--')) continue;
        }
        $stmt .= $line;
        $inQuote = sql_scan_line($line, $inQuote);
        if ($inQuote || !str_ends_with(rtrim($line), ';')) continue;

        $s = trim($stmt);
        $stmt = '';
        if (preg_match('/^(DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO) `([A-Za-z0-9_]+)`/', $s, $m)) {
            if (!str_starts_with($m[2], $prefix)) {
                fclose($fh);
                json_fail('wrong_prefix', '이 백업은 다른 설치(표 이름 접두어가 다름)에서 만든 파일이라 복원할 수 없어요.', 422);
            }
            if ($m[1] === 'CREATE TABLE') $tables[] = $m[2];
        } elseif (!preg_match('/^SET (NAMES utf8mb4|FOREIGN_KEY_CHECKS = [01]|time_zone = \'\+09:00\');$/', $s)) {
            fclose($fh);
            json_fail('bad_backup', '백업 파일에 허용되지 않는 내용이 있어서 복원하지 않았어요.', 422);
        }
        $exec($s);
    }
    fclose($fh);
    return $tables;
}

/** 복원할 파일을 미리 훑어서(실행하지 않고) 문제가 없는지 확인한다. */
function check_dump_file(string $file, string $prefix): array
{
    return run_dump_file($file, $prefix, function (string $s): void {});
}

// 백업 zip 으로 되돌린다. 교회 관리자만, 확인 글자('복원')를 직접 입력해야 한다.
// 지금 데이터는 먼저 data/ 폴더에 SQL 로 저장해 두고, 복원이 도중에 실패하면 그것으로 되돌린다.
route('POST', '/system/restore', function () {
    require_owner();
    if (!class_exists('ZipArchive')) json_fail('no_zip', '이 서버에서는 zip 을 읽을 수 없어요.', 501);
    if (($_POST['confirm'] ?? '') !== '복원') json_fail('confirm', "확인을 위해 '복원'이라고 입력해 주세요.", 422);
    $f = $_FILES['backup'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        json_fail('upload', '백업 파일을 올리지 못했어요. 파일이 너무 크면 서버의 업로드 제한(' . ini_get('upload_max_filesize') . ')을 넘었을 수 있어요.', 422);
    }
    @set_time_limit(300);
    $prefix = dm_config()['prefix'];

    $zip = new ZipArchive();
    if ($zip->open($f['tmp_name']) !== true) json_fail('bad_zip', '백업 zip 파일을 열지 못했어요.', 422);
    $sqlIdx = $zip->locateName('backup.sql');
    if ($sqlIdx === false) json_fail('bad_zip', '이 zip 안에 backup.sql 이 없어요. dep-manage 에서 내려받은 백업 파일이 맞는지 확인해 주세요.', 422);

    $work = tempnam(sys_get_temp_dir(), 'dmrs');
    file_put_contents($work, $zip->getFromIndex($sqlIdx));
    $head = (string)fgets(fopen($work, 'rb'));
    if (!preg_match('/^-- dep-manage 백업 \(([0-9.]+)\)/u', $head, $hm)) {
        @unlink($work);
        json_fail('bad_zip', 'dep-manage 백업 파일이 아니에요.', 422);
    }
    if (version_compare($hm[1], DM_VERSION, '>')) {
        @unlink($work);
        json_fail('too_new', "이 백업은 더 새 버전({$hm[1]})에서 만든 거예요. 먼저 프로그램을 새 버전으로 올려 주세요.", 422);
    }
    try {
        $inBackup = check_dump_file($work, $prefix); // 실행 없이 검사만
    } catch (Throwable $e) {
        @unlink($work);
        throw $e;
    }
    if (!in_array($prefix . 'users', $inBackup, true) || !in_array($prefix . 'settings', $inBackup, true)) {
        @unlink($work);
        json_fail('bad_zip', '백업 파일에 필요한 표(사용자·설정)가 없어요.', 422);
    }

    // 지금 상태를 먼저 저장
    $safety = DM_DATA . '/before-restore-' . date('Ymd-His') . '.sql';
    $fh = fopen($safety, 'wb');
    write_sql_dump($fh);
    fclose($fh);

    $pdo = db();
    $exec = function (string $s) use ($pdo): void { $pdo->exec($s); };
    try {
        // 백업에 없는 표(더 새 버전에서 생긴 표)는 지운다. 그래야 업데이트 적용 때 다시 만들 수 있다.
        $like = $pdo->quote(str_replace(['_', '%'], ['\_', '\%'], $prefix) . '%');
        $now = $pdo->query('SHOW TABLES LIKE ' . $like)->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($now as $tn) {
            if (!in_array($tn, $inBackup, true) && !str_ends_with((string)$tn, 'login_attempts')) $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string)$tn) . '`');
        }
        run_dump_file($work, $prefix, $exec);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $e) {
        dm_log('error', '복원 실패, 이전 상태로 되돌림: ' . $e->getMessage());
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            run_dump_file($safety, $prefix, $exec);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $e2) {
            dm_log('error', '되돌리기도 실패: ' . $e2->getMessage() . ' (data/ 의 before-restore 파일로 직접 복원하세요)');
        }
        @unlink($work);
        json_fail('restore_failed', '복원하다가 문제가 생겨서 이전 상태로 되돌렸어요. 백업 파일이 손상되었을 수 있어요.', 500);
    }
    @unlink($work);

    // 사진 (이름이 규칙에 맞는 jpg 만)
    $photos = 0;
    $dir = photo_dir();
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if (!preg_match('#^photos/([a-f0-9]+(?:_t)?\.jpg)$#', $name, $pm)) continue;
        $stat = $zip->statIndex($i);
        if (!$stat || $stat['size'] > 8 * 1024 * 1024) continue;
        file_put_contents($dir . '/' . $pm[1], $zip->getFromIndex($i));
        $photos++;
    }
    $zip->close();

    // 사용자 표가 바뀌었으니 지금 로그인은 끝낸다.
    @audit('system.restore', null, null, count($inBackup) . '개 표, 사진 ' . $photos . '장');
    session_boot();
    $_SESSION = [];
    return ['tables' => count($inBackup), 'photos' => $photos, 'safetyFile' => basename($safety)];
});
