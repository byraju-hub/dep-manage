#!/usr/bin/env bash
# web/build 를 FTP로 서버의 하위 폴더에 올린다.
#
# 사용법:
#   FTP_CFG=~/.ftp-dm.cfg ./scripts/deploy-ftp.sh ftp://example.com/dm /dm
#
#   FTP_CFG  curl 설정 파일 (한 줄: user = "아이디:비밀번호"). 저장소에 넣지 않는다.
#   첫 번째 인자  올릴 FTP 주소
#   두 번째 인자  사이트 안에서의 경로(BASE_PATH). 루트에 설치하면 생략.
#
# .htaccess(로그인 보호 + SPA 주소 처리)는 서버마다 다르므로 이 스크립트가 만들지 않는다.
set -euo pipefail

: "${FTP_CFG:?FTP_CFG 에 curl 설정 파일 경로를 지정하세요}"
TARGET="${1:?올릴 FTP 주소를 지정하세요 (예: ftp://example.com/dm)}"
BASE="${2:-}"

cd "$(dirname "$0")/../web"
MSYS_NO_PATHCONV=1 BASE_PATH="$BASE" npm run build >/dev/null

cd build
count=0
while IFS= read -r f; do
	curl -sS -K "$FTP_CFG" --ftp-create-dirs -T "$f" "$TARGET/${f#./}"
	count=$((count + 1))
done < <(find . -type f | sort)
echo "올렸어요: ${count}개 파일 -> $TARGET"
