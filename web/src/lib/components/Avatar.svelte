<script lang="ts">
	import { avatarTone } from '../palette';
	import type { Tone } from '../types';

	let { name, index = 0, size = 36, tone }: { name: string; index?: number; size?: number; tone?: Tone } = $props();
	const t = $derived(tone ?? avatarTone(index));
</script>

<span class="avatar tone-{t}" style:width="{size}px" style:height="{size}px" style:font-size="{Math.round(size * 0.4)}px" aria-hidden="true">
	{name.slice(0, 1)}
</span>

<style>
	.avatar {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		border-radius: 50%;
		background: var(--bg);
		color: var(--fg);
		font-weight: 700;
	}
</style>
