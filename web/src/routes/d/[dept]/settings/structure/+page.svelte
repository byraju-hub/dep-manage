<script lang="ts">
	import { terms } from '$lib/terms.svelte';

	let { data } = $props();
</script>

<section class="card box">
	<div>
		<h2>{terms.grade} 단계</h2>
		<p class="sub">해가 바뀔 때 이 순서대로 올라갑니다. (데모 화면: 편집 기능은 다음 단계에서 붙습니다.)</p>
	</div>
	<div class="chain">
		{#each data.dept.gradeLevels as g, i (g.id)}
			<span class="step">
				<span class="badge badge-sq tone-{g.tone ?? 'slate'} chip" class:dash={!g.tone && !g.isGraduate}>{g.label}</span>
				{#if i < data.dept.gradeLevels.length - 1}<span class="arrow" aria-hidden="true">›</span>{/if}
			</span>
		{/each}
	</div>
</section>

<style>
	.box { padding: 22px 26px; display: flex; flex-direction: column; gap: 14px; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13px; color: var(--muted); }
	.chain { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
	.step { display: inline-flex; align-items: center; gap: 6px; }
	.chip { height: 32px; padding: 0 12px; font-size: 13.5px; font-weight: 700; }
	.dash { background: var(--surface); border: 1px dashed #98a2b3; color: var(--ink-2); }
	.arrow { color: #98a2b3; font-size: 18px; }
</style>
