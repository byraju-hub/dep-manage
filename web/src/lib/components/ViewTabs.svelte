<script lang="ts">
	// 명단 보기 방식 전환 (기본 / 상세 / 반별). 주소의 ?view= 값으로 구분한다.
	let { views, current, hrefFor }: { views: Array<{ key: string; label: string }>; current: string; hrefFor: (key: string) => string } = $props();
</script>

<div class="tabs" role="tablist" aria-label="보기 방식">
	{#each views as v (v.key)}
		<a href={hrefFor(v.key)} role="tab" aria-selected={v.key === current} class:on={v.key === current}>{v.label}</a>
	{/each}
</div>

<style>
	.tabs { display: inline-flex; background: #e6ecfa; border-radius: 12px; padding: 4px; gap: 2px; }
	a { padding: 7px 18px; border-radius: 9px; font-size: 14px; font-weight: 600; color: var(--ink-2); min-height: 20px; }
	a.on { background: var(--surface); color: var(--accent); font-weight: 700; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.12); }
	@media (max-width: 480px) { a { padding: 8px 14px; } }
</style>
