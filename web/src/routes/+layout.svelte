<script lang="ts">
	import '../app.css';
	import favicon from '#lib/assets/favicon.svg';
	import { applyFonts } from '$lib/fonts';
	import type { LayoutProps } from './$types';

	let { data, children }: LayoutProps = $props();

	// 교회 설정의 글꼴을 화면 전체에 적용한다. (설정에서 바꾸면 data 가 새로 와서 다시 적용된다.)
	$effect.pre(() => {
		applyFonts(data.church.fonts);
	});
</script>

<svelte:head>
	<link rel="icon" href={favicon} />
	<title>{data.church.name} · dep-manage</title>
</svelte:head>

{@render children()}
