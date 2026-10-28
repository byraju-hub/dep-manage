#!/usr/bin/env bash
# 화면(web/build)과 서버 코드(server/)를 FTP로 사이트 폴더에 올린다.
#
# 사용법:
#   FTP_CFG=~/.ftp-dm.cfg ./scripts/deploy-ftp.sh ftp://example.com/dm /dm
#
#   FTP_CFG       curl 설정 파일 (한 줄: user = "아이디:비밀번호"). 저장소에 넣지 않는다.
#   첫 번째 인자  올릴 FTP 주소
#   두 번째 인자  사이트 안에서의 경로(BASE_PATH). 루트에 설치하면 생략.
#
# - 해시가 이름에 붙은 _app/immutable 파일은 서버에 같은 이름이 있으면 건너뛴다. (내용이 같다는 뜻)
# - data/config.php, data/ 안의 자료는 올리지도 지우지도 않는다. (설치 정보와 업로드 파일이 들어 있다.)
# - 사이트 폴더의 .htaccess(SPA 주소 처리)는 서버마다 달라서 이 스크립트가 만들지 않는다. README 의 예시를 참고한다.
set -euo pipefail

: "${FTP_CFG:?FTP_CFG 에 curl 설정 파일 경로를 지정하세요}"
TARGET="${1:?올릴 FTP 주소를 지정하세요 (예: ftp://example.com/dm)}"
BASE="${2:-}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

up=0
skip=0

# 서버 코드: 보호용 .htaccess 를 먼저 올린 뒤 나머지를 올린다. (data/config.php 는 소스에 없으므로 덮어쓰지 않는다.)
cd "$ROOT/server"
for f in data/.htaccess migrations/.htaccess api/.htaccess; do
	curl -sS -K "$FTP_CFG" --ftp-create-dirs -T "$f" "$TARGET/$f"
	up=$((up + 1))
done
while IFS= read -r f; do
	f="${f#./}"
	case "$f" in data/.htaccess | migrations/.htaccess | api/.htaccess) continue ;; esac
	curl -sS -K "$FTP_CFG" --ftp-create-dirs -T "$f" "$TARGET/$f"
	up=$((up + 1))
done < <(find . -type f | sort)

# 화면
cd "$ROOT/web"
MSYS_NO_PATHCONV=1 BASE_PATH="$BASE" npm run build >/dev/null
cd build
for d in $(find . -type d | sort); do
	dd="${d#.}"
	remote="$(curl -s -K "$FTP_CFG" "$TARGET$dd/" -l 2>/dev/null | tr -d '\r' || true)"
	for f in $(find "$d" -maxdepth 1 -type f | sort); do
		rel="${f#./}"
		case "$rel" in
			_app/immutable/*)
				if printf '%s\n' "$remote" | grep -qxF -- "$(basename "$f")"; then
					skip=$((skip + 1))
					continue
				fi
				;;
		esac
		curl -sS -K "$FTP_CFG" --ftp-create-dirs -T "$f" "$TARGET/$rel"
		up=$((up + 1))
	done
done

echo "올렸어요: ${up}개 파일 (이미 있어서 건너뜀 ${skip}개) -> $TARGET"
