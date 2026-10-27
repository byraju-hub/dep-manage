<script lang="ts">
	import { dotted } from '$lib/dates';

	let { data } = $props();
</script>

<div class="page">
	<header class="page-head"><h1>공지사항</h1></header>
	<div class="card list">
		{#each data.notices as n (n.id)}
			<article>
				<div class="meta">
					<span class="badge badge-sq {n.deptId === null ? 'tone-blue' : 'tone-green'}">{n.deptId === null ? '교회 전체' : '부서'}</span>
					{#if n.pinned}<span class="badge badge-sq tone-orange">고정</span>{/if}
					<span class="d">{dotted(n.date)}</span>
				</div>
				<h2>{n.title}</h2>
				<p>{n.body}</p>
			</article>
		{/each}
	</div>
</div>

<style>
	.list { padding: 6px 22px; }
	article { padding: 16px 0; border-top: 1px solid var(--line-soft); }
	article:first-child { border-top: 0; }
	.meta { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
	.d { font-size: 12.5px; color: var(--muted); }
	h2 { font-size: 16px; font-weight: 700; }
	p { color: var(--ink-2); margin-top: 2px; }
</style>
