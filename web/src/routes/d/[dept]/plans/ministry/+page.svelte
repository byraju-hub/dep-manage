<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { ApiError, saveMinistryPlan } from '$lib/api';
	import { can } from '$lib/session.svelte';
	import { url } from '$lib/url';
	import type { MinistryPlan, MinistryPlanItem } from '$lib/types';

	let { data } = $props();
	const canWrite = $derived(can(data.dept.id, 'admin'));

	const areas = ['예배', '교육', '전도', '양육', '교제', '봉사'];
	const statusLabel = { todo: '준비 전', doing: '진행 중', done: '완료' } as const;

	const blank = (year: number): MinistryPlan => ({ year, theme: '', themeVerse: '', vision: '', goals: '', status: 'draft', items: [] });
	let plan = $state<MinistryPlan>(blank(data.year));
	let saved = $state(false);
	let error = $state('');

	// 연도를 바꾸거나 저장한 뒤에는 서버의 내용으로 다시 채운다.
	$effect(() => {
		plan = data.plan ? { ...data.plan, items: data.plan.items.map((i) => ({ ...i })) } : blank(data.year);
		saved = false;
	});

	function year(delta: number) {
		void goto(`${url(`/d/${data.dept.id}/plans/ministry`)}?year=${data.year + delta}`);
	}

	function addItem(area: string) {
		const item: MinistryPlanItem = { area, title: '', description: '', period: '', owner: '', budget: 0, status: 'todo' };
		plan.items.push(item);
		saved = false;
	}

	async function save(e: SubmitEvent) {
		e.preventDefault();
		error = '';
		try {
			await saveMinistryPlan(data.dept.id, plan);
			await invalidateAll();
			saved = true;
		} catch (err) {
			error = err instanceof ApiError ? err.message : '저장하지 못했어요.';
		}
	}

	const total = $derived(plan.items.reduce((a, i) => a + (Number(i.budget) || 0), 0));
	const done = $derived(plan.items.filter((i) => i.status === 'done').length);
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>사역 계획</h1>
			<p class="sub">한 해의 주제와 목표, 영역별 계획을 세우고 진행 상태를 관리해요.</p>
		</div>
		<div class="yr" role="group" aria-label="연도">
			<button aria-label="이전 해" onclick={() => year(-1)}>‹</button>
			<strong>{data.year}년</strong>
			<button aria-label="다음 해" onclick={() => year(1)}>›</button>
		</div>
	</header>

	<form class="stack" onsubmit={save} novalidate>
		<section class="card box">
			<div class="two">
				<label class="field"><span>올해 주제</span><input class="input" bind:value={plan.theme} oninput={() => (saved = false)} disabled={!canWrite} /></label>
				<label class="field"><span>주제 말씀</span><input class="input" bind:value={plan.themeVerse} oninput={() => (saved = false)} disabled={!canWrite} placeholder="예: 빌립보서 4:13" /></label>
			</div>
			<label class="field"><span>비전</span><textarea class="input" rows="2" bind:value={plan.vision} oninput={() => (saved = false)} disabled={!canWrite}></textarea></label>
			<label class="field"><span>목표</span><textarea class="input" rows="3" bind:value={plan.goals} oninput={() => (saved = false)} disabled={!canWrite}></textarea></label>
		</section>

		<section class="card box">
			<div class="head">
				<h2>영역별 계획</h2>
				<span class="muted">{plan.items.length}개 · 완료 {done}개 · 예산 합계 {total.toLocaleString()}원</span>
			</div>

			{#each plan.items as it, i (i)}
				<div class="item">
					<select class="input" bind:value={it.area} aria-label="영역" disabled={!canWrite}>{#each areas as a (a)}<option value={a}>{a}</option>{/each}</select>
					<input class="input t" bind:value={it.title} placeholder="계획 이름" aria-label="계획 이름" disabled={!canWrite} />
					<input class="input" bind:value={it.period} placeholder="기간 (예: 3~5월)" aria-label="기간" disabled={!canWrite} />
					<input class="input" bind:value={it.owner} placeholder="담당" aria-label="담당" disabled={!canWrite} />
					<input class="input" type="number" min="0" bind:value={it.budget} placeholder="예산(원)" aria-label="예산" disabled={!canWrite} />
					<select class="input st-{it.status}" bind:value={it.status} aria-label="진행 상태" disabled={!canWrite}>{#each Object.entries(statusLabel) as [k, l] (k)}<option value={k}>{l}</option>{/each}</select>
					{#if canWrite}<button type="button" class="x" aria-label="이 계획 지우기" onclick={() => plan.items.splice(i, 1)}>×</button>{/if}
					<textarea class="input desc" rows="2" bind:value={it.description} placeholder="설명" aria-label="설명" disabled={!canWrite}></textarea>
				</div>
			{:else}
				<p class="muted">아직 계획이 없어요.</p>
			{/each}

			{#if canWrite}
				<div class="adds">
					<span class="muted">추가:</span>
					{#each areas as a (a)}<button type="button" class="chip" onclick={() => addItem(a)}>+ {a}</button>{/each}
				</div>
			{/if}
		</section>

		{#if canWrite}
			<div class="foot">
				{#if error}<span class="err" role="alert">{error}</span>{/if}
				{#if saved}<span class="okmsg" role="status">저장했어요</span>{/if}
				<button class="btn btn-primary" type="submit">저장</button>
			</div>
		{/if}
	</form>
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.yr { display: inline-flex; align-items: center; gap: 6px; }
	.yr button { width: 36px; height: 36px; border: 1px solid var(--line-strong); border-radius: 10px; background: var(--surface); font-size: 18px; }
	.yr strong { min-width: 72px; text-align: center; }
	.stack { display: flex; flex-direction: column; gap: 16px; }
	.box { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; }
	.two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
	.head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
	h2 { font-size: 18px; font-weight: 700; }
	.item { display: grid; grid-template-columns: 90px minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) 110px 110px 32px; gap: 8px; align-items: center; padding: 12px; border: 1px solid var(--line); border-radius: 14px; background: var(--field-bg); }
	.desc { grid-column: 1 / -1; background: var(--surface); }
	.x { width: 28px; height: 28px; border: 0; border-radius: 50%; background: transparent; color: var(--muted); font-size: 18px; }
	.st-done { color: var(--ok-ink); font-weight: 700; }
	.st-doing { color: #a04a07; font-weight: 700; }
	.adds { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
	.chip { height: 34px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--line-strong); background: var(--surface); font-weight: 600; color: var(--ink-2); }
	.foot { display: flex; justify-content: flex-end; align-items: center; gap: 12px; }
	.err { color: var(--danger-ink); font-weight: 700; }
	.okmsg { color: var(--ok-ink); font-weight: 700; }
	@media (max-width: 900px) { .item { grid-template-columns: 1fr 1fr; } .item .t { grid-column: 1 / -1; } .two { grid-template-columns: 1fr; } }
</style>
