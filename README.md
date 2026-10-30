# dep-manage
교회 부서 관리 시스템

영 · 유아부, 유치부, 유초등부, 중고등부, 청년부 등 교회 부서를 운영하는 교역자(목사, 전도사)와 교사를 위한 웹 프로그램입니다. cafe24와 같은 PHP + MariaDB 웹호스팅에 설치해서 사용하고, 무료로 배포합니다.

- 구조 설계: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- 디자인 기준(색·원칙): [docs/DESIGN.md](docs/DESIGN.md)
- 진행 계획: [PLAN.md](PLAN.md)
- 라이선스: [GNU AGPL-3.0](LICENSE)

## 지금 되는 것

- **설치 마법사** (`/install/`): DB 정보, 교회·부서, 관리자 계정을 입력하면 끝. 모든 표 이름에 접두어(기본 `dm_`)를 붙여 같은 DB의 다른 프로그램과 섞이지 않습니다. 체험용 샘플 데이터를 넣을 수 있고, 설정에서 샘플만 한 번에 지울 수 있습니다.
- **로그인과 권한**: 세션 로그인, 초대 코드로 가입 → 관리자 승인, 부서별 역할(교회 관리자·부서 관리자·부서 교사·열람, 한 사람이 여러 부서에 서로 다른 역할). 한 설치에서 여러 부서를 운영합니다. 개인정보는 서버에서 역할별로 걸러서 내려줍니다.
- **명단**: 학생·교사 명단(기본·상세·반별), 추가·수정·삭제, 사진 올리기(자동 축소), 엑셀 양식 내려받기 → 채워서 올리기, 현재 명단 엑셀로 내려받기.
- **출석**: 학생·교사 출석 체크(출석·온라인·결석), 출석률(기간별, 반별, 개인별, 엑셀).
- **심방**, **공지**, **연간 계획**(엑셀 붙여넣기), **사역 계획**, **회의록**, 대시보드(사역 캘린더·추이·장기 결석).
- **추가 항목**: 부서마다 입력 칸(알레르기, 카카오톡 등록 등)을 직접 만들어 학생·교사 입력 화면에 더하기.
- **부서 이동·졸업**, **부서 추가**(교회 관리자), 화면 안의 **프로그램 매뉴얼**.
- **설정**: 부서 정보·교회 이름과 로고(왼쪽 위·로그인 화면·브라우저 탭 아이콘), 부서별 용어, 학년·반 관리, 글꼴(타이틀·메뉴·내용), 학년 승급(미리보기 후 연 1회), 사용자·초대 코드, 백업 내려받기(DB + 사진 zip), 업데이트 적용.

## 구조

```
web/      SvelteKit 정적 앱 (adapter-static). 서버의 PHP API(/api/v1)와 이야기합니다.
server/   PHP API, 설치 마법사, 데이터베이스 구조(migrations). 사이트 폴더에 그대로 올립니다.
scripts/  FTP 배포 예시
docs/     설계·디자인 문서
```

## 설치해서 쓰기 (배포 zip)

[Releases](https://github.com/byraju-hub/dep-manage/releases)에서 `dep-manage-vX.Y.Z.zip` 을 받아 압축을 풀고, 안의 내용을 호스팅 사이트 폴더에 올린 뒤 `/install/` 을 열면 됩니다. 자세한 순서는 zip 안의 `설치가이드.pdf` (소스: `docs/install-guide.html`) 를 보세요.
zip 은 `v0.2.0` 같은 태그를 올리면 GitHub Actions 가 만들고, 내 컴퓨터에서는 `./scripts/build-release.sh` 로 만들 수 있습니다.

## 개발과 배포

```
cd web
npm install
npm run build                      # web/build 에 정적 파일 생성 (글꼴 정리 포함)
BASE_PATH=/dm npm run build        # 사이트를 서브폴더(/dm)에 올릴 때
```

`scripts/deploy-ftp.sh` 가 `server/` 와 `web/build` 를 FTP로 올립니다. 사이트 폴더에는 아래와 같은 `.htaccess` 가 필요합니다 (SPA 주소 처리).

```
RewriteEngine On
RewriteBase /dm/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.html [L]
```

서버 요구사항: PHP 8.1 이상 (pdo_mysql, mbstring, gd 권장), MariaDB/MySQL, Apache mod_rewrite.
데이터베이스 구조를 바꾸는 새 버전은 로그인한 교회 관리자가 설정 → 데이터 → **업데이트 적용**을 눌러 반영합니다.

---

**제작: [byraju](https://github.com/byraju-hub) / 문의 : [byraju@naver.com](mailto:byraju@naver.com)**
