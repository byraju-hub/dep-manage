<script lang="ts">
	import Icon from './Icon.svelte';
	import type { Tone } from '../types';

	let { icon, tone, size = 30, iconSize }: { icon: string; tone: Tone; size?: number; iconSize?: number } = $props();
</script>

<span
	class="tile tone-{tone}"
	style:width="{size}px"
	style:height="{size}px"
	style:border-radius="{Math.round(size * 0.3)}px"
>
	<Icon name={icon} size={iconSize ?? Math.round(size * 0.6)} />
</span>

<style>
	.tile {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		background: var(--bg);
		color: var(--fg);
	}
</style>
