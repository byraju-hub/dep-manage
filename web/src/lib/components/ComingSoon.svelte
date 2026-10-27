<script lang="ts">
	import Icon from './Icon.svelte';

	let { title, note }: { title: string; note: string } = $props();
</script>

<div class="page">
	<header class="page-head"><h1>{title}</h1></header>
	<div class="card box">
		<span class="ico"><Icon name="clock" size={28} /></span>
		<h2>다음 단계에서 만들어요</h2>
		<p>{note}</p>
	</div>
</div>

<style>
	.box { padding: 40px 24px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 8px; }
	.ico { width: 56px; height: 56px; border-radius: 16px; background: #fef3c7; color: #92400e; display: flex; align-items: center; justify-content: center; }
	h2 { font-size: 17px; font-weight: 700; }
	p { color: var(--muted); max-width: 440px; }
</style>
