<script lang="ts">
	import MobileNav from '$lib/components/MobileNav.svelte';
	import Sidebar from '$lib/components/Sidebar.svelte';
import { session } from '$lib/session.svelte';
import { url } from '$lib/url';

	let { data, children } = $props();
</script>

<svelte:head><title>{data.dept.name} · {data.church.name}</title></svelte:head>

<div class="shell">
	<div class="desktop"><Sidebar dept={data.dept} depts={data.depts} pending={data.pending} churchName={data.church.name} churchLogo={data.church.logo} /></div>
	<div class="mobile"><MobileNav dept={data.dept} depts={data.depts} pending={data.pending} churchName={data.church.name} churchLogo={data.church.logo} /></div>
	<main class="main">
		{#if data.needsUpdate && session.user?.isOwner}
			<a class="update" href={url(`/d/${data.dept.id}/settings/data`)}>새 버전의 데이터베이스 변경을 적용해야 해요. 눌러서 '업데이트 적용'을 해 주세요.</a>
		{/if}
		<div class="inner">{@render children()}</div>
	</main>
</div>

<style>
	.shell { display: flex; min-height: 100vh; }
	.main { flex: 1; min-width: 0; }
	.inner { max-width: 1120px; margin: 0 auto; padding: 28px 36px 48px; }
	.mobile { display: none; }
	.update { display: block; background: #fff8ec; border-bottom: 1px solid #ffd9a8; color: #7a4a06; padding: 10px 20px; font-weight: 600; text-align: center; }

	@media (max-width: 900px) {
		.shell { flex-direction: column; }
		.desktop { display: none; }
		.mobile { display: block; }
		.inner { padding: 16px 16px calc(96px + env(safe-area-inset-bottom)); }
	}
</style>
