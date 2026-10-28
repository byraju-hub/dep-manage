<script lang="ts">
	import '../app.css';
	import favicon from '#lib/assets/favicon.svg';
	import { logoUrl } from '$lib/api';
import { applyFonts } from '$lib/fonts';
	import type { LayoutProps } from './$types';

	let { data, children }: LayoutProps = $props();

	// 교회 설정의 글꼴을 화면 전체에 적용한다. (설정에서 바꾸면 data 가 새로 와서 다시 적용된다.)
	$effect.pre(() => {
		applyFonts(data.church.fonts);
	});
</script>

<svelte:head>
	{#if data.church.logo}
		<!-- 올린 로고를 탭 아이콘으로. 로고를 바꾸면 주소(v=)가 바뀌어 브라우저가 새로 받는다. -->
		<link rel="icon" type="image/png" href={logoUrl(data.church.logo, true)} />
		<link rel="apple-touch-icon" href={logoUrl(data.church.logo)} />
	{:else}
		<link rel="icon" href={favicon} />
	{/if}
	<title>{data.church.name} · dep-manage</title>
</svelte:head>

{@render children()}
