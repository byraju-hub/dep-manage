<script lang="ts">
	import { session } from '$lib/session.svelte';
	import { url } from '$lib/url';

	let { data } = $props();

	// 이 방침이 마지막으로 바뀐 날. 내용을 고치면 같이 바꾼다.
	const EFFECTIVE = '2026년 11월 17일';

	const church = $derived(data.church.name);
	const officer = $derived((data.church.privacyOfficer ?? '').trim());
	const contact = $derived((data.church.privacyContact ?? '').trim());

	// 로그인한 사람은 앱으로, 아니면 로그인 화면으로 돌아간다.
	const backHref = $derived(session.user ? url('/') : url('/login'));
</script>

<svelte:head><title>개인정보 처리 방침 · {church}</title></svelte:head>

<main class="wrap">
	<article class="card doc">
		<a class="back" href={backHref}>← 돌아가기</a>
		<h1>{church} 개인정보 처리 방침</h1>
		<p class="meta">시행일자 {EFFECTIVE}</p>

		<p>
			{church}(이하 ‘교회’)는 부서 관리 프로그램(dep-manage)으로 주일학교·청소년부·청년부 같은 부서의 교사와 학생을 관리하면서
			「개인정보 보호법」에 따라 이용자와 교인의 개인정보를 안전하게 처리하고, 불편을 빠르게 해결하기 위해 이 방침을 둡니다.
		</p>

		<nav class="toc" aria-label="목차">
			<a href="#s1">1. 처리하는 개인정보 항목과 수집 방법</a>
			<a href="#s2">2. 처리 목적</a>
			<a href="#s3">3. 신앙에 관한 정보와 어린이·청소년의 정보</a>
			<a href="#s4">4. 열람 권한</a>
			<a href="#s5">5. 제3자 제공과 국외 이전</a>
			<a href="#s6">6. 처리 위탁과 저장 위치</a>
			<a href="#s7">7. 보유 및 이용 기간</a>
			<a href="#s8">8. 파기 절차와 방법</a>
			<a href="#s9">9. 정보주체와 법정대리인의 권리</a>
			<a href="#s10">10. 쿠키</a>
			<a href="#s11">11. 안전성 확보 조치</a>
			<a href="#s12">12. 개인정보 관리책임자와 문의</a>
			<a href="#s13">13. 방침의 변경</a>
		</nav>

		<section id="s1">
			<h2>1. 처리하는 개인정보 항목과 수집 방법</h2>

			<h3>가. 프로그램 이용자(교사·관리자)</h3>
			<ul>
				<li><b>가입·로그인:</b> 이름, 아이디, 비밀번호(암호화해서 저장하므로 관리자도 원래 비밀번호를 볼 수 없습니다), 소속 부서와 권한</li>
				<li><b>자동으로 남는 기록:</b> 접속 IP 주소, 로그인 시도 기록, 주요 작업 기록(누가 언제 무엇을 바꾸었는지)</li>
			</ul>

			<h3>나. 부서 구성원(학생)</h3>
			<ul>
				<li><b>인적사항:</b> 이름, 성별, 생년월일(양력·음력), 연락처, 주소, 학교, 학년·반, 사진(선택)</li>
				<li><b>보호자:</b> 보호자 이름, 관계, 연락처</li>
				<li><b>신앙·교회 생활:</b> 세례 여부, 인도자, 재적 상태, 새가족 정착 단계, 행사 참가 기록</li>
				<li><b>돌봄 기록:</b> 출석, 심방 기록, 기도 제목, 담당자가 적은 메모</li>
				<li><b>교회가 추가로 정한 항목:</b> 부서 설정의 ‘추가 항목’에서 교회가 직접 만든 항목</li>
			</ul>

			<h3>다. 교사</h3>
			<ul>
				<li>이름, 직분, 임원 역할, 생년월일, 연락처, 사진(선택), 담당 반, 메모, 교회가 추가로 정한 항목</li>
			</ul>

			<h3>라. 수집 방법</h3>
			<ul>
				<li>가입 신청, 담당자(교사·관리자)의 직접 입력, 엑셀 파일 가져오기, 보호자·본인이 교회에 알려 준 내용</li>
				<li>같은 서버에 교적 관리 프로그램(ch-manage)이 함께 설치된 교회는 교적에 있는 이름·생년월일·연락처 등을 연결해서 씁니다.</li>
			</ul>
		</section>

		<section id="s2">
			<h2>2. 처리 목적</h2>
			<ul>
				<li><b>부서 운영:</b> 구성원 명단과 반 편성, 출석 관리, 승급, 행사·모임·계획 관리</li>
				<li><b>돌봄:</b> 심방, 기도 제목 나눔, 새가족 정착 돕기, 생일 축하 같은 목양 활동</li>
				<li><b>연락:</b> 구성원과 보호자에게 교회 소식과 일정 전달</li>
				<li><b>프로그램 이용 관리:</b> 본인 확인, 권한 구분, 무단 접근 방지, 문제가 생겼을 때 원인 확인</li>
			</ul>
			<p>위 목적 밖의 일(광고, 외부 판매 등)에는 쓰지 않으며, 목적이 바뀌면 미리 알리고 필요한 동의를 받습니다.</p>
		</section>

		<section id="s3">
			<h2>3. 신앙에 관한 정보와 어린이·청소년의 정보</h2>
			<ul>
				<li>세례 여부, 직분, 기도 제목 같은 신앙에 관한 정보는 민감정보에 해당할 수 있습니다. 교회는 선교와 목양이라는 종교단체 고유의 목적에 필요한 만큼만 받고, 정보주체(또는 법정대리인)의 동의를 받아 처리합니다.</li>
				<li>이 프로그램은 주일학교·청소년부처럼 <b>어린이와 청소년</b>의 정보를 다루므로 더 조심합니다. <b>만 14세 미만</b> 어린이의 정보는 <b>법정대리인(보호자)의 동의</b>를 받아 처리합니다.</li>
				<li>심방 기록·기도 제목·메모는 교사 이상의 권한이 있는 사람만 볼 수 있고, 부서 밖의 사람에게는 보이지 않습니다.</li>
			</ul>
		</section>

		<section id="s4">
			<h2>4. 열람 권한</h2>
			<p>프로그램 안에서는 권한에 따라 볼 수 있는 범위가 달라지며, 모든 사람은 <b>자기가 속한 부서의 정보</b>만 볼 수 있습니다.</p>
			<ul>
				<li><b>보기 권한:</b> 명단 같은 기본 화면만 볼 수 있습니다.</li>
				<li><b>교사:</b> 출석·심방·기도 제목·새가족 정착을 기록하고 볼 수 있습니다.</li>
				<li><b>부서 관리자:</b> 부서 구성원과 교사 정보를 고치고, 이용자를 승인하고, 설정을 바꿀 수 있습니다.</li>
				<li><b>교회 관리자:</b> 교회 전체 부서와 이용자, 데이터 백업·복원을 관리합니다.</li>
			</ul>
			<p>체험용 계정은 가상의 예시 데이터만 볼 수 있고 실제 교인의 정보는 볼 수 없습니다.</p>
		</section>

		<section id="s5">
			<h2>5. 제3자 제공과 국외 이전</h2>
			<ul>
				<li>교회는 개인정보를 외부에 제공하지 않습니다. 정보주체의 동의가 있거나 법령에 따라 수사기관 등이 적법하게 요구한 경우에만 예외입니다.</li>
				<li>이 프로그램은 이용 통계·광고·인공지능 처리 같은 <b>외부 서비스로 개인정보를 보내지 않습니다.</b> 따라서 개인정보가 외국으로 나가는 일은 없습니다.</li>
				<li>프로그램을 만든 사람도 교회의 서버와 데이터에 접근하지 않습니다.</li>
			</ul>
		</section>

		<section id="s6">
			<h2>6. 처리 위탁과 저장 위치</h2>
			<p>
				이 프로그램은 교회가 계약한 <b>웹호스팅 서버</b>에 설치되어 있고, 모든 데이터는 그 서버의 데이터베이스와 저장 공간에 보관됩니다.
				서버를 빌려 주는 웹호스팅 업체는 서버 운영만 맡고, 교회의 허락 없이 데이터를 열람하거나 쓰지 않습니다.
			</p>
			<p>
				호스팅 업체가 바뀌거나 개인정보 처리를 새로 맡기는 경우에는 이 방침에 업체 이름과 맡기는 일을 적어 알립니다.
			</p>
		</section>

		<section id="s7">
			<h2>7. 보유 및 이용 기간</h2>
			<ul>
				<li><b>부서 구성원·교사 정보:</b> 부서에서 활동하는 동안 보유합니다. 졸업·전출·활동 종료 후에는 보호자나 본인이 삭제를 요청하지 않는 한 ‘활동 종료’ 상태로 두어 재등록·증명에 쓰며, 필요 없어지면 삭제합니다.</li>
				<li><b>이용자 계정:</b> 사용하는 동안 보유하고, 탈퇴하거나 더 이상 일하지 않으면 지체 없이 삭제합니다.</li>
				<li><b>로그인 시도 기록:</b> 짧게(하루 안팎) 보관하고 자동으로 지웁니다.</li>
				<li><b>작업 기록·접속 IP:</b> 보안과 문제 확인을 위해 보관하며, 법령에서 정한 접속기록 보관 기간(최소 1년)을 지킵니다.</li>
				<li><b>백업 파일:</b> 교회 관리자가 직접 내려받는 파일이며, 개인정보가 들어 있으므로 필요한 기간만 안전한 곳에 보관하고 오래된 것은 삭제합니다.</li>
			</ul>
		</section>

		<section id="s8">
			<h2>8. 파기 절차와 방법</h2>
			<ul>
				<li>보유 기간이 지났거나 처리 목적이 끝난 정보는 지체 없이 파기합니다. 다른 법령으로 보존해야 하는 정보는 따로 분리해 보관합니다.</li>
				<li>전자 파일(데이터베이스, 사진, 백업)은 복구할 수 없는 방법으로 삭제합니다. 프로그램에서 구성원을 삭제하면 사진과 연결된 출석·심방·기도 제목 같은 기록도 함께 삭제됩니다.</li>
				<li>출력한 종이 명단과 내려받은 엑셀 파일은 용도가 끝나면 분쇄하거나 삭제합니다.</li>
			</ul>
		</section>

		<section id="s9">
			<h2>9. 정보주체와 법정대리인의 권리</h2>
			<ul>
				<li>본인(만 14세 미만은 법정대리인)은 언제든 자기 정보의 <b>열람, 정정, 삭제, 처리 정지</b>를 요청할 수 있고, 동의를 거두어들일 수 있습니다.</li>
				<li>요청은 아래 관리책임자에게 하면 되며, 교회는 본인 또는 대리인 여부를 확인한 뒤 <b>10일 안에</b> 처리하고 결과를 알립니다.</li>
				<li>프로그램 이용자는 ‘내 정보’ 화면에서 자기 정보를 확인하고 ‘비밀번호 변경’ 화면에서 비밀번호를 직접 바꿀 수 있습니다.</li>
				<li>요청이 정당한 사유로 받아들여지기 어려운 경우에는 그 이유를 알립니다.</li>
			</ul>
		</section>

		<section id="s10">
			<h2>10. 쿠키</h2>
			<ul>
				<li>이 프로그램은 로그인 상태를 유지하기 위한 <b>세션 쿠키 하나</b>만 사용합니다. 브라우저를 닫거나 로그아웃하면 사라집니다.</li>
				<li>광고·추적·분석 쿠키는 쓰지 않습니다.</li>
				<li>브라우저 설정에서 쿠키를 막을 수 있지만, 막으면 로그인할 수 없습니다.</li>
			</ul>
		</section>

		<section id="s11">
			<h2>11. 안전성 확보 조치</h2>
			<ul>
				<li><b>비밀번호 보호:</b> 비밀번호는 되돌릴 수 없는 방식으로 암호화해 저장합니다.</li>
				<li><b>접근 통제:</b> 로그인한 사람만 사용할 수 있고, 권한과 소속 부서에 따라 볼 수 있는 정보를 나눕니다. 가입은 관리자가 승인해야 쓸 수 있습니다.</li>
				<li><b>무차별 대입 방지:</b> 비밀번호를 여러 번 틀리면 일정 시간 로그인을 막습니다.</li>
				<li><b>통신 보호:</b> 암호화 연결(HTTPS)로 서비스하도록 안내하며, 보안 관련 응답 헤더를 적용합니다.</li>
				<li><b>사진·파일 보호:</b> 올린 사진은 로그인한 교사 이상 권한이 있는 사람만 볼 수 있습니다. 서버의 설정·데이터 폴더는 인터넷에서 직접 열 수 없게 막습니다.</li>
				<li><b>기록 보관:</b> 주요 작업과 접속 기록을 남겨 점검합니다.</li>
				<li><b>백업:</b> 교회 관리자가 설정 → 데이터에서 백업을 내려받아 보관합니다. 백업 파일에는 개인정보가 들어 있으므로 안전한 곳에만 두고 다른 사람에게 전달하지 않습니다.</li>
				<li><b>취급자 제한·교육:</b> 개인정보를 다루는 교사와 관리자를 필요한 사람으로 한정하고, 개인정보 보호 수칙을 안내합니다. 퇴임·이동하는 이용자의 권한은 바로 정리합니다.</li>
			</ul>
		</section>

		<section id="s12">
			<h2>12. 개인정보 관리책임자와 문의</h2>
			<dl>
				<dt>교회</dt><dd>{church}</dd>
				<dt>관리책임자</dt><dd>{officer || '교회 사무실에 문의해 주세요.'}</dd>
				<dt>연락처</dt><dd>{contact || '교회 사무실에 문의해 주세요.'}</dd>
			</dl>
			<p>개인정보 침해에 대한 신고나 상담이 필요하면 아래 기관에 문의할 수 있습니다.</p>
			<ul>
				<li>개인정보침해신고센터 (privacy.kisa.or.kr) 국번없이 118</li>
				<li>개인정보분쟁조정위원회 (kopico.go.kr) 1833-6972</li>
				<li>대검찰청 사이버수사과 (spo.go.kr) 국번없이 1301</li>
				<li>경찰청 사이버범죄 신고시스템 (ecrm.police.go.kr) 국번없이 182</li>
			</ul>
		</section>

		<section id="s13">
			<h2>13. 방침의 변경</h2>
			<p>
				이 방침이 바뀌면 시행하기 <b>최소 7일 전에</b> 이 화면과 부서 공지로 알립니다. 이용자의 권리에 중요한 변경은 30일 전에 알립니다.
				이 방침은 {EFFECTIVE}부터 적용합니다.
			</p>
		</section>
	</article>
</main>

<style>
	.wrap { min-height: 100vh; padding: 28px 16px 48px; display: flex; justify-content: center; }
	.doc { width: 100%; max-width: 820px; padding: 32px 36px 40px; line-height: 1.75; color: var(--ink-2); }
	.back { display: inline-block; margin-bottom: 14px; padding: 6px 0; color: var(--accent); font-weight: 700; text-decoration: none; }
	h1 { font-size: 26px; line-height: 1.3; font-weight: 700; color: var(--ink); }
	.meta { margin: 6px 0 18px; font-size: 13.5px; color: var(--muted); }
	h2 { font-size: 19px; font-weight: 700; color: var(--ink); margin: 0 0 8px; }
	h3 { font-size: 15px; font-weight: 700; color: var(--ink); margin: 14px 0 4px; }
	section { margin-top: 30px; scroll-margin-top: 16px; }
	p { margin: 8px 0; }
	ul { margin: 6px 0; padding-left: 20px; display: flex; flex-direction: column; gap: 4px; }
	b { color: var(--ink); }
	.toc { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2px 16px; margin: 20px 0 0; padding: 14px 16px; background: var(--field-bg); border: 1px solid var(--line); border-radius: var(--radius-sm); }
	.toc a { color: var(--ink-2); font-size: 13.5px; padding: 4px 0; text-decoration: none; }
	.toc a:hover { color: var(--accent); text-decoration: underline; }
	dl { display: grid; grid-template-columns: 110px 1fr; gap: 6px 12px; margin: 10px 0 16px; padding: 14px 16px; background: var(--field-bg); border: 1px solid var(--line); border-radius: var(--radius-sm); }
	dt { font-weight: 700; color: var(--ink); }
	dd { margin: 0; overflow-wrap: anywhere; }
	@media (max-width: 720px) { .doc { padding: 22px 18px 30px; } .toc { grid-template-columns: 1fr; } h1 { font-size: 22px; } dl { grid-template-columns: 90px 1fr; } }
</style>
