<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { createTeacher } from '$lib/api';
	import TeacherForm from '$lib/components/TeacherForm.svelte';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';

	let { data } = $props();

	async function save(v: Parameters<typeof createTeacher>[1]) {
		await createTeacher(data.dept.id, v);
		await invalidateAll();
		await goto(url(`/d/${data.dept.id}/teachers`));
	}
</script>

<div class="page">
	<header class="page-head"><h1>{terms.teacher} 추가</h1></header>
	<TeacherForm classes={data.classes} submitLabel="추가" onsubmit={save} oncancel={() => goto(url(`/d/${data.dept.id}/teachers`))} />
</div>
