<script lang="ts">
	import { avatarTone } from '../palette';
	import type { Tone } from '../types';

	// 사진 주소(src)가 있으면 사진을, 없거나 불러오지 못하면 이름의 첫 글자를 보여준다.
	let { name, index = 0, size = 36, tone, src = null }: { name: string; index?: number; size?: number; tone?: Tone; src?: string | null } = $props();
	const t = $derived(tone ?? avatarTone(index));
	let failed = $state(false);
	$effect(() => {
		src; // 사진 주소가 바뀌면 다시 시도한다
		failed = false;
	});
</script>

<span class="avatar tone-{t}" style:width="{size}px" style:height="{size}px" style:font-size="{Math.round(size * 0.4)}px" aria-hidden="true">
	{#if src && !failed}
		<img {src} alt="" loading="lazy" onerror={() => (failed = true)} />
	{:else}
		{name.slice(0, 1)}
	{/if}
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
		overflow: hidden;
	}
	img { width: 100%; height: 100%; object-fit: cover; display: block; }
</style>
