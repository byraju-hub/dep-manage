<script lang="ts">
	import ImportWizard from '$lib/components/ImportWizard.svelte';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';

	let { data } = $props();
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>{terms.student} 명단 엑셀로 올리기</h1>
			<p class="sub"><a class="back" href={url(`/d/${data.dept.id}/students`)}>← {terms.student} 명단</a></p>
		</div>
	</header>
	<ImportWizard kind="students" dept={data.dept} classes={data.classes.map((c) => ({ id: c.id, name: c.name }))} fields={data.fields.filter((f) => f.entity === 'student')} />
</div>

<style>
	.back { color: var(--accent); font-weight: 700; font-size: 13.5px; }
</style>
