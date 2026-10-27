<script lang="ts">
	import MobileNav from '$lib/components/MobileNav.svelte';
	import Sidebar from '$lib/components/Sidebar.svelte';

	let { data, children } = $props();
</script>

<svelte:head><title>{data.dept.name} · dep-manage</title></svelte:head>

<div class="shell">
	<div class="desktop"><Sidebar dept={data.dept} depts={data.depts} pending={data.pending} /></div>
	<div class="mobile"><MobileNav dept={data.dept} depts={data.depts} pending={data.pending} /></div>
	<main class="main">
		<div class="inner">{@render children()}</div>
	</main>
</div>

<style>
	.shell { display: flex; min-height: 100vh; }
	.main { flex: 1; min-width: 0; }
	.inner { max-width: 1120px; margin: 0 auto; padding: 28px 36px 48px; }
	.mobile { display: none; }

	@media (max-width: 900px) {
		.shell { flex-direction: column; }
		.desktop { display: none; }
		.mobile { display: block; }
		.inner { padding: 16px 16px calc(96px + env(safe-area-inset-bottom)); }
	}
</style>
