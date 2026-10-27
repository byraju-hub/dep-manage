# 화면 설계 (design)

디자인 캔버스에서 만든 화면 설계 원본입니다. 코딩할 때 보는 기준이고, 실제 구현은 `web/` 에 있습니다.

| 파일 | 화면 |
|---|---|
| `Main.dc.html` | 대시보드 (사역 캘린더 포함) |
| `Attendance.dc.html` | 출석 체크 (모바일) |
| `Students.dc.html` | 학생 명단 · 기본 보기 |
| `StudentsClass.dc.html` | 학생 명단 · 반별 보기 |
| `Visits.dc.html` | 심방 |
| `Terms.dc.html` | 설정 · 용어 |
| `Install.dc.html` | 설치 마법사 |
| `Login.dc.html` | 로그인 |
| `canvas.json` | 캔버스에서의 화면 배치 |

`.dc.html` 은 디자인 캔버스 전용 형식이라 브라우저에서 바로 열리지 않습니다.
색·간격 기준은 `web/src/app.css` 의 디자인 토큰으로 옮겨 두었습니다.

## 색

- 포인트: `#2563EB`
- 톤(배경/글자): blue `#E3ECFF/#1D4ED8`, green `#DDF5E8/#0F7B4A`, violet `#ECE5FF/#6D28D9`, sky `#DAF1FC/#0369A1`,
  pink `#FFE3EE/#BE185D`, orange `#FFEBD0/#A04A07`, teal `#D9F5F1/#0F766E`, amber `#FEF3C7/#92400E`, slate `#E8ECF3/#475467`
- 학년 색: 중1 파랑, 중2 초록, 중3 주황, 고1 분홍, 고2 보라, 고3 청록
- 캘린더 일정 5종: 사역 일정(파랑) · 생일(분홍) · 심방(초록) · 마감·후속(주황) · 회의(보라)
