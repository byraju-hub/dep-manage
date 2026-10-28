<?php
declare(strict_types=1);

// 설치 마법사. 브라우저에서 /install/ 에 들어와 DB 정보와 관리자 계정만 입력하면 설치가 끝난다.
// 설치가 끝나면 data/config.php 가 생기고, 그 뒤로 이 화면은 "이미 설치됨"만 보여준다.

define('DM_ROOT', dirname(__DIR__));
define('DM_DATA', DM_ROOT . '/data');
require DM_ROOT . '/api/lib/util.php';
require DM_ROOT . '/api/lib/migrate.php';
require DM_ROOT . '/api/lib/sample.php';

ini_set('display_errors', '0');
date_default_timezone_set('Asia/Seoul');
mb_internal_encoding('UTF-8');

$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')), '/'); // 예: /dm
session_name('dm_install');
session_set_cookie_params(['lifetime' => 0, 'path' => ($base === '' ? '' : $base) . '/install/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// 부서 종류별 기본 용어와 학년 단계 (화면의 presets.ts 와 같은 내용)
function presets(): array
{
    $t = DEFAULT_TERMS;
    return [
        'youth' => ['중고등부', '중1 ~ 고3', $t, ['예비 중1', '중1', '중2', '중3', '고1', '고2', '고3', '졸업']],
        'infant' => ['영아부', '0 ~ 3세', array_merge($t, ['department' => '영아부', 'student' => '아기', 'teacher' => '선생님', 'grade' => '나이', 'head' => '부서장']), ['0세', '1세', '2세', '3세']],
        'kinder' => ['유치부', '4 ~ 7세', array_merge($t, ['department' => '유치부', 'student' => '어린이', 'teacher' => '선생님', 'head' => '부서장']), ['4세', '5세', '6세', '7세']],
        'elementary' => ['유초등부', '초1 ~ 초6', array_merge($t, ['department' => '유초등부', 'student' => '어린이', 'teacher' => '선생님', 'head' => '부서장']), ['초1', '초2', '초3', '초4', '초5', '초6']],
        'teens' => ['청소년부', '용어가 다른 중고등부', array_merge($t, ['department' => '청소년부', 'student' => '청소년', 'teacher' => '선생님', 'class' => '소그룹', 'grade' => '기수', 'head' => '부서장', 'pastor' => '담당목사', 'visit' => '만남', 'newMember' => '새친구']), ['1기', '2기', '3기', '4기', '5기', '6기']],
        'young' => ['청년부', '대학 · 청년', array_merge($t, ['department' => '청년부', 'student' => '청년', 'teacher' => '리더', 'class' => '목장', 'grade' => '구분', 'head' => '회장', 'visit' => '돌봄', 'newMember' => '새친구']), ['대학 새내기', '대학생', '사회초년생']],
        'custom' => ['직접 설정', '설치 뒤에 만들기', $t, []],
    ];
}

function page(string $title, string $content, int $step = 0): never
{
    $steps = ['환경 점검', '데이터베이스', '교회 · 부서', '관리자 계정', '완료'];
    echo '<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>' . h($title) . ' · dep-manage 설치</title><style>
:root{--accent:#2563eb;--ink:#17202a;--muted:#5b6678;--line:#e1e7f5;--line2:#d5ddf0;--bg:#f3f6ff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.6 "Noto Sans KR","Malgun Gothic","Apple SD Gothic Neo",system-ui,sans-serif}
.wrap{max-width:760px;margin:0 auto;padding:32px 16px 64px}.brand{display:flex;align-items:center;gap:10px;margin-bottom:20px}
.logo{width:42px;height:42px;border-radius:13px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:19px}
.bn{font-size:17px;font-weight:700;line-height:1.2}.bs{font-size:12.5px;color:var(--muted)}
ol.steps{display:flex;gap:6px;list-style:none;margin:0 0 20px;padding:0;flex-wrap:wrap}
ol.steps li{padding:4px 12px;border-radius:999px;background:#e6ecfa;color:var(--muted);font-weight:600;font-size:12.5px}
ol.steps li.on{background:var(--accent);color:#fff}ol.steps li.done{background:#ddf5e8;color:#0f6b40}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px 26px}
h1{margin:0 0 4px;font-size:22px}p.sub{margin:0 0 16px;color:var(--muted)}
label{display:block;font-weight:700;font-size:13px;color:#475467;margin-bottom:4px}
input[type=text],input[type=password],select{width:100%;height:44px;border:1px solid var(--line2);border-radius:12px;padding:0 12px;font:inherit;background:#fff}
.row{margin-bottom:14px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.hint{font-size:12.5px;color:var(--muted);margin-top:4px}
.btn{display:inline-flex;align-items:center;justify-content:center;height:46px;padding:0 24px;border-radius:12px;border:1px solid var(--line2);background:#fff;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;color:var(--ink)}
.btn.p{background:var(--accent);border-color:transparent;color:#fff}.btn[disabled]{opacity:.5;cursor:not-allowed}
.actions{display:flex;justify-content:space-between;margin-top:20px;gap:10px}
.err{background:#ffe3ea;color:#be123c;border-radius:12px;padding:10px 14px;margin-bottom:14px;font-weight:600}
.ok{background:#ddf5e8;color:#0f6b40;border-radius:12px;padding:10px 14px;margin-bottom:14px;font-weight:600}
table.chk{width:100%;border-collapse:collapse}table.chk td{padding:8px 0;border-top:1px solid #edf1fa}table.chk td:last-child{text-align:right;font-weight:700}
.good{color:#0f6b40}.bad{color:#be123c}.warn{color:#a04a07}
.presets{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
.presets label{border:1px solid var(--line2);border-radius:14px;padding:10px 12px;cursor:pointer;margin:0;font-weight:600;color:var(--ink)}
.presets input{margin-right:6px}.presets small{display:block;font-weight:400;color:var(--muted)}
.box{border:1px solid #ffd9a8;background:#fff8ec;border-radius:12px;padding:12px 14px;margin:14px 0}
@media(max-width:600px){.grid{grid-template-columns:1fr}.card{padding:18px 16px}}
</style></head><body><div class="wrap"><div class="brand"><div class="logo">d</div><div><div class="bn">dep-manage</div><div class="bs">교회 부서 관리 · 설치</div></div></div>';
    if ($step > 0) {
        echo '<ol class="steps">';
        foreach ($steps as $i => $s) {
            $n = $i + 1;
            echo '<li class="' . ($n < $step ? 'done' : ($n === $step ? 'on' : '')) . '">' . $n . '. ' . h($s) . '</li>';
        }
        echo '</ol>';
    }
    echo '<div class="card">' . $content . '</div></div></body></html>';
    exit;
}

// ===== 이미 설치된 경우 =====
if (is_file(DM_DATA . '/config.php')) {
    page('이미 설치됨', '<h1>이미 설치되어 있어요</h1><p class="sub">이 프로그램은 설치가 끝났습니다. 다시 설치하려면 서버의 <b>data/config.php</b> 를 지운 뒤 이 화면을 다시 여세요. (데이터베이스의 자료는 지워지지 않습니다.)</p><p><a class="btn p" href="' . h($base) . '/">프로그램으로 가기</a></p>');
}

$S = &$_SESSION['inst'];
$S ??= ['step' => 1];
$S['csrf'] ??= bin2hex(random_bytes(16));
$error = '';

function csrf_field(): string { global $S; return '<input type="hidden" name="csrf" value="' . h($S['csrf']) . '">'; }

// ===== 환경 점검 =====
function checks(): array
{
    $c = [];
    $c[] = ['PHP 8.1 이상', PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>='), true];
    foreach (['pdo_mysql' => 'PDO MySQL (데이터베이스)', 'mbstring' => 'mbstring (한글 처리)', 'json' => 'JSON'] as $e => $label) {
        $c[] = [$label, extension_loaded($e) ? '사용 가능' : '없음', extension_loaded($e), true];
    }
    $c[] = ['GD (사진 줄이기)', extension_loaded('gd') ? '사용 가능' : '없음 (사진 기능을 쓸 수 없어요)', extension_loaded('gd'), false];
    $c[] = ['data 폴더에 쓰기', is_writable(DM_DATA) ? '가능' : '불가능 (폴더 권한을 707 또는 777로 바꿔 주세요)', is_writable(DM_DATA), true];
    $c[] = ['마이그레이션 파일', is_dir(DM_ROOT . '/migrations') && migration_files(DM_ROOT . '/migrations') ? '있음' : '없음', is_dir(DM_ROOT . '/migrations') && (bool)migration_files(DM_ROOT . '/migrations'), true];
    return $c;
}

function connect(array $d): PDO
{
    return new PDO('mysql:host=' . $d['host'] . ';dbname=' . $d['name'] . ';charset=utf8mb4', $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// ===== 입력 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($S['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = '화면이 오래되었어요. 새로고침한 뒤 다시 시도해 주세요.';
    } else {
        $act = (string)($_POST['act'] ?? '');

        if ($act === 'back') {
            $S['step'] = max(1, (int)$S['step'] - 1);
        } elseif ($act === 'check') {
            $blocked = array_filter(checks(), fn($c) => $c[3] && !$c[2]);
            if ($blocked) $error = '필수 항목을 먼저 해결해 주세요.';
            else $S['step'] = 2;
        } elseif ($act === 'db') {
            $d = ['host' => trim((string)($_POST['host'] ?? '')), 'name' => trim((string)($_POST['name'] ?? '')), 'user' => trim((string)($_POST['user'] ?? '')), 'pass' => (string)($_POST['pass'] ?? '')];
            $prefix = trim((string)($_POST['prefix'] ?? 'dm_'));
            $S['db_form'] = ['host' => $d['host'], 'name' => $d['name'], 'user' => $d['user'], 'prefix' => $prefix];
            if ($d['host'] === '' || $d['name'] === '' || $d['user'] === '') $error = '접속 주소, 데이터베이스 이름, 아이디를 모두 입력해 주세요.';
            elseif (!valid_prefix($prefix)) $error = '접두어는 영문 소문자로 시작하고 밑줄(_)로 끝나게 써 주세요. 예: dm_';
            else {
                try {
                    $pdo = connect($d);
                    if (prefix_in_use($pdo, $prefix)) {
                        $error = "이 데이터베이스에 '{$prefix}' 로 시작하는 표가 이미 있어요. 다른 접두어를 쓰거나, 예전 설치 흔적이라면 직접 지운 뒤 다시 시도해 주세요. (기존 자료를 보호하려고 자동으로 덮어쓰지 않아요.)";
                    } else {
                        $S['db'] = $d;
                        $S['prefix'] = $prefix;
                        $S['step'] = 3;
                    }
                } catch (Throwable $e) {
                    $error = '데이터베이스에 연결하지 못했어요. 입력한 정보를 다시 확인해 주세요. (' . h(preg_replace('/\s+/', ' ', mb_substr($e->getMessage(), 0, 120))) . ')';
                }
            }
        } elseif ($act === 'org') {
            $S['org'] = [
                'church' => trim((string)($_POST['church'] ?? '')), 'preset' => (string)($_POST['preset'] ?? 'youth'),
                'dept' => trim((string)($_POST['dept'] ?? '')), 'pastor' => trim((string)($_POST['pastor'] ?? '')),
                'head' => trim((string)($_POST['head'] ?? '')), 'sample' => !empty($_POST['sample']),
            ];
            if ($S['org']['church'] === '' || mb_strlen($S['org']['church']) > 50) $error = '교회 이름을 입력해 주세요. (50자 이하)';
            elseif (!isset(presets()[$S['org']['preset']])) $error = '부서 종류를 골라 주세요.';
            else {
                if ($S['org']['dept'] === '') $S['org']['dept'] = presets()[$S['org']['preset']][0];
                $S['step'] = 4;
            }
        } elseif ($act === 'install') {
            $login = trim((string)($_POST['login'] ?? ''));
            $name = trim((string)($_POST['aname'] ?? ''));
            $pw = (string)($_POST['pw'] ?? '');
            $S['admin_form'] = ['login' => $login, 'aname' => $name];
            if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $login)) $error = '아이디는 영문, 숫자, - _ . 으로 3자 이상 만들어 주세요.';
            elseif ($name === '' || mb_strlen($name) > 50) $error = '이름을 입력해 주세요.';
            elseif (mb_strlen($pw) < 8) $error = '비밀번호는 8자 이상으로 만들어 주세요.';
            elseif ($pw !== (string)($_POST['pw2'] ?? '')) $error = '비밀번호가 서로 일치하지 않아요.';
            else {
                $pdo = null;
                $created = false;
                try {
                    $p = $S['prefix'];
                    $pdo = connect($S['db']);
                    if (prefix_in_use($pdo, $p)) throw new RuntimeException('이미 같은 접두어의 표가 있어요. 2단계에서 다시 시도해 주세요.');
                    $created = true;
                    run_migrations($pdo, $p, DM_ROOT . '/migrations');

                    $org = $S['org'];
                    [$pname, , $terms, $grades] = presets()[$org['preset']];
                    $terms['department'] = $org['dept'];
                    $set = $pdo->prepare("INSERT INTO `{$p}settings` (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
                    $set->execute(['church_name', json_encode($org['church'], JSON_UNESCAPED_UNICODE)]);
                    $set->execute(['fonts', json_encode(['title' => 'noto-sans', 'menu' => 'noto-sans', 'body' => 'noto-sans'])]);
                    $set->execute(['installed_at', json_encode(date('c'))]);

                    $pdo->prepare("INSERT INTO `{$p}departments` (name, pastor_name, head_name, terms, sort) VALUES (?,?,?,?,1)")
                        ->execute([$org['dept'], $org['pastor'], $org['head'], json_encode($terms, JSON_UNESCAPED_UNICODE)]);
                    $deptId = (int)$pdo->lastInsertId();
                    $tones = ['중1' => 'blue', '중2' => 'green', '중3' => 'orange', '고1' => 'pink', '고2' => 'violet', '고3' => 'teal', '졸업' => 'slate'];
                    $cycle = ['blue', 'green', 'orange', 'pink', 'violet', 'teal', 'sky', 'amber'];
                    $ci = 0;
                    $prev = null;
                    $insG = $pdo->prepare("INSERT INTO `{$p}grade_levels` (department_id, label, tone, sort, is_graduate) VALUES (?,?,?,?,?)");
                    foreach ($grades as $i => $label) {
                        $grad = $label === '졸업';
                        $tone = $tones[$label] ?? ($grad ? 'slate' : (str_starts_with($label, '예비') ? null : $cycle[$ci++ % 8]));
                        $insG->execute([$deptId, $label, $tone, $i, $grad ? 1 : 0]);
                        $gid = (int)$pdo->lastInsertId();
                        if ($prev) $pdo->prepare("UPDATE `{$p}grade_levels` SET next_id = ? WHERE id = ?")->execute([$gid, $prev]);
                        $prev = $gid;
                    }

                    $pdo->prepare("INSERT INTO `{$p}users` (login_id, password_hash, name, is_owner, status) VALUES (?,?,?,1,'active')")
                        ->execute([$login, password_hash($pw, PASSWORD_DEFAULT), $name]);

                    if ($org['sample'] && $grades) {
                        sample_seed_department($pdo, $p, $deptId, true);
                        $young = sample_create_young_department($pdo, $p);
                        sample_seed_department($pdo, $p, $young);
                    }

                    // 마지막에 설정 파일을 만든다. 이 파일이 있어야 "설치됨"으로 본다.
                    $config = ['db' => $S['db'], 'prefix' => $p, 'secret' => bin2hex(random_bytes(32)), 'installed_at' => date('c')];
                    if (file_put_contents(DM_DATA . '/config.php', "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) {
                        throw new RuntimeException('data/config.php 를 만들지 못했어요. data 폴더의 쓰기 권한을 확인해 주세요.');
                    }
                    @chmod(DM_DATA . '/config.php', 0600);
                    $S['done'] = ['sample' => (bool)($org['sample'] && $grades)];
                    $S['step'] = 5;
                } catch (Throwable $e) {
                    // 이번 시도에서 만든 표만 정리해서 처음부터 다시 할 수 있게 한다.
                    if ($pdo && $created) {
                        try {
                            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                            foreach ($pdo->query('SHOW TABLES LIKE ' . $pdo->quote(str_replace('_', '\\_', $S['prefix']) . '%'))->fetchAll(PDO::FETCH_COLUMN) as $tbl) {
                                $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $tbl) . '`');
                            }
                            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                        } catch (Throwable) {
                        }
                    }
                    @file_put_contents(DM_DATA . '/install-error.log', date('c') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
                    $error = '설치하는 중 문제가 생겼어요: ' . h(preg_replace('/\s+/', ' ', mb_substr($e->getMessage(), 0, 200))) . ' (이번에 만든 표는 정리했어요. 다시 시도할 수 있어요.)';
                }
            }
        }
    }
}

$step = (int)$S['step'];
$err = $error !== '' ? '<div class="err" role="alert">' . $error . '</div>' : '';

// ===== 화면 =====
if ($step === 1) {
    $rows = '';
    $blocked = false;
    foreach (checks() as [$label, $value, $okk, $must]) {
        if ($must && !$okk) $blocked = true;
        $cls = $okk ? 'good' : ($must ? 'bad' : 'warn');
        $rows .= '<tr><td>' . h($label) . '</td><td class="' . $cls . '">' . h((string)$value) . ($okk ? ' ✓' : '') . '</td></tr>';
    }
    page('환경 점검', $err . '<h1>환경을 점검해요</h1><p class="sub">이 서버에서 프로그램이 동작할 수 있는지 확인합니다.</p><table class="chk">' . $rows . '</table>'
        . '<form method="post">' . csrf_field() . '<input type="hidden" name="act" value="check"><div class="actions"><span></span><button class="btn p" type="submit"' . ($blocked ? ' disabled' : '') . '>다음: 데이터베이스</button></div></form>', 1);
}

if ($step === 2) {
    $f = $S['db_form'] ?? ['host' => 'localhost', 'name' => '', 'user' => '', 'prefix' => 'dm_'];
    page('데이터베이스', $err . '<h1>데이터베이스를 연결해요</h1><p class="sub">호스팅의 MariaDB(MySQL) 접속 정보를 입력하세요. 카페24는 보통 접속 주소가 <b>localhost</b>이고, 데이터베이스 이름과 아이디가 계정 아이디와 같아요.</p>'
        . '<form method="post" autocomplete="off">' . csrf_field() . '<input type="hidden" name="act" value="db">'
        . '<div class="grid"><div class="row"><label for="host">접속 주소</label><input id="host" type="text" name="host" value="' . h($f['host']) . '"></div><div class="row"><label for="name">데이터베이스 이름</label><input id="name" type="text" name="name" value="' . h($f['name']) . '"></div></div>'
        . '<div class="grid"><div class="row"><label for="user">아이디</label><input id="user" type="text" name="user" value="' . h($f['user']) . '"></div><div class="row"><label for="pass">비밀번호</label><input id="pass" type="password" name="pass" autocomplete="new-password"></div></div>'
        . '<div class="row"><label for="prefix">표 이름 접두어</label><input id="prefix" type="text" name="prefix" value="' . h($f['prefix']) . '" style="max-width:200px"><div class="hint">이 데이터베이스에 다른 프로그램의 자료가 있어도 섞이지 않도록, 이 프로그램의 모든 표 이름 앞에 붙는 글자예요. 기본값 <b>dm_</b> 그대로 쓰면 돼요.</div></div>'
        . '<div class="actions"><button class="btn" type="submit" name="act" value="back" formnovalidate>이전</button><button class="btn p" type="submit">연결 확인하고 다음</button></div></form>', 2);
}

if ($step === 3) {
    $o = $S['org'] ?? ['church' => '', 'preset' => 'youth', 'dept' => '', 'pastor' => '', 'head' => '', 'sample' => false];
    $cards = '';
    foreach (presets() as $key => [$pname, $range]) {
        $cards .= '<label><input type="radio" name="preset" value="' . h($key) . '"' . ($o['preset'] === $key ? ' checked' : '') . '>' . h($pname) . '<small>' . h($range) . '</small></label>';
    }
    page('교회 · 부서', $err . '<h1>교회와 첫 부서를 정해요</h1><p class="sub">부서 종류를 고르면 학년 단계와 용어가 자동으로 채워져요. 나머지 부서는 설치한 뒤에 추가할 수 있어요.</p>'
        . '<form method="post">' . csrf_field() . '<input type="hidden" name="act" value="org">'
        . '<div class="row"><label for="church">교회 이름</label><input id="church" type="text" name="church" value="' . h($o['church']) . '" placeholder="예: 은혜교회"></div>'
        . '<div class="row"><label>부서 종류</label><div class="presets">' . $cards . '</div></div>'
        . '<div class="grid"><div class="row"><label for="dept">부서 이름</label><input id="dept" type="text" name="dept" value="' . h($o['dept']) . '" placeholder="비우면 부서 종류 이름을 써요"></div><div class="row"><label for="pastor">담당 교역자</label><input id="pastor" type="text" name="pastor" value="' . h($o['pastor']) . '" placeholder="예: 김은혜 목사"></div></div>'
        . '<div class="row"><label for="head">부장 · 부서장</label><input id="head" type="text" name="head" value="' . h($o['head']) . '" placeholder="예: 박성실 집사" style="max-width:320px"></div>'
        . '<div class="box"><label style="display:flex;gap:8px;align-items:flex-start;margin:0;color:var(--ink)"><input type="checkbox" name="sample" value="1"' . (!empty($o['sample']) ? ' checked' : '') . ' style="margin-top:4px"><span>샘플 데이터로 시작해 보기<div class="hint" style="font-weight:400">가상의 학생·교사·출석·심방 자료를 넣어서 화면을 눈으로 확인할 수 있어요. 나중에 설정 → 데이터에서 <b>샘플 데이터 삭제</b> 버튼으로 한 번에 지울 수 있고, 직접 입력한 자료는 그대로 남아요.</div></span></label></div>'
        . '<div class="actions"><button class="btn" type="submit" name="act" value="back" formnovalidate>이전</button><button class="btn p" type="submit">다음: 관리자 계정</button></div></form>', 3);
}

if ($step === 4) {
    $a = $S['admin_form'] ?? ['login' => '', 'aname' => ''];
    page('관리자 계정', $err . '<h1>관리자 계정을 만들어요</h1><p class="sub">교회 전체를 관리하는 첫 번째 계정이에요. 비밀번호는 8자 이상으로 만들어 주세요.</p>'
        . '<form method="post" autocomplete="off">' . csrf_field() . '<input type="hidden" name="act" value="install">'
        . '<div class="grid"><div class="row"><label for="aname">이름</label><input id="aname" type="text" name="aname" value="' . h($a['aname']) . '" placeholder="예: 김선생"></div><div class="row"><label for="login">아이디</label><input id="login" type="text" name="login" value="' . h($a['login']) . '" autocomplete="username"></div></div>'
        . '<div class="grid"><div class="row"><label for="pw">비밀번호</label><input id="pw" type="password" name="pw" autocomplete="new-password"></div><div class="row"><label for="pw2">비밀번호 확인</label><input id="pw2" type="password" name="pw2" autocomplete="new-password"></div></div>'
        . '<div class="actions"><button class="btn" type="submit" name="act" value="back" formnovalidate>이전</button><button class="btn p" type="submit">설치하기</button></div></form>', 4);
}

// 5단계: 완료
$sample = !empty($S['done']['sample']);
unset($_SESSION['inst']);
page('완료', '<h1>설치가 끝났어요</h1><div class="ok">데이터베이스 표를 만들고 관리자 계정을 등록했어요.</div>'
    . ($sample ? '<p class="sub">샘플 데이터가 들어 있어요. 설정 → 데이터에서 언제든 지울 수 있어요.</p>' : '')
    . '<p class="sub">이제 방금 만든 아이디와 비밀번호로 로그인하세요. 보안을 위해 이 설치 화면은 앞으로 열리지 않아요.</p>'
    . '<p><a class="btn p" href="' . h($base) . '/">로그인하러 가기</a></p>', 5);
