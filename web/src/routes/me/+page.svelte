<script lang="ts">
	import { doLogout, roleIn, session } from '$lib/session.svelte';
	import { url } from '$lib/url';

	let { data } = $props();

	const roleLabel = { owner: '교회 관리자', admin: '부서 관리자', teacher: '부서 교사', viewer: '열람' } as const;
	const firstDept = $derived(data.depts[0]?.id);
</script>

<svelte:head><title>내 정보 · {data.church.name}</title></svelte:head>

<main class="wrap">
	<header>
		<h1>내 정보</h1>
		<a class="link" href={firstDept ? url(`/d/${firstDept}`) : url('/')}>← 돌아가기</a>
	</header>

	<section class="card box">
		<dl>
			<div><dt>이름</dt><dd>{session.user?.name}</dd></div>
			<div><dt>아이디</dt><dd>{session.user?.loginId ?? ''}</dd></div>
			<div><dt>권한</dt><dd>{session.user?.roleLabel}</dd></div>
		</dl>
		<div class="row">
			<a class="btn btn-primary" href={url('/password')}>비밀번호 바꾸기</a>
			<button class="btn" onclick={doLogout}>로그아웃</button>
		</div>
	</section>

	<section class="card box">
		<h2>소속 부서와 역할</h2>
		<ul>
			{#each data.depts as d (d.id)}
				{@const r = roleIn(d.id)}
				<li><a href={url(`/d/${d.id}`)}>{d.name}</a><span class="badge tone-blue">{r ? roleLabel[r] : '-'}</span></li>
			{:else}
				<li class="muted">소속된 부서가 없어요.</li>
			{/each}
		</ul>
	</section>
</main>

<style>
	.wrap { max-width: 640px; margin: 0 auto; padding: 28px 20px 56px; display: flex; flex-direction: column; gap: 16px; }
	header { display: flex; justify-content: space-between; align-items: baseline; }
	h1 { font-size: 26px; }
	h2 { font-size: 17px; }
	.link { color: var(--accent); font-weight: 700; }
	.box { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; }
	dl { margin: 0; display: flex; flex-direction: column; gap: 10px; }
	dl div { display: flex; gap: 12px; }
	dt { width: 70px; color: var(--muted); }
	dd { margin: 0; font-weight: 700; }
	.row { display: flex; gap: 8px; flex-wrap: wrap; }
	ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
	li { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-top: 1px solid var(--line-soft); }
	li:first-child { border-top: 0; }
	li a { font-weight: 700; color: var(--ink); }
</style>
