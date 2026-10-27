# dep-manage 구조 설계 (초안 v0.1)

> 교회 부서(중고등부·청소년부 등) 관리 시스템. 목사님·부서 담당자에게 무상 배포한다.
> 기준: 운영 중인 `byraju-hub/youth67`(PHP + MariaDB)의 기능과 데이터 구조.
> 작성일: 2026-10-27

---

## 1. 목표와 원칙

| 원칙 | 내용 |
|---|---|
| 가장 싼 호스팅에서 동작 | 카페24 최저가 웹호스팅(PHP + MariaDB, Node 불가) 기준 |
| 터미널 없이 설치 | zip 업로드 → 브라우저에서 `/install` → 끝 |
| 교회마다 다른 용어 | 부서명, 학년/기수, 반, 교사 호칭 등을 설정으로 바꿀 수 있게 |
| 개인정보 우선 | 미성년자 정보와 심방 기록을 다루므로 **로그인 필수**, 최소 권한이 기본값 |
| 업그레이드 안전 | 새 버전을 덮어써도 설정·사진·데이터가 남아 있게 |

### youth67에서 바꾸는 점

| youth67 | dep-manage |
|---|---|
| 학년이 `ENUM('초6','중1',…)`으로 고정 | `grade_levels` 테이블로 설정 (청년부·유초등부도 가능) |
| 반 이름 규칙이 코드에 하드코딩 (`고등A`, `중1A`) | `classes` 테이블, 연도별로 편성 |
| 세례 구분이 ENUM | 설정 가능한 선택 목록 |
| `has_kakao`, `attended_retreat` 같은 교회 고유 컬럼 | **사용자 정의 항목**(custom fields)으로 일반화 |
| 권한 A/B/pending, 최고관리자가 아이디(`byraju`)로 고정 | 부서별 역할(owner/admin/teacher/viewer) |
| 비로그인 사용자에게 이름 마스킹해서 공개 | 기본은 로그인 필수 (공개 모드는 나중에 검토) |
| 부모 이름이 `father_name`/`mother_name` 고정 | `guardians` 테이블 (한부모·조부모 등 대응) |
| 출석이 출석/결석 두 가지 | 출석/결석/지각/온라인/사유결석 + 메모 |

그대로 가져가는 것: 세션 쿠키 인증, CSRF 토큰, 로그인 시도 제한, 감사 로그, 개인정보 변경 시 값을 남기지 않는 이력 방식, 전화번호 정규화, 학년 일괄 승급(미리보기 후 적용), 음력 생일 변환표.

---

## 2. 전체 구조

```
[브라우저]  Svelte SPA (빌드된 정적 파일)
    │  fetch /api/...  (같은 도메인, 세션 쿠키 + X-CSRF-Token)
    ▼
[PHP 8.x]   api/index.php  ← 단일 진입점, 라우터 → 컨트롤러 → PDO
    │
    ▼
[MariaDB]   utf8mb4
```

- **프론트엔드**: SvelteKit + `adapter-static` (SPA 모드, `fallback: index.html`), Svelte 5, TypeScript
- **백엔드**: 프레임워크 없는 PHP 8.x. 작은 라우터와 PDO만 사용. 외부 라이브러리는 단일 파일 수준만(엑셀 내보내기 등)
- **인증**: PHP 세션 쿠키(HttpOnly, SameSite=Lax, HTTPS면 Secure). 같은 도메인이라 JWT 불필요
- **설치 마법사**: SPA와 별개로 PHP가 직접 렌더링하는 `/install` (DB가 아직 없어도 동작해야 하므로)

### 왜 이 구조인가
- 카페24 최저가는 Node 서버를 띄울 수 없으므로 SSR 불가 → 정적 빌드 + PHP API
- 사용자는 빌드할 필요가 없음 → GitHub Release에 빌드 완료 zip 제공

---

## 3. 레포지토리 구조

```
dep-manage/
├─ web/                     # SvelteKit 소스
│  ├─ src/
│  │  ├─ lib/
│  │  │  ├─ api/            # fetch 래퍼, 타입
│  │  │  ├─ stores/         # 로그인 사용자, 설정, 용어
│  │  │  ├─ components/     # 공통 UI
│  │  │  └─ terms.ts        # 용어 치환 헬퍼  t('student') → "학생" / "청소년"
│  │  └─ routes/            # 화면 (5장 참고)
│  └─ svelte.config.js
├─ server/                  # 배포 시 그대로 복사되는 PHP
│  ├─ api/
│  │  ├─ index.php          # 진입점
│  │  ├─ routes.php         # URL → 컨트롤러 매핑
│  │  ├─ controllers/       # Students.php, Attendance.php, Visits.php ...
│  │  └─ lib/               # db.php, auth.php, csrf.php, audit.php, validate.php
│  ├─ install/              # 설치 마법사
│  ├─ migrations/           # 001_init.sql, 002_xxx.sql ...
│  └─ data/                 # 배포 시 빈 폴더 (config, uploads, backups 저장소)
├─ docs/
├─ scripts/build-release.*  # web 빌드 + server 복사 → zip
└─ .github/workflows/release.yml   # 태그 푸시 시 zip을 Release에 첨부
```

### 배포 zip을 풀었을 때 (사용자 서버의 `www/`)

```
www/
├─ index.html, _app/        # Svelte 빌드 결과
├─ api/                     # PHP API
├─ install/                 # 설치 후 install.lock 있으면 접근 차단
├─ data/                    # ⚠ 웹 접근 전체 차단 (.htaccess: Require all denied)
│  ├─ config.php            # 설치 시 생성 (DB 정보, 앱 비밀키)
│  ├─ uploads/photos/       # 사진은 PHP를 통해서만 내려줌 (권한 확인 후)
│  └─ backups/
└─ .htaccess                # /api, /install 외 경로 → index.html
```

- 업그레이드 = `data/` 빼고 덮어쓰기. 관리자가 로그인하면 미적용 마이그레이션을 감지해서 "업데이트 적용" 버튼을 보여줌
- 사진을 `data/` 안에 두고 PHP로 내려주면 **로그인하지 않은 사람이 사진 URL로 직접 접근할 수 없음** (youth67보다 안전)
- 가능하면 `data/`를 웹 루트 밖(카페24 홈 디렉터리)에 두는 옵션도 설치 마법사에서 제공 (호스팅 동작 확인 필요)

---

## 4. 데이터베이스 스키마

모든 업무 테이블은 `department_id`를 가진다. **v1 화면은 부서 하나만 다루지만, 스키마는 처음부터 여러 부서를 지원**한다 (나중에 추가하면 모든 쿼리를 고쳐야 하므로).

### 4.1 시스템·설정

| 테이블 | 주요 컬럼 | 설명 |
|---|---|---|
| `app_settings` | `key`, `value`(JSON) | 교회명, 시간대, 스키마 버전, 활성 모듈 |
| `departments` | `id`, `name`, `short_name`, `sort`, `is_active`, `settings`(JSON) | 부서. `settings`에 용어·옵션 저장 |
| `grade_levels` | `id`, `department_id`, `label`, `sort`, `next_id`, `is_graduate` | 학년 단계 (예비중1 → 중1 → … → 고3 → 졸업). `next_id`로 승급 경로 정의 |
| `option_lists` | `id`, `department_id`, `list_key`, `value`, `sort` | 세례 구분, 직분, 임원 등 선택 목록 |
| `custom_field_defs` | `id`, `department_id`, `entity`(student/teacher), `key`, `label`, `type`(bool/text/select/date), `options`, `sort`, `show_in_list` | 교회별 추가 항목 (카톡 등록, 수련회 참석 등) |

#### 용어 설정 (`departments.settings.terms`)

| 키 | 기본값 | 다른 교회 예시 |
|---|---|---|
| `department` | 중고등부 | 청소년부, 중등부, 학생회 |
| `student` | 학생 | 청소년, 친구들 |
| `teacher` | 교사 | 선생님, 리더, 섬기미 |
| `class` | 반 | 소그룹, 셀, 목장 |
| `grade` | 학년 | 기수 |
| `cohort_format` | `{n}기` | `{year}학번` |
| `head` | 부장 | 부서장 |
| `pastor` | 교역자 | 담당목사, 전도사 |
| `visit` | 심방 | 만남, 케어 |
| `new_member` | 새가족 | 새친구 |
| `attendance` | 출석 | — |

프론트엔드는 화면 문구를 하드코딩하지 않고 `t('student')`처럼 용어 키로 출력한다.

### 4.2 사용자·권한

| 테이블 | 주요 컬럼 | 설명 |
|---|---|---|
| `users` | `id`, `login_id`, `password_hash`, `name`, `phone`, `email`, `status`(pending/active/disabled), `must_change_password`, `last_login_at` | 로그인 계정 |
| `memberships` | `user_id`, `department_id`, `role`, `teacher_id` | 부서별 역할. `teacher_id`로 교사 명단과 연결 |
| `invites` | `code`, `department_id`, `role`, `expires_at`, `max_uses`, `used_count`, `created_by` | 초대 코드로만 가입 (공개 가입 스팸 방지) |
| `login_attempts` | `ip`, `login_id`, `attempted_at` | 15분 내 실패 횟수 제한 |
| `audit_log` | `user_id`, `action`, `target_type`, `target_id`, `detail`, `ip`, `created_at` | 변경 이력 |

**역할**

| 역할 | 누구 | 할 수 있는 것 |
|---|---|---|
| `owner` | 설치한 사람 (보통 교역자) | 전부 + 다른 owner 지정, 백업/복원, 업데이트 적용 |
| `admin` | 교역자, 부장 | 설정, 사용자 승인/권한, 모든 명단·심방·계획 편집 |
| `teacher` | 담임 교사 | 출석 체크, 명단 열람, **담당 반** 학생 정보 수정, 본인이 쓴 심방 기록 |
| `viewer` | 임원·참관 | 명단 기본 보기, 계획·공지 열람 (연락처 등 민감정보 제외) |

> 담당 반만 보이게 할지(교사 열람 범위)는 부서 설정 옵션으로 둔다: `teacher_scope = all | own_class`

### 4.3 사람

| 테이블 | 주요 컬럼 |
|---|---|
| `students` | `department_id`, `name`, `gender`, `birth_date`, `birth_calendar`(양/음), `phone`, `school`, `grade_level_id`, `cohort`(기수 번호, 선택), `status`(active/new/inactive/graduated/transferred), `registered_at`, `baptism`, `mentor`(인도자), `dream`, `address`, `photo_path`, `custom`(JSON), `notes` |
| `guardians` | `student_id`, `relation`(부/모/조부모/기타), `name`, `phone`, `is_primary` |
| `teachers` | `department_id`, `user_id`(nullable), `name`, `position`(직분), `officer_role`(임원), `phone`, `birth_date`, `birth_calendar`, `status`, `joined_at`, `photo_path`, `custom`(JSON), `notes` |
| `classes` | `department_id`, `year`, `name`, `sort` |
| `class_students` | `class_id`, `student_id` |
| `class_teachers` | `class_id`, `teacher_id`, `role`(담임/부담임) |
| `grade_promotion_log` | `department_id`, `year`, `applied_by`, `applied_at` |
| `lunar_solar_map` | 음력 → 양력 변환표 (설치 시 2000~2060년 데이터 포함) |

- 반 편성을 **연도별**로 두어 작년 반 기록이 남고, 새해 반 편성은 "작년 편성 복사 후 수정"으로 처리
- 학년 승급은 youth67처럼 **미리보기 → 적용** 방식, `grade_levels.next_id`를 따라감

### 4.4 출석

| 테이블 | 주요 컬럼 |
|---|---|
| `student_attendance` | `student_id`, `date`, `status`(present/absent/late/online/excused), `note`, `checked_by`, UNIQUE(`student_id`,`date`) |
| `teacher_attendance` | `teacher_id`, `date`, `status`, `note`, UNIQUE(`teacher_id`,`date`) |

- 기본 출석일은 일요일. 부서 설정에서 요일 변경 가능 (토요 모임 등)
- **장기 결석 알림**: 연속 N주 결석(기본 3주)이면 대시보드에 표시, 바로 심방 등록으로 연결

### 4.5 심방 (신규)

| 테이블 | 주요 컬럼 |
|---|---|
| `visits` | `department_id`, `visit_date`, `method`(가정/전화/문자·카톡/학교/카페/교회/온라인), `title`, `content`, `prayer_requests`, `follow_up`, `follow_up_due`, `follow_up_done`, `visibility`, `author_user_id` |
| `visit_targets` | `visit_id`, `target_type`(student/teacher), `target_id` — 한 번에 여러 명 가능 |
| `visit_companions` | `visit_id`, `teacher_id` — 함께 간 사람 |

**열람 범위(`visibility`)**

| 값 | 볼 수 있는 사람 |
|---|---|
| `private` | 작성자 + owner |
| `pastors` (기본값) | 작성자 + admin 이상 |
| `staff` | 해당 부서 교사 전체 |

- 심방 내용은 가장 민감한 정보이므로 목록 화면에는 제목·날짜·대상만, 본문은 권한 확인 후 상세에서만
- 학생/교사 상세 화면에 "심방 기록" 탭, 후속 조치가 남은 건은 대시보드에 표시

### 4.6 계획·소통

| 테이블 | 주요 컬럼 | 설명 |
|---|---|---|
| `events` | `department_id`, `start_date`, `end_date`, `title`, `category`(예배/공과/행사/수련회/회의/기타), `prayer`(기도자), `owner`, `notes` | **연간 계획** (youth67 `year_plan` 확장, 여러 날 행사 지원) |
| `ministry_plans` | `department_id`, `year`, `theme`, `theme_verse`, `vision`, `goals`, `status`(draft/final) | **사역 계획** (연도별 1건) |
| `ministry_plan_items` | `plan_id`, `area`(예배/교육/전도/양육/교제/봉사), `title`, `description`, `period`, `owner`, `budget`, `status`, `sort` | 사역 계획 세부 항목 |
| `notices` | `department_id`, `title`, `body`, `is_pinned`, `audience`(staff/admin), `publish_at`, `author_user_id` | **공지사항** |
| `meeting_notes` | `department_id`, `meeting_date`, `title`, `content`, `attendees`, `author_user_id` | **회의록** (youth67 서기 노트) |

### 4.7 추가 기능 (모듈로 켜고 끄기)

| 모듈 | 내용 | 테이블 |
|---|---|---|
| 생일 | 이번 주/이번 달 생일자 (음력 자동 변환) | 없음 (계산) |
| 새가족 정착 | 첫 방문 → 2주 출석 → 심방 → 등록 단계 체크 | `newcomer_steps` |
| 행사 참가 관리 | 수련회 등 참가 신청, 회비 납부 여부 | `event_participants` |
| 기도 제목 | 학생·교사별 기도 제목 (심방과 연결) | `prayer_requests` |
| 가져오기/내보내기 | 엑셀(CSV/XLSX)로 명단 가져오기·내보내기 | 없음 |
| 백업 | DB 전체를 파일로 내려받기 / 복원 (owner만) | 없음 |

---

## 5. 화면 구성

```
/login                      로그인
/join?code=XXXX             초대 코드로 가입 → 승인 대기
/                           대시보드
/students                   명단 - 기본 보기 (이름·학년·반·상태)
/students?view=full         명단 - 상세(전체) 보기 (모든 항목, 가로 스크롤 표)
/students?view=class        명단 - 반별 보기 (반 카드 + 담임 교사)
/students/new, /students/:id, /students/:id/edit
/teachers  (위와 같은 3가지 보기)
/attendance                 출석 체크 (날짜·반 필터, 터치 토글)
/attendance/stats           출석 통계 (주별 추이, 개인별 출석률, 반별 비교)
/visits                     심방 목록 (대상·기간·방법 필터, 후속조치 필터)
/visits/new, /visits/:id
/plans/calendar             연간 계획 (달력 + 목록, 엑셀 일괄 입력)
/plans/ministry             사역 계획 (연도별)
/notices, /notices/:id      공지사항
/meetings                   회의록
/settings/church            교회·부서 정보, 담당자(교역자/부장)
/settings/terms             용어 설정 (미리보기 포함)
/settings/structure         학년 단계, 반 편성, 선택 목록, 사용자 정의 항목
/settings/users             사용자 승인·역할, 초대 코드
/settings/data              가져오기·내보내기, 백업, 업데이트
/me                         내 정보, 비밀번호 변경
```

### 대시보드에 보여줄 것
- 지난 주일 / 이번 주일 출석 인원 (학생·교사)
- 이번 주 생일자
- 장기 결석자 (연속 N주)
- 후속 조치가 남은 심방
- 다가오는 일정 (연간 계획)
- 고정 공지

### 모바일 우선
교사들은 주로 주일에 휴대폰으로 출석 체크를 하므로 **출석·명단·심방 작성은 모바일 화면 우선**으로 설계하고, 상세(전체) 명단 표와 통계는 PC 우선.

---

## 6. API 설계

- 기본 경로: `/api/v1/...`, 요청·응답 모두 JSON
- 성공: `{ "ok": true, "data": ... }` / 실패: `{ "ok": false, "error": { "code": "...", "message": "..." } }`
- 변경 요청(POST/PUT/PATCH/DELETE)은 `X-CSRF-Token` 헤더 필수
- 모든 쿼리는 prepared statement, 모든 엔드포인트는 서버에서 권한 확인 (화면에서 숨기는 것만으로는 안 됨)
- 민감 필드(연락처, 주소, 보호자, 생일)는 **역할에 따라 서버에서 빼고** 내려줌

| 영역 | 엔드포인트 |
|---|---|
| 인증 | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`, `POST /auth/join`, `POST /auth/password` |
| 설정 | `GET/PUT /settings`, `GET/PUT /departments/:id`, `GET/PUT /terms`, `CRUD /grade-levels`, `CRUD /option-lists`, `CRUD /custom-fields` |
| 사용자 | `GET /users`, `PATCH /users/:id` (승인·역할), `POST /users/:id/reset-password`, `CRUD /invites` |
| 학생 | `GET /students?view=&grade=&class=&status=&q=`, `GET/POST/PUT/DELETE /students/:id`, `POST /students/:id/photo`, `GET /students/export` |
| 교사 | 학생과 동일 구조 |
| 반 | `GET /classes?year=`, `CRUD /classes`, `PUT /classes/:id/members`, `POST /classes/copy-year` |
| 승급 | `GET /promotions/preview?year=`, `POST /promotions/apply` |
| 출석 | `GET /attendance?date=&class=`, `PUT /attendance` (한 명 토글), `GET /attendance/stats?from=&to=` |
| 심방 | `GET /visits?target=&from=&to=&pending=1`, `CRUD /visits` |
| 계획 | `CRUD /events`, `POST /events/bulk`, `GET/PUT /ministry-plans/:year`, `CRUD /ministry-plans/:year/items` |
| 소통 | `CRUD /notices`, `CRUD /meetings` |
| 대시보드 | `GET /dashboard` (위 6가지를 한 번에) |
| 데이터 | `POST /import/students`, `GET /backup`, `POST /restore`, `GET /system/migrations`, `POST /system/migrate` |
| 파일 | `GET /files/photos/:id` (권한 확인 후 이미지 전송) |

---

## 7. 설치 마법사 (`/install`)

1. **환경 점검**: PHP 버전(8.1+), PDO MySQL, GD(사진 축소), mbstring, `data/` 쓰기 권한
2. **DB 연결**: 호스트·DB명·아이디·비밀번호 입력 → 접속 테스트 (카페24는 DB명 = 아이디인 경우가 많아 안내 문구 제공)
3. **테이블 생성**: `migrations/` 순서대로 실행
4. **교회·부서 정보**: 교회 이름, 부서 이름, 담당 교역자, 부장(부서장)
5. **용어·구조 프리셋 선택**: "중고등부(중1~고3)", "청소년부", "유초등부", "청년부", "직접 설정" → 학년 단계와 용어가 자동으로 채워짐
6. **관리자 계정 생성**: owner 계정 (비밀번호 8자 이상)
7. **완료**: `data/config.php`, `data/install.lock` 생성 → 이후 `/install` 접근 차단

---

## 8. 보안 체크리스트

- [ ] 로그인하지 않으면 API 전체가 401 (설치·로그인·가입만 예외)
- [ ] 비밀번호 `password_hash` (bcrypt/argon2), 최소 8자
- [ ] 로그인 실패 제한 (IP + 아이디 기준, 15분)
- [ ] 세션 고정 방지 (로그인 시 `session_regenerate_id`)
- [ ] CSRF 토큰 (모든 변경 요청)
- [ ] `data/`, `migrations/` 웹 접근 차단
- [ ] 사진은 PHP를 통해서만, 업로드 시 MIME 검사 + 자동 축소(긴 변 800px) + 파일명 무작위화
- [ ] 역할별 민감 필드 서버 측 제거
- [ ] 심방 기록 열람 범위 서버 측 검사
- [ ] 감사 로그: 개인정보 변경은 "변경"만 기록, 값은 남기지 않음
- [ ] 출력은 Svelte 기본 이스케이프 사용, `{@html}` 사용 금지 (공지 본문은 마크다운 → 안전한 렌더러)
- [ ] 설치 후 `/install` 잠금

---

## 9. 개발 환경

- 로컬: PHP 8.x 내장 서버(`php -S localhost:8000 -t server`) + MariaDB(또는 XAMPP)
- Vite 개발 서버에서 `/api`를 PHP 서버로 프록시
- 빌드: `web` 빌드 결과 + `server/` → `dist/` → zip
- 릴리스: `v0.1.0` 태그 푸시 → GitHub Actions가 zip을 Release에 첨부

---

## 10. 개발 단계

| 단계 | 내용 | 완료 기준 |
|---|---|---|
| M0 | 레포 구성, 로컬 개발 환경, 빌드·릴리스 스크립트 | 빈 화면 zip이 카페24에 올라가 뜸 |
| M1 | 설치 마법사, 로그인·초대 가입·승인, 기본 설정(교회·부서·용어·학년·반) | 새 서버에 설치하고 로그인까지 |
| M2 | 학생·교사 명단 (기본/상세/반별), 상세·편집, 사진, 사용자 정의 항목 | youth67 명단 기능 대체 |
| M3 | 출석 체크, 출석 통계, 대시보드, 생일, 장기 결석 | youth67 출석 기능 대체 |
| M4 | 심방 | 신규 |
| M5 | 연간 계획, 사역 계획, 공지, 회의록 | youth67 계획·서기 노트 대체 |
| M6 | 가져오기·내보내기, 백업·복원, 학년 승급, 업데이트 적용 | 1년 운영 가능 |
| M7 | 설치 가이드(스크린샷), 베타 배포 (본 교회 → 2~3개 교회) | v1.0 |

각 단계는 Sonnet으로 구현 → Opus로 diff 검토(8장 보안 체크리스트 기준) → 수정 순서로 진행.

---

## 11. 결정이 필요한 것

1. **한 설치에 여러 부서?** — 제안: 스키마는 다부서 지원, v1 화면은 단일 부서. (교회 전체가 한 설치로 여러 부서를 운영하는 건 v2)
2. **비로그인 열람(이름 마스킹 공개) 기능 유지?** — 제안: 제거. 로그인 필수
3. **교사의 열람 범위 기본값** — 제안: 전체 명단 열람 가능, 수정은 담당 반만
4. **심방 기록 기본 열람 범위** — 제안: `pastors` (작성자 + 교역자·부장)
5. **라이선스** — 제안: MIT
6. **추가 모듈 중 v1에 넣을 것** — 제안: 생일, 장기 결석, 가져오기/내보내기, 백업은 v1. 새가족 정착·행사 참가·기도 제목은 v1.x
