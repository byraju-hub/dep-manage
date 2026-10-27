<script lang="ts">
	// Noto Sans KR (가변 글꼴). 서버에서 직접 내려주므로 외부 글꼴 서비스를 쓰지 않는다.
	import '@fontsource-variable/noto-sans-kr';
	import '../app.css';
	import favicon from '#lib/assets/favicon.svg';
	import type { LayoutProps } from './$types';

	let { children }: LayoutProps = $props();
</script>

<svelte:head>
	<link rel="icon" href={favicon} />
	<title>dep-manage</title>
</svelte:head>

{@render children()}
