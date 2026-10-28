<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { ApiError, deleteTeacher, updateTeacher } from '$lib/api';
	import PhotoField from '$lib/components/PhotoField.svelte';
import TeacherForm from '$lib/components/TeacherForm.svelte';
	import { can } from '$lib/session.svelte';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';

	let { data } = $props();
	const t = $derived(data.teacher);
	let confirming = $state(false);
	let error = $state('');
	const editable = $derived(can(data.dept.id, 'admin'));

	async function save(v: Parameters<typeof updateTeacher>[1]) {
		await updateTeacher(t.id, v);
		await invalidateAll();
		await goto(url(`/d/${data.dept.id}/teachers`));
	}

	async function remove() {
		error = '';
		try {
			await deleteTeacher(t.id);
			await invalidateAll();
			await goto(url(`/d/${data.dept.id}/teachers`));
		} catch (e) {
			error = e instanceof ApiError ? e.message : '삭제하지 못했어요.';
		}
	}
</script>

<div class="page">
	<header class="page-head"><h1>{t.name}</h1></header>
	{#if editable}
		<section class="card photo"><h2>사진</h2><PhotoField type="teacher" id={t.id} name={t.name} token={t.photo} tone="violet" /></section>
		<TeacherForm
			classes={data.classes}
			initial={{ name: t.name, position: t.position, officerRole: t.officerRole, birthDate: t.birthDate, phone: t.phone, status: t.status, notes: t.notes, classIds: t.classIds }}
			onsubmit={save}
			oncancel={() => goto(url(`/d/${data.dept.id}/teachers`))}
		/>
		<section class="card danger">
			<h2>{terms.teacher} 삭제</h2>
			<p class="muted">출석 기록까지 함께 지워지고 되돌릴 수 없어요. 잠시 쉬는 경우에는 상태를 <b>쉬는 중</b>으로 바꾸는 것을 권해요.</p>
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
	{:else}
		<div class="card info">
			<p>{t.position || '-'} · {t.officerRole || '임원 없음'}</p>
			<p class="muted">담당 {terms.class}: {t.classNames.join(', ') || '-'}</p>
		</div>
	{/if}
</div>

<style>
	.photo { padding: 18px 22px; max-width: 860px; display: flex; flex-direction: column; gap: 10px; }
	.danger { padding: 18px 22px; max-width: 860px; display: flex; flex-direction: column; gap: 8px; border-color: #f5b8c7; background: #fffafb; }
	.info { padding: 18px 22px; max-width: 860px; }
	h2 { font-size: 16px; font-weight: 700; }
	.row { display: flex; gap: 10px; }
	.del { background: var(--danger); color: #fff; border-color: transparent; }
	.err { color: var(--danger-ink); font-weight: 700; }
</style>
