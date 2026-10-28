<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { ApiError, deleteStudent, updateStudent } from '$lib/api';
	import PhotoField from '$lib/components/PhotoField.svelte';
import StudentForm from '$lib/components/StudentForm.svelte';
	import { can } from '$lib/session.svelte';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';

	let { data } = $props();
	const s = $derived(data.student);
	let confirming = $state(false);
	let error = $state('');

	async function save(v: Parameters<typeof updateStudent>[1]) {
		await updateStudent(s.id, v);
		await invalidateAll();
		await goto(url(`/d/${data.dept.id}/students/${s.id}`));
	}

	async function remove() {
		error = '';
		try {
			await deleteStudent(s.id);
			await invalidateAll();
			await goto(url(`/d/${data.dept.id}/students`));
		} catch (e) {
			error = e instanceof ApiError ? e.message : '삭제하지 못했어요.';
		}
	}
</script>

<div class="page">
	<header class="page-head"><h1>{s.name} 수정</h1></header>
	<section class="card photo"><h2>사진</h2><PhotoField type="student" id={s.id} name={s.name} token={s.photo} tone={s.tone} /></section>
	<StudentForm
		dept={data.dept}
		classes={data.classes}
		fields={data.fields.filter((f) => f.entity === 'student')}
		initial={{ name: s.name, gender: s.gender, birthDate: s.birthDate, phone: s.phone, school: s.school, gradeId: s.gradeId, classId: s.classId, status: s.status, baptism: s.baptism, mentor: s.mentor, address: s.address, notes: s.notes, guardians: s.guardians, custom: s.custom }}
		onsubmit={save}
		oncancel={() => goto(url(`/d/${data.dept.id}/students/${s.id}`))}
	/>

	{#if can(data.dept.id, 'admin')}
		<section class="card danger">
			<h2>{terms.student} 삭제</h2>
			<p class="muted">출석과 {terms.visit} 대상 기록까지 함께 지워지고 되돌릴 수 없어요. 잠시 쉬는 경우에는 상태를 <b>비활동</b>으로 바꾸는 것을 권해요.</p>
			{#if !confirming}
				<div><button class="btn" onclick={() => (confirming = true)}>삭제…</button></div>
			{:else}
				<div class="row">
					<button class="btn" onclick={() => (confirming = false)}>취소</button>
					<button class="btn del" onclick={remove}>정말 삭제하기</button>
				</div>
			{/if}
			{#if error}<p class="err" role="alert">{error}</p>{/if}
		</section>
	{/if}
</div>

<style>
	.photo { padding: 18px 22px; max-width: 860px; display: flex; flex-direction: column; gap: 10px; }
	.danger { padding: 18px 22px; max-width: 860px; display: flex; flex-direction: column; gap: 8px; border-color: #f5b8c7; background: #fffafb; }
	h2 { font-size: 16px; font-weight: 700; }
	.row { display: flex; gap: 10px; }
	.del { background: var(--danger); color: #fff; border-color: transparent; }
	.err { color: var(--danger-ink); font-weight: 700; }
</style>
