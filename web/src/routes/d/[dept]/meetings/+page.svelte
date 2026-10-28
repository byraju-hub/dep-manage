<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, createMeeting, deleteMeeting, updateMeeting } from '$lib/api';
	import { dotted, today } from '$lib/dates';
	import { can } from '$lib/session.svelte';
	import { terms } from '$lib/terms.svelte';

	let { data } = $props();
	const canWrite = $derived(can(data.dept.id, 'teacher'));

	let editing = $state<number | null>(null);
	let date = $state(today());
	let title = $state('');
	let content = $state('');
	let attendees = $state('');
	let error = $state('');
	let open = $state<number | null>(null);

	function startNew() {
		editing = 0;
		date = today();
		title = content = attendees = '';
		error = '';
	}

	function startEdit(m: (typeof data.meetings)[number]) {
		editing = m.id;
		date = m.date;
		title = m.title;
		content = m.content;
		attendees = m.attendees;
		error = '';
	}

	async function save(e: SubmitEvent) {
		e.preventDefault();
		error = '';
		if (!title.trim()) return void (error = '제목을 입력해 주세요.');
		try {
			const v = { date, title: title.trim(), content, attendees };
			if (editing) await updateMeeting(editing, v);
			else await createMeeting(data.dept.id, v);
			editing = null;
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '저장하지 못했어요.';
		}
	}

	async function remove(id: number) {
		error = '';
		try {
			await deleteMeeting(id);
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '지우지 못했어요.';
		}
	}
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>회의록</h1>
			<p class="sub">{terms.teacher}회의 내용을 날짜별로 남겨요.</p>
		</div>
		{#if canWrite && editing === null}<button class="btn btn-primary" onclick={startNew}>새 회의록</button>{/if}
	</header>

	{#if editing !== null}
		<form class="card form" onsubmit={save} novalidate>
			<div class="two">
				<label class="field"><span>날짜</span><input class="input" type="date" bind:value={date} /></label>
				<label class="field"><span>제목</span><input class="input" bind:value={title} placeholder="예: 10월 {terms.teacher}회의" /></label>
			</div>
			<label class="field"><span>참석자</span><input class="input" bind:value={attendees} /></label>
			<label class="field"><span>내용</span><textarea class="input" rows="10" bind:value={content}></textarea></label>
			{#if error}<p class="err" role="alert">{error}</p>{/if}
			<div class="buttons">
				<button type="button" class="btn" onclick={() => (editing = null)}>취소</button>
				<button type="submit" class="btn btn-primary">저장</button>
			</div>
		</form>
	{/if}

	{#if error && editing === null}<p class="err" role="alert">{error}</p>{/if}

	<div class="card list">
		{#each data.meetings as m (m.id)}
			<article>
				<button class="head" onclick={() => (open = open === m.id ? null : m.id)} aria-expanded={open === m.id}>
					<span class="d">{dotted(m.date)}</span>
					<span class="t">{m.title}</span>
					<span class="chev">{open === m.id ? '접기' : '보기'}</span>
				</button>
				{#if open === m.id}
					<div class="body">
						{#if m.attendees}<p class="muted">참석 · {m.attendees}</p>{/if}
						<p class="txt">{m.content || '내용이 없어요.'}</p>
						{#if canWrite}
							<div class="row"><button class="btn sm" onclick={() => startEdit(m)}>수정</button><button class="btn sm" onclick={() => remove(m.id)}>삭제</button></div>
						{/if}
					</div>
				{/if}
			</article>
		{:else}
			<p class="none">회의록이 없어요.</p>
		{/each}
	</div>
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.form { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; max-width: 860px; }
	.two { display: grid; grid-template-columns: 200px 1fr; gap: 14px; }
	.buttons { display: flex; justify-content: flex-end; gap: 10px; }
	.err { color: var(--danger-ink); font-weight: 700; }
	.list { padding: 4px 20px; }
	article { border-top: 1px solid var(--line-soft); }
	article:first-child { border-top: 0; }
	.head { width: 100%; display: flex; align-items: center; gap: 14px; padding: 14px 0; border: 0; background: transparent; text-align: left; }
	.d { width: 110px; color: var(--muted); font-size: 13px; flex-shrink: 0; }
	.t { font-weight: 600; flex: 1; }
	.chev { color: var(--accent); font-size: 13px; font-weight: 700; }
	.body { padding: 0 0 16px 124px; display: flex; flex-direction: column; gap: 8px; }
	.txt { white-space: pre-line; line-height: 1.7; }
	.row { display: flex; gap: 8px; }
	.btn.sm { height: 34px; padding: 0 12px; font-size: 13px; }
	.none { color: var(--muted); padding: 24px; text-align: center; }
	@media (max-width: 720px) { .two { grid-template-columns: 1fr; } .d { width: 90px; } .body { padding-left: 0; } }
</style>
