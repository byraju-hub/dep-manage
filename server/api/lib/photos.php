<?php
declare(strict_types=1);

// 사진 저장. 올린 파일을 그대로 두지 않고 다시 그려서(JPEG, 긴 변 640px와 160px 두 장) 저장한다.
//  - 다시 그리면 사진 안에 숨은 위치 정보(EXIF)와 이상한 내용이 사라지고, 호스팅 용량도 아낀다.
//  - 파일 이름은 무작위이고, 웹에서 직접 열 수 없는 data/ 안에 둔다. 사진은 권한을 확인한 뒤 PHP 로만 내려준다.

const PHOTO_MAX_BYTES = 5 * 1024 * 1024;
const PHOTO_MAX_PIXELS = 30_000_000;

function photo_dir(): string
{
    $d = DM_DATA . '/uploads/photos';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d;
}

function photo_path_for(string $token, bool $thumb): string
{
    return photo_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $token) . ($thumb ? '_t' : '') . '.jpg';
}

function delete_photo(?string $token): void
{
    if (!$token) return;
    @unlink(photo_path_for($token, false));
    @unlink(photo_path_for($token, true));
}

/** 긴 변이 $max 를 넘지 않게 줄인다. 투명한 부분은 흰색으로 채운다. */
function photo_fit(GdImage $src, int $max): GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, $max / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $out;
}

/**
 * 업로드된 사진을 검사하고 저장한다.
 * @param array $file $_FILES['photo']
 * @return string 새 사진의 토큰(파일 이름의 앞부분)
 */
function store_photo(array $file): string
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) json_fail('too_large', '사진 용량이 너무 커요. 5MB 이하로 올려 주세요.', 413);
    if ($err !== UPLOAD_ERR_OK) json_fail('no_file', '사진 파일을 선택해 주세요.', 422);
    $tmp = (string)$file['tmp_name'];
    if (!is_uploaded_file($tmp)) json_fail('invalid', '올바른 업로드가 아닙니다.', 422);
    if ((int)$file['size'] > PHOTO_MAX_BYTES) json_fail('too_large', '사진 용량이 너무 커요. 5MB 이하로 올려 주세요.', 413);
    if (!extension_loaded('gd')) json_fail('no_gd', '이 서버에서는 사진 기능을 쓸 수 없어요. (GD 확장이 없어요)', 501);

    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        json_fail('bad_type', '이미지 파일(JPG, PNG, WEBP)만 올릴 수 있어요.', 422);
    }
    if ($info[0] * $info[1] > PHOTO_MAX_PIXELS) json_fail('too_large', '사진 해상도가 너무 커요. 줄여서 올려 주세요.', 413);

    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG => @imagecreatefrompng($tmp),
        default => @imagecreatefromwebp($tmp),
    };
    if (!$img) json_fail('bad_image', '사진을 읽을 수 없어요. 다른 파일로 시도해 주세요.', 422);

    // 휴대폰으로 찍은 사진의 방향 정보를 반영한다.
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $rot = match ((int)($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($rot !== 0) {
            $rotated = imagerotate($img, $rot, 0);
            if ($rotated) $img = $rotated;
        }
    }

    $token = bin2hex(random_bytes(12));
    $full = photo_fit($img, 640);
    $thumb = photo_fit($img, 160);
    $ok = imagejpeg($full, photo_path_for($token, false), 85) && imagejpeg($thumb, photo_path_for($token, true), 80);
    if (!$ok) {
        delete_photo($token);
        json_fail('save_failed', '사진을 저장하지 못했어요. 서버의 data 폴더 권한을 확인해 주세요.', 500);
    }
    return $token;
}
