<script lang="ts">
	import { page } from '$app/state';
	import { url } from '$lib/url';

	let { data, children } = $props();
	const root = $derived(url(`/d/${data.dept.id}/settings`));
	const tabs = $derived([
		{ label: '부서 정보', href: root },
		{ label: '용어', href: `${root}/terms` },
		{ label: '학년 · 반 구조', href: `${root}/structure` },
		{ label: '데이터', href: `${root}/data` }
	]);
	const isOn = (href: string) => (href === root ? page.url.pathname.replace(/\/$/, '') === root : page.url.pathname.startsWith(href));
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>설정</h1>
			<p class="sub">{data.dept.name} · 이 부서에만 적용됩니다</p>
		</div>
	</header>

	<div class="tabs" role="tablist" aria-label="설정 메뉴">
		{#each tabs as t (t.href)}
			<a href={t.href} role="tab" aria-selected={isOn(t.href)} class:on={isOn(t.href)}>{t.label}</a>
		{/each}
	</div>

	{@render children()}
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.tabs { display: flex; gap: 4px; border-bottom: 1px solid #dce3f3; overflow-x: auto; }
	.tabs a { padding: 10px 16px; font-size: 14px; font-weight: 700; margin-bottom: -1px; color: var(--ink-2); border-bottom: 3px solid transparent; white-space: nowrap; }
	.tabs a.on { color: var(--accent); border-bottom-color: var(--accent); }
</style>
