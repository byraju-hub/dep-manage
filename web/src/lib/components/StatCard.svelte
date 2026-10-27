<script lang="ts">
	import Tile from './Tile.svelte';
	import type { Tone } from '../types';

	let {
		label,
		value,
		unit = '',
		note = '',
		noteTone = 'muted',
		icon,
		tone
	}: { label: string; value: string | number; unit?: string; note?: string; noteTone?: 'ok' | 'bad' | 'muted'; icon: string; tone: Tone } = $props();
</script>

<div class="card stat">
	<div>
		<div class="label">{label}</div>
		<div class="num">
			<span class="value">{value}</span>
			{#if unit}<span class="unit">{unit}</span>{/if}
		</div>
		{#if note}<div class="note {noteTone}">{note}</div>{/if}
	</div>
	<Tile {icon} {tone} size={48} iconSize={24} />
</div>

<style>
	.stat {
		display: flex; align-items: center; justify-content: space-between; gap: 10px;
		padding: 14px 16px 14px 18px;
	}
	.label { font-size: 13px; color: var(--ink-2); }
	.num { display: flex; align-items: baseline; gap: 6px; }
	.value { font-size: 30px; font-weight: 700; line-height: 1.25; }
	.unit { font-size: 13px; color: var(--muted); white-space: nowrap; }
	@media (max-width: 480px) {
		.stat { padding: 12px 14px; }
		.stat :global(.tile) { display: none; }
		.value { font-size: 26px; }
	}
	.note { font-size: 12.5px; }
	.note.ok { color: #0f7b4a; font-weight: 600; }
	.note.bad { color: var(--danger-ink); font-weight: 600; }
	.note.muted { color: var(--muted); }
</style>
