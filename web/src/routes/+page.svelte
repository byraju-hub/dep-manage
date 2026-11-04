<script lang="ts">
	import ChurchLogo from '$lib/components/ChurchLogo.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import { dotted } from '$lib/dates';
	import { can, doLogout, session } from '$lib/session.svelte';
	import { url } from '$lib/url';

	let { data } = $props();
</script>

<svelte:head><title>{data.church.name} · 교회 홈</title></svelte:head>

<main class="wrap">
	<header class="top">
		<div class="brand">
			<ChurchLogo name={data.church.name} logo={data.church.logo} size={44} />
			<div>
				<h1>{data.church.name}</h1>
				<p class="muted">교회 홈 · {session.user?.name}님 ({session.user?.roleLabel})</p>
			</div>
		</div>
		<div class="right">
			<a class="btn" href={url('/me')}>내 정보</a>
			<button class="btn" onclick={doLogout}>로그아웃</button>
		</div>
	</header>

	{#if data.notices.length}
		<section class="card notices" aria-label="교회 공지">
			<h2>교회 공지</h2>
			<ul>
				{#each data.notices as n (n.id)}
					<li>
						{#if n.pinned}<span class="badge tone-orange">고정</span>{/if}
						<b>{n.title}</b>
						<span class="muted">{dotted(n.date)}</span>
						{#if n.body}<p>{n.body}</p>{/if}
					</li>
				{/each}
			</ul>
		</section>
	{/if}

	<h2 class="sec">우리 부서</h2>
	<div class="grid">
		{#each data.summaries as { dept, summary } (dept.id)}
			<a class="card dept" href={url(`/d/${dept.id}`)}>
				<div class="name">{dept.name}<Icon name="chevronRight" size={18} /></div>
				<div class="muted">{dept.pastorName ? `담당 ${dept.pastorName}` : '담당 교역자 미정'}</div>
				{#if summary}
					<dl>
						<div><dt>재적 {dept.terms.student}</dt><dd>{summary.enrolledTotal}명</dd></div>
						<div><dt>지난 주일 출석</dt><dd>{summary.studentCount}명</dd></div>
						<div><dt>새가족</dt><dd>{summary.newCount}명</dd></div>
						<div><dt>이번 주 생일</dt><dd>{summary.birthdayCount}명</dd></div>
					</dl>
					{#if summary.absentees.length}<p class="warn">장기 결석 {summary.absentees.length}명</p>{/if}
					{#if can(dept.id, 'teacher') && summary.followUps.length}<p class="warn">후속 조치가 남은 {dept.terms.visit} {summary.followUps.length}건</p>{/if}
				{/if}
			</a>
		{/each}
	</div>
</main>

<style>
	.wrap { max-width: 1040px; margin: 0 auto; padding: 28px 20px 56px; display: flex; flex-direction: column; gap: 20px; }
	.top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
	.brand { display: flex; align-items: center; gap: 12px; }
	h1 { font-size: 24px; line-height: 1.3; }
	.right { display: flex; gap: 8px; }
	.sec { font-size: 18px; }
	.notices { padding: 16px 20px; display: flex; flex-direction: column; gap: 8px; }
	.notices h2 { font-size: 16px; }
	.notices ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
	.notices li p { margin: 4px 0 0; white-space: pre-line; color: var(--ink-2); line-height: 1.6; }
	.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
	.dept { padding: 18px 20px; display: flex; flex-direction: column; gap: 10px; color: inherit; text-decoration: none; }
	.dept:hover { border-color: var(--accent); }
	.name { display: flex; justify-content: space-between; align-items: center; font-size: 19px; font-weight: 700; }
	dl { margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; }
	dl div { display: flex; flex-direction: column; }
	dt { font-size: 12.5px; color: var(--muted); }
	dd { margin: 0; font-size: 18px; font-weight: 700; }
	.warn { font-size: 13px; font-weight: 700; color: var(--danger-ink); }
</style>
