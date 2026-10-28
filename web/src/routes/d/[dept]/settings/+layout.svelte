<script lang="ts">
	import { page } from '$app/state';
	import { can, session } from '$lib/session.svelte';
	import { url } from '$lib/url';

	let { data, children } = $props();
	const root = $derived(url(`/d/${data.dept.id}/settings`));
	const isAdmin = $derived(can(data.dept.id, 'admin'));
	const tabs = $derived([
		{ label: '부서 정보', href: root },
		{ label: '용어', href: `${root}/terms` },
		{ label: '학년 · 반 구조', href: `${root}/structure` },
		{ label: '글꼴', href: `${root}/fonts` },
		{ label: '사용자', href: `${root}/users` },
		...(session.user?.isOwner ? [{ label: '데이터', href: `${root}/data` }] : [])
	]);
	const isOn = (href: string) => (href === root ? page.url.pathname.replace(/\/$/, '') === root : page.url.pathname.startsWith(href));
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>설정</h1>
			<p class="sub">{data.dept.name} · 부서 정보와 용어는 이 부서에만, 교회 이름과 글꼴은 모든 부서에 적용됩니다</p>
		</div>
	</header>

	{#if !isAdmin}
		<div class="card none">설정은 부서 관리자와 교회 관리자만 열 수 있어요.</div>
	{:else}
		<div class="tabs" role="tablist" aria-label="설정 메뉴">
			{#each tabs as t (t.href)}
				<a href={t.href} role="tab" aria-selected={isOn(t.href)} class:on={isOn(t.href)}>{t.label}</a>
			{/each}
		</div>

		{@render children()}
	{/if}
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.none { padding: 28px; color: var(--muted); text-align: center; }
	.tabs { display: flex; gap: 4px; border-bottom: 1px solid #dce3f3; overflow-x: auto; }
	.tabs a { padding: 10px 16px; font-size: 14px; font-weight: 700; margin-bottom: -1px; color: var(--ink-2); border-bottom: 3px solid transparent; white-space: nowrap; }
	.tabs a.on { color: var(--accent); border-bottom-color: var(--accent); }
</style>
