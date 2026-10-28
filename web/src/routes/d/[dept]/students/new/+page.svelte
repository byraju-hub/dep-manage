<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { createStudent } from '$lib/api';
	import StudentForm from '$lib/components/StudentForm.svelte';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';

	let { data } = $props();

	async function save(v: Parameters<typeof createStudent>[1]) {
		const r = await createStudent(data.dept.id, v);
		await invalidateAll();
		await goto(url(`/d/${data.dept.id}/students/${r.id}`));
	}
</script>

<div class="page">
	<header class="page-head"><h1>{terms.student} 추가</h1></header>
	<StudentForm dept={data.dept} classes={data.classes} fields={data.fields.filter((f) => f.entity === 'student')} submitLabel="추가" onsubmit={save} oncancel={() => goto(url(`/d/${data.dept.id}/students`))} />
</div>
