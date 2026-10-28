# dep-manage
교회 부서 관리 시스템

중고등부·청소년부·유초등부·청년부 등 교회 부서의 학생·교사 명단, 출석, 심방, 연간·사역 계획, 공지를 관리하는 웹 프로그램입니다.
카페24 같은 저가 PHP + MariaDB 웹호스팅에서 설치해 쓸 수 있도록 만들고, 무상으로 배포합니다.

- 구조 설계: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- 디자인 기준(색·원칙): [docs/DESIGN.md](docs/DESIGN.md)
- 진행 계획: [PLAN.md](PLAN.md)
- 라이선스: [GNU AGPL-3.0](LICENSE)

## 현재 상태

화면(SvelteKit)을 **가상 데이터**로 먼저 만들고 있습니다. 로그인·출석·심방·용어 설정 등을 눌러 볼 수 있지만,
바꾼 내용은 브라우저에만 저장됩니다. PHP + MariaDB API는 다음 단계에서 붙입니다.

만든 화면: 로그인, 설치 마법사(미리보기), 대시보드(사역 캘린더), 학생 명단(기본·상세·반별)과 상세, 교사 명단,
출석 체크(출석·온라인·결석), 심방, 공지사항, 설정(부서 정보·용어·학년 구조).
아직 없는 화면: 연간 계획, 사역 계획, 회의록, 사용자 관리.

## 개발

```
cd web
npm install
npm run dev        # http://localhost:5173  (아무 아이디·비밀번호로 로그인)
npm run build      # web/build 에 정적 파일 생성
```

서브폴더에 올릴 때는 `BASE_PATH=/dm npm run build` 로 빌드합니다. `scripts/deploy-ftp.sh` 는 빌드 후 FTP로 올리는 예시입니다.

## 구조

```
web/src/lib/api.ts       데이터 접근 계층. 지금은 가상 데이터, 나중에 PHP JSON API 호출로 교체
web/src/lib/mock/        가상 데이터 생성기 (실제 교회 정보 없음, 날짜는 오늘 기준)
web/src/lib/terms.*      부서별 용어(학생/교사/반/심방 등) — 화면 문구는 여기서 불러 씀
web/src/routes/d/[dept]  부서별 화면 (한 설치에서 여러 부서 운영)
```
