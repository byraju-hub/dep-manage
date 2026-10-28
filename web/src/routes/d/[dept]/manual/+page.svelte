<script lang="ts">
	import { terms } from '$lib/terms.svelte';
	import { can } from '$lib/session.svelte';

	let { data } = $props();

	const sections = $derived([
		{ id: 'start', title: '처음 시작하기' },
		{ id: 'roles', title: '누가 무엇을 할 수 있나요' },
		{ id: 'people', title: `${terms.student} · ${terms.teacher} 명단` },
		{ id: 'excel', title: '엑셀로 한꺼번에 넣고 내려받기' },
		{ id: 'attendance', title: '출석 체크와 출석률' },
		{ id: 'visit', title: `${terms.visit} 기록` },
		{ id: 'plan', title: '계획 · 공지 · 회의록' },
		{ id: 'settings', title: '설정' },
		{ id: 'year', title: '새해, 졸업, 부서 이동' },
		{ id: 'faq', title: '자주 묻는 질문' }
	]);
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>프로그램 매뉴얼</h1>
			<p class="sub">{data.dept.name} 관리 프로그램을 쓰는 방법을 화면 순서대로 정리했어요.</p>
		</div>
	</header>

	<div class="layout">
		<nav class="toc card" aria-label="목차">
			<b>목차</b>
			{#each sections as s (s.id)}<a href="#{s.id}">{s.title}</a>{/each}
		</nav>

		<div class="body">
			<section id="start" class="card box">
				<h2>처음 시작하기</h2>
				<ol>
					<li>받은 주소로 들어가 <b>아이디와 비밀번호</b>로 로그인해요. 처음 받은 임시 비밀번호는 로그인하면 바로 바꾸게 돼요.</li>
					<li>맨 처음 화면은 <b>대시보드</b>예요. 이번 주 출석, 앞으로의 일정, 챙겨야 할 {terms.visit}이 한눈에 보여요.</li>
					<li>어느 화면에 있든 <b>왼쪽 위의 교회 이름(로고)</b>을 누르면 대시보드로 돌아와요. (휴대폰은 맨 위 이름)</li>
					<li>여러 부서에 소속되어 있다면 왼쪽의 <b>부서</b> 칸을 눌러 다른 부서로 바꿀 수 있어요. 소속이 아닌 부서는 목록에 나오지 않아요.</li>
					<li>휴대폰에서는 아래쪽 탭을 쓰고, 나머지 메뉴는 <b>더보기</b>에 있어요.</li>
				</ol>
			</section>

			<section id="roles" class="card box">
				<h2>누가 무엇을 할 수 있나요</h2>
				<p>역할은 <b>부서마다 따로</b> 정해요. 같은 사람이 {terms.department} 교사이면서 다른 부서에서는 열람만 할 수도 있어요.</p>
				<div class="scroll">
					<table>
						<thead><tr><th>할 수 있는 일</th><th>열람</th><th>부서 교사</th><th>부서 관리자</th><th>교회 관리자</th></tr></thead>
						<tbody>
							<tr><td>명단·출석·일정 보기</td><td>○</td><td>○</td><td>○</td><td>○</td></tr>
							<tr><td>출석 체크, {terms.student} 정보 고치기</td><td>-</td><td>○</td><td>○</td><td>○</td></tr>
							<tr><td>{terms.visit} 기록 쓰기 (비공개 기록은 쓴 사람과 교역자만)</td><td>-</td><td>○</td><td>○</td><td>○</td></tr>
							<tr><td>{terms.student}·{terms.teacher} 추가·삭제, 엑셀 올리기</td><td>-</td><td>-</td><td>○</td><td>○</td></tr>
							<tr><td>설정, 승급, 부서 이동, 사용자 승인</td><td>-</td><td>-</td><td>○</td><td>○</td></tr>
							<tr><td>부서 추가, 교회 이름·로고, 백업, 업데이트</td><td>-</td><td>-</td><td>-</td><td>○</td></tr>
						</tbody>
					</table>
				</div>
				<p class="note">소속이 없는 부서(예: 중고등부 교사가 유초등부)는 주소를 직접 입력해도 열리지 않아요.</p>
			</section>

			<section id="people" class="card box">
				<h2>{terms.student} · {terms.teacher} 명단</h2>
				<ul>
					<li>왼쪽 메뉴의 <b>{terms.student} 명단</b>에서 <b>기본 · 상세 · {terms.class}별</b> 보기를 바꿔 볼 수 있어요. 이름을 검색하거나 {terms.grade}로 걸러낼 수 있어요.</li>
					<li><b>추가</b> 버튼으로 한 명씩 등록하고, 사진은 수정 화면에서 올려요. 사진은 올리면 자동으로 크기가 줄어요.</li>
					<li>이름 색은 {terms.grade}마다 달라서 한눈에 구분돼요.</li>
					<li>쉬거나 졸업한 {terms.student}은(는) 삭제하지 말고 상태를 <b>비활동</b>으로 바꿔 두세요. 기록이 남아요.</li>
					<li>부서마다 필요한 정보(알레르기, 카카오톡 등록 등)는 설정의 <b>추가 항목</b>에서 만들면 입력 화면에 나타나요.</li>
				</ul>
			</section>

			<section id="excel" class="card box">
				<h2>엑셀로 한꺼번에 넣고 내려받기</h2>
				<ol>
					<li>명단 화면의 <b>엑셀</b> 메뉴에서 <b>양식 내려받기</b>를 눌러요.</li>
					<li>양식에 채워 넣어요. 성별·상태 같은 칸은 <b>목록에서 고르는</b> 칸이에요.</li>
					<li><b>엑셀 올리기</b>로 파일을 고르면 미리보기가 나와요. 문제가 있는 줄은 이유와 함께 알려 주고, 문제없는 줄만 등록해요.</li>
					<li>이미 있는 이름(같은 생년월일)은 건너뛰어서 두 번 올려도 겹치지 않아요.</li>
					<li>같은 메뉴의 <b>명단 내려받기</b>로 현재 명단을 엑셀로 저장할 수 있어요.</li>
				</ol>
			</section>

			<section id="attendance" class="card box">
				<h2>출석 체크와 출석률</h2>
				<ul>
					<li><b>출석 체크</b>에서 날짜(기본은 이번 주일)를 고르고 이름을 눌러 <b>출석 · 온라인 · 결석</b>을 정해요. 저장을 눌러야 기록돼요.</li>
					<li>위쪽 탭에서 {terms.student}과(와) {terms.teacher}을(를) 번갈아 체크해요.</li>
					<li><b>출석률</b> 화면에서는 기간별 출석률, {terms.class}별 비교, 오래 빠진 사람을 볼 수 있어요. 온라인 참석도 출석으로 셈해요.</li>
				</ul>
			</section>

			<section id="visit" class="card box">
				<h2>{terms.visit} 기록</h2>
				<ul>
					<li>누구를, 언제, 어떤 방법(방문·전화·카톡 등)으로 만났는지와 나눈 이야기를 적어요. 여러 명을 한 번에 연결할 수 있어요.</li>
					<li><b>후속 조치</b>(다시 연락할 날짜)를 정해 두면 대시보드에 챙길 일로 떠요. 끝나면 완료로 표시해요.</li>
					<li>기록의 공개 범위는 <b>교역자만 · 부서 관리자까지 · 교사까지</b> 중에서 정해요. 민감한 이야기는 좁게 두세요. 열람 역할은 {terms.visit} 기록을 볼 수 없어요.</li>
				</ul>
			</section>

			<section id="plan" class="card box">
				<h2>계획 · 공지 · 회의록</h2>
				<ul>
					<li><b>연간 계획</b>: 달력에 예배·행사·수련회 등 일정을 넣어요. 종류마다 색이 달라요.</li>
					<li><b>사역 계획</b>: 올해의 목표와 월별 계획을 적어 둬요.</li>
					<li><b>공지사항</b>: 중요한 글은 위에 고정할 수 있어요. 교회 전체 공지는 모든 부서에 보여요.</li>
					<li><b>회의록</b>: 회의 날짜, 참석자, 내용을 남겨요.</li>
				</ul>
			</section>

			{#if can(data.dept.id, 'admin')}
				<section id="settings" class="card box">
					<h2>설정 <span class="badge tone-blue">관리자</span></h2>
					<ul>
						<li><b>부서 정보 · 용어</b>: 부서 이름, 담당 교역자, 그리고 화면에 쓰는 말({terms.student}·{terms.teacher}·{terms.class} 등)을 우리 교회 말로 바꿔요.</li>
						<li><b>{terms.grade} · {terms.class} 구조</b>: 학년 단계와 반을 만들고 고쳐요.</li>
						<li><b>글꼴</b>: 제목, 메뉴, 내용의 글꼴을 따로 고를 수 있어요.</li>
						<li><b>추가 항목</b>: 기본 항목 말고 우리 부서에만 필요한 입력 칸을 더해요. (최대 20개씩)</li>
						<li><b>사용자</b>: 가입 신청을 승인하고, 부서마다 역할을 정해요. 새 사람은 <b>초대 코드</b>를 만들어 알려 주면 스스로 가입해요.</li>
						<li><b>부서 관리 · 데이터</b> (교회 관리자): 부서 추가, 백업 내려받기, 업데이트 적용, 연습용 가상 데이터 지우기가 있어요.</li>
					</ul>
				</section>
			{:else}
				<section id="settings" class="card box">
					<h2>설정</h2>
					<p>설정은 부서 관리자와 교회 관리자만 열 수 있어요. 바꾸고 싶은 것이 있으면 부서 관리자에게 말씀해 주세요.</p>
				</section>
			{/if}

			<section id="year" class="card box">
				<h2>새해, 졸업, 부서 이동</h2>
				<ol>
					<li><b>{terms.grade} 승급</b> (설정): 새해에 한 번, 모든 {terms.student}의 {terms.grade}을(를) 한 단계씩 올려요. 마지막 단계는 졸업 처리돼요. 반영 전에 <b>미리보기</b>를 보여 주고, <b>되돌릴 수 없어서</b> 같은 해에는 한 번만 할 수 있어요. 하기 전에 백업을 받아 두세요.</li>
					<li><b>부서 이동 · 졸업</b> (설정): 졸업한 {terms.student}을(를) 상위 부서(예: 중고등부 → 청년부)로 옮겨요. 옮겨 갈 부서·{terms.grade}·상태를 고르고, 옮길 사람을 체크해요. 출석 기록은 따라가고, {terms.visit} 기록은 예전 부서에 남아서 새 부서에서는 보이지 않아요.</li>
				</ol>
			</section>

			<section id="faq" class="card box">
				<h2>자주 묻는 질문</h2>
				<dl>
					<dt>비밀번호를 잊었어요.</dt>
					<dd>부서 관리자에게 <b>비밀번호 초기화</b>를 부탁하세요. 임시 비밀번호를 받아 로그인한 뒤 바로 바꾸면 돼요.</dd>
					<dt>다른 부서가 보이지 않아요.</dt>
					<dd>소속된 부서만 보여요. 필요하면 그 부서 관리자나 교회 관리자에게 소속 추가를 요청하세요.</dd>
					<dt>실수로 지웠어요.</dt>
					<dd>삭제는 되돌릴 수 없어요. 그래서 쉬는 사람은 삭제 대신 상태를 바꾸는 것을 권해요. 교회 관리자는 정기적으로 <b>백업</b>을 내려받아 두세요.</dd>
					<dt>엑셀을 올렸는데 일부가 안 들어갔어요.</dt>
					<dd>미리보기에서 빨간 줄의 이유를 확인해 고친 뒤 다시 올리세요. 이미 들어간 사람은 건너뛰어요.</dd>
					<dt>휴대폰 화면에서 글자가 작아요.</dt>
					<dd>휴대폰 브라우저의 글자 크기 설정을 키우면 화면도 함께 커져요.</dd>
				</dl>
			</section>
		</div>
	</div>
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.layout { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 20px; align-items: start; }
	.toc { position: sticky; top: 16px; padding: 14px 16px; display: flex; flex-direction: column; gap: 6px; font-size: 14px; }
	.toc a { color: var(--ink-2); padding: 3px 0; }
	.toc a:hover { color: var(--accent); }
	.body { display: flex; flex-direction: column; gap: 14px; }
	.box { padding: 20px 24px; display: flex; flex-direction: column; gap: 10px; line-height: 1.7; scroll-margin-top: 16px; }
	h2 { font-size: 19px; font-weight: 700; }
	ol, ul { margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 6px; }
	.note { font-size: 13.5px; color: var(--muted); }
	.scroll { overflow-x: auto; }
	table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 560px; }
	th, td { padding: 8px; border-top: 1px solid var(--line-soft); text-align: center; }
	th:first-child, td:first-child { text-align: left; }
	th { font-size: 12.5px; color: var(--ink-2); font-weight: 600; border-top: 0; }
	dt { font-weight: 700; margin-top: 8px; }
	dd { margin: 2px 0 0; color: var(--ink-2); }

	@media (max-width: 900px) {
		.layout { grid-template-columns: 1fr; }
		.toc { position: static; }
	}
</style>
