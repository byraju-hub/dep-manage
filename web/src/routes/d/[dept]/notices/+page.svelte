<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, createNotice, deleteNotice } from '$lib/api';
	import { dotted } from '$lib/dates';
	import { can } from '$lib/session.svelte';

	let { data } = $props();
	const canWrite = $derived(can(data.dept.id, 'admin'));

	let title = $state('');
	let body = $state('');
	let pinned = $state(false);
	let churchWide = $state(false);
	let error = $state('');

	async function submit(e: SubmitEvent) {
		e.preventDefault();
		error = '';
		if (!title.trim()) return void (error = '제목을 입력해 주세요.');
		try {
			await createNotice(data.dept.id, { title: title.trim(), body, pinned, churchWide });
			title = body = '';
			pinned = churchWide = false;
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '저장하지 못했어요.';
		}
	}

	async function remove(id: number) {
		error = '';
		try {
			await deleteNotice(id);
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '지우지 못했어요.';
		}
	}
</script>

<div class="page">
	<header class="page-head"><h1>공지사항</h1></header>

	{#if canWrite}
		<form class="card write" onsubmit={submit} novalidate>
			<label class="field"><span>제목</span><input class="input" bind:value={title} /></label>
			<label class="field"><span>내용</span><textarea class="input" rows="3" bind:value={body}></textarea></label>
			<div class="opts">
				<label class="check"><input type="checkbox" bind:checked={pinned} /> 위에 고정</label>
				<label class="check"><input type="checkbox" bind:checked={churchWide} /> 교회 전체 공지 (모든 부서에 보여요)</label>
				<button class="btn btn-primary" type="submit">공지 올리기</button>
			</div>
			{#if error}<p class="err" role="alert">{error}</p>{/if}
		</form>
	{/if}

	<div class="card list">
		{#each data.notices as n (n.id)}
			<article>
				<div class="meta">
					<span class="badge badge-sq {n.deptId === null ? 'tone-blue' : 'tone-green'}">{n.deptId === null ? '교회 전체' : '부서'}</span>
					{#if n.pinned}<span class="badge badge-sq tone-orange">고정</span>{/if}
					<span class="d">{dotted(n.date)}</span>
					{#if canWrite}<button class="del" onclick={() => remove(n.id)} aria-label="{n.title} 삭제">삭제</button>{/if}
				</div>
				<h2>{n.title}</h2>
				{#if n.body}<p>{n.body}</p>{/if}
			</article>
		{:else}
			<p class="none">공지가 없어요.</p>
		{/each}
	</div>
</div>

<style>
	.write { padding: 18px 22px; display: flex; flex-direction: column; gap: 12px; max-width: 760px; }
	.opts { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
	.opts .btn { margin-left: auto; }
	.check { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; }
	.err { color: var(--danger-ink); font-weight: 700; }
	.list { padding: 6px 22px; }
	article { padding: 16px 0; border-top: 1px solid var(--line-soft); }
	article:first-child { border-top: 0; }
	.meta { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
	.d { font-size: 12.5px; color: var(--muted); }
	.del { margin-left: auto; border: 0; background: transparent; color: var(--muted); font-size: 13px; text-decoration: underline; padding: 4px; }
	h2 { font-size: 16px; font-weight: 700; }
	p { color: var(--ink-2); margin-top: 2px; white-space: pre-line; }
	.none { color: var(--muted); padding: 20px 0; text-align: center; }
</style>
