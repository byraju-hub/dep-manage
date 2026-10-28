<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, createClass, createGradeLevel, deleteClass, deleteGradeLevel, updateClass, updateGradeLevel } from '$lib/api';
	import { terms } from '$lib/terms.svelte';
	import type { Tone } from '$lib/types';

	let { data } = $props();

	const tones: Tone[] = ['blue', 'green', 'orange', 'pink', 'violet', 'teal', 'sky', 'amber', 'slate'];
	const toneName: Record<Tone, string> = { blue: '파랑', green: '초록', orange: '주황', pink: '분홍', violet: '보라', teal: '청록', sky: '하늘', amber: '노랑', slate: '회색' };

	let error = $state('');
	let newGrade = $state('');
	let newClass = $state('');

	async function act(fn: () => Promise<unknown>) {
		error = '';
		try {
			await fn();
			await invalidateAll();
		} catch (e) {
			error = e instanceof ApiError ? e.message : '처리하지 못했어요.';
		}
	}

	const nextTone = (i: number): Tone => tones[i % 8];
</script>

{#if error}<p class="err" role="alert">{error}</p>{/if}

<div class="stack">
	<section class="card box">
		<div>
			<h2>{terms.grade} 단계</h2>
			<p class="sub">해가 바뀔 때 이 순서대로 올라가요. 색은 이 단계의 {terms.student} 이름과 표시에 쓰여요.</p>
		</div>
		<ul class="list">
			{#each data.dept.gradeLevels as g (g.id)}
				<li>
					<input class="input name" value={g.label} aria-label="{g.label} 이름" onchange={(e) => act(() => updateGradeLevel(g.id, { label: e.currentTarget.value, tone: (g.tone ?? 'slate') as Tone }))} />
					<select class="input tone tone-{g.tone ?? 'slate'}" value={g.tone ?? 'slate'} aria-label="{g.label} 색" onchange={(e) => act(() => updateGradeLevel(g.id, { label: g.label, tone: e.currentTarget.value as Tone }))}>
						{#each tones as t (t)}<option value={t}>{toneName[t]}</option>{/each}
					</select>
					<button class="btn sm" onclick={() => act(() => deleteGradeLevel(g.id))}>삭제</button>
				</li>
			{/each}
		</ul>
		<form class="add" onsubmit={(e) => { e.preventDefault(); if (newGrade.trim()) act(async () => { await createGradeLevel(data.dept.id, { label: newGrade.trim(), tone: nextTone(data.dept.gradeLevels.length) }); newGrade = ''; }); }}>
			<input class="input" placeholder="새 단계 이름 (예: 고3)" bind:value={newGrade} aria-label="새 단계 이름" />
			<button class="btn btn-primary" type="submit">단계 추가</button>
		</form>
	</section>

	<section class="card box">
		<div>
			<h2>{terms.class}</h2>
			<p class="sub">{terms.student}을(를) 나누는 {terms.class}이에요. 이름과 색을 바꾸거나 새로 만들 수 있어요. 지우면 그 {terms.class}의 {terms.student}은(는) {terms.class} 없음 상태가 돼요.</p>
		</div>
		<ul class="list">
			{#each data.classes as c (c.id)}
				<li>
					<input class="input name" value={c.name} aria-label="{c.name} 이름" onchange={(e) => act(() => updateClass(c.id, { name: e.currentTarget.value, tone: c.tone }))} />
					<select class="input tone tone-{c.tone}" value={c.tone} aria-label="{c.name} 색" onchange={(e) => act(() => updateClass(c.id, { name: c.name, tone: e.currentTarget.value as Tone }))}>
						{#each tones as t (t)}<option value={t}>{toneName[t]}</option>{/each}
					</select>
					<span class="muted cnt">{c.students.length}명</span>
					<button class="btn sm" onclick={() => act(() => deleteClass(c.id))}>삭제</button>
				</li>
			{:else}
				<li class="none">아직 {terms.class}이(가) 없어요.</li>
			{/each}
		</ul>
		<form class="add" onsubmit={(e) => { e.preventDefault(); if (newClass.trim()) act(async () => { await createClass(data.dept.id, { name: newClass.trim(), tone: nextTone(data.classes.length) }); newClass = ''; }); }}>
			<input class="input" placeholder="새 {terms.class} 이름 (예: 중1 A반)" bind:value={newClass} aria-label="새 {terms.class} 이름" />
			<button class="btn btn-primary" type="submit">{terms.class} 추가</button>
		</form>
	</section>
</div>

<style>
	.stack { display: flex; flex-direction: column; gap: 16px; }
	.box { padding: 20px 24px; display: flex; flex-direction: column; gap: 12px; max-width: 760px; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13px; color: var(--muted); }
	.err { color: var(--danger-ink); font-weight: 700; }
	.list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
	.list li { display: flex; align-items: center; gap: 8px; }
	.name { flex: 1; min-width: 0; }
	.tone { width: 96px; color: var(--fg); background: var(--bg); font-weight: 600; }
	.cnt { width: 44px; text-align: right; font-size: 13px; }
	.btn.sm { height: 36px; padding: 0 12px; font-size: 13px; }
	.add { display: flex; gap: 8px; }
	.add .input { flex: 1; }
	.none { color: var(--muted); }
</style>
