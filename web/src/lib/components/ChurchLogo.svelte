<script lang="ts">
	import { logoUrl } from '../api';

	// 교회 로고. 올린 로고가 없거나 불러오지 못하면 교회 이름의 첫 글자를 포인트 색 바탕에 보여준다.
	let { name, logo = null, size = 38 }: { name: string; logo?: string | null; size?: number } = $props();
	let failed = $state(false);
	$effect(() => {
		logo; // 로고가 바뀌면 다시 시도한다
		failed = false;
	});
</script>

<span class="logo" class:img={logo && !failed} style:width="{size}px" style:height="{size}px" style:border-radius="{Math.round(size * 0.3)}px" style:font-size="{Math.round(size * 0.45)}px" aria-hidden="true">
	{#if logo && !failed}
		<img src={logoUrl(logo)} alt="" onerror={() => (failed = true)} />
	{:else}
		{name.slice(0, 1)}
	{/if}
</span>

<style>
	.logo {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		background: var(--accent);
		color: #fff;
		font-weight: 700;
		overflow: hidden;
	}
	/* 로고 그림이 있으면 바탕색을 없앤다. (투명한 PNG 가 포인트 색 위에 올라가 보이지 않게) */
	.logo.img { background: transparent; }
	img { width: 100%; height: 100%; object-fit: contain; display: block; }
</style>
