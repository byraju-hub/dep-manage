<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { ApiError, answerPrayer, createPrayer, deletePrayer, updatePrayer } from '$lib/api';
	import Icon from '$lib/components/Icon.svelte';
	import { dotted, today } from '$lib/dates';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';
	import type { PrayerRow } from '$lib/types';

	let { data } = $props();

	let adding = $state(false);
	let target = $state(''); // 'student:12'
	let content = $state('');
	let editing = $state<number | null>(null);
	let editText = $state('');
	let error = $state('');
	let search = $state('');

	const matches = $derived(data.people.filter((p) => !search.trim() || p.name.includes(search.trim())));

	const tabs = [
		{ key: 'praying', label: '기도 중' },
		{ key: 'answered', label: '응답받음' },
		{ key: 'all', label: '전체' }
	] as const;

	async function run(fn: () => Promise<unknown>, fail: string) {
		error = '';
		try {
			await fn();
			await invalidateAll();
		} catch (e) {
			error = e instanceof ApiError ? e.message : fail;
		}
	}

	async function add(e: SubmitEvent) {
		e.preventDefault();
		if (!target) return void (error = '누구의 기도 제목인지 골라 주세요.');
		if (!content.trim()) return void (error = '기도 제목을 적어 주세요.');
		const [type, id] = target.split(':');
		await run(async () => {
			await createPrayer(data.dept.id, { targetType: type as 'student' | 'teacher', targetId: Number(id), content: content.trim(), date: today() });
			content = '';
			adding = false;
		}, '저장하지 못했어요.');
	}

	const startEdit = (p: PrayerRow) => {
		editing = p.id;
		editText = p.content;
	};
	const saveEdit = (p: PrayerRow) =>
		run(async () => {
			await updatePrayer(p.id, editText.trim());
			editing = null;
		}, '저장하지 못했어요.');
	const remove = (p: PrayerRow) => confirm(`${p.targetName}의 기도 제목을 지울까요?`) && run(() => deletePrayer(p.id), '지우지 못했어요.');
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>기도 제목</h1>
			<p class="sub">{terms.student}·{terms.teacher}의 기도 제목을 오래 두고 보며, 응답받은 것은 표시해 두세요. {terms.teacher} 이상만 볼 수 있어요.</p>
		</div>
		{#if !adding}<button class="btn btn-primary" onclick={() => (adding = true)}>기도 제목 추가</button>{/if}
	</header>

	{#if adding}
		<form class="card form" onsubmit={add} novalidate>
			<label class="field">
				<span>누구의 기도 제목인가요?</span>
				<input class="input" placeholder="이름으로 찾기" bind:value={search} />
				<select class="input" size="5" bind:value={target} aria-label="대상 선택">
					{#each matches as p (p.type + p.id)}<option value="{p.type}:{p.id}">{p.name} ({p.type === 'student' ? terms.student : terms.teacher})</option>{/each}
				</select>
			</label>
			<label class="field"><span>기도 제목</span><textarea class="input" rows="3" maxlength="500" bind:value={content}></textarea></label>
			{#if error}<p class="err" role="alert">{error}</p>{/if}
			<div class="buttons">
				<button type="button" class="btn" onclick={() => (adding = false)}>취소</button>
				<button type="submit" class="btn btn-primary">저장</button>
			</div>
		</form>
	{/if}

	<nav class="tabs" aria-label="상태">
		{#each tabs as t (t.key)}
			<button class="tab" class:on={data.status === t.key} onclick={() => goto(url(`/d/${data.dept.id}/prayers?status=${t.key}`), { reset: false })}>{t.label}</button>
		{/each}
	</nav>

	{#if error && !adding}<p class="err" role="alert">{error}</p>{/if}

	<div class="card list">
		{#each data.prayers as p (p.id)}
			<article class:done={p.status === 'answered'}>
				<div class="top">
					<a class="who" href={url(`/d/${data.dept.id}/${p.targetType === 'student' ? 'students' : 'teachers'}/${p.targetId}`)}>{p.targetName}</a>
					<span class="meta">{dotted(p.date)} · {p.author}{p.answeredDate ? ` · 응답 ${dotted(p.answeredDate)}` : ''}</span>
				</div>
				{#if editing === p.id}
					<textarea class="input" rows="3" maxlength="500" bind:value={editText}></textarea>
					<div class="row">
						<button class="btn" onclick={() => (editing = null)}>취소</button>
						<button class="btn btn-primary" onclick={() => saveEdit(p)}>저장</button>
					</div>
				{:else}
					<p class="txt">{p.content}</p>
					<div class="row">
						<button class="btn" onclick={() => run(() => answerPrayer(p.id, p.status !== 'answered'), '바꾸지 못했어요.')}>{p.status === 'answered' ? '다시 기도 중으로' : '응답받았어요'}</button>
						<button class="ico" onclick={() => startEdit(p)} aria-label="수정" title="수정"><Icon name="edit" size={17} /></button>
						<button class="ico del" onclick={() => remove(p)} aria-label="삭제" title="삭제"><Icon name="trash" size={17} /></button>
					</div>
				{/if}
			</article>
		{:else}
			<p class="none">{data.status === 'answered' ? '아직 응답받은 기도 제목이 없어요.' : '기도 제목이 없어요.'}</p>
		{/each}
	</div>
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.form { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; max-width: 760px; }
	.buttons { display: flex; justify-content: flex-end; gap: 10px; }
	.err { color: var(--danger-ink); font-weight: 700; }
	.tabs { display: flex; gap: 8px; }
	.tab { height: 38px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--line-strong); background: var(--surface); font-weight: 700; color: var(--ink-2); }
	.tab.on { background: var(--accent); border-color: var(--accent); color: #fff; }
	.list { padding: 4px 20px; }
	article { border-top: 1px solid var(--line-soft); padding: 14px 0; display: flex; flex-direction: column; gap: 8px; }
	article:first-child { border-top: 0; }
	.top { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
	.who { font-weight: 700; color: var(--ink); }
	.meta { font-size: 12.5px; color: var(--muted); }
	.txt { white-space: pre-line; line-height: 1.7; }
	article.done .txt { color: var(--muted); }
	.row { display: flex; gap: 8px; align-items: center; }
	.ico { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line-strong); border-radius: 10px; background: var(--surface); color: var(--ink-2); }
	.ico:hover { background: var(--field-bg); }
	.ico.del { color: var(--danger-ink); }
	.none { color: var(--muted); padding: 24px; text-align: center; }
</style>
