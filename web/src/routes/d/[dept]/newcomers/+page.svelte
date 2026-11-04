<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, setNewcomerStep } from '$lib/api';
	import { dotted } from '$lib/dates';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';
	import type { NewcomerRow, NewcomerStep } from '$lib/types';

	let { data } = $props();
	let error = $state('');
	let busy = $state(false);

	const steps: Array<{ key: NewcomerStep; label: string; hint: string }> = [
		{ key: 'welcome', label: '첫 방문 환영', hint: '처음 온 날 인사하고 정보를 받았어요' },
		{ key: 'attend', label: '2주 출석', hint: '두 주일 연속으로 나왔어요' },
		{ key: 'visit', label: terms.visit, hint: '심방(가정 방문·연락)을 했어요' },
		{ key: 'register', label: '정식 등록', hint: '체크하면 재적 학생으로 바뀌어요' }
	];

	async function toggle(n: NewcomerRow, step: NewcomerStep, done: boolean) {
		error = '';
		busy = true;
		try {
			await setNewcomerStep(n.id, step, done);
			await invalidateAll();
		} catch (e) {
			error = e instanceof ApiError ? e.message : '저장하지 못했어요.';
		} finally {
			busy = false;
		}
	}

	const auto = (n: NewcomerRow, step: NewcomerStep) => (step === 'attend' ? n.autoAttend : step === 'visit' ? n.autoVisit : false);
	const doneCount = (n: NewcomerRow) => steps.filter((s) => n.steps[s.key]).length;
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>새가족 정착</h1>
			<p class="sub">상태가 '새가족'인 {terms.student}이 정착하는 과정을 단계별로 확인해요. 정식 등록을 체크하면 재적 {terms.student}으로 바뀌어요.</p>
		</div>
	</header>

	{#if error}<p class="err" role="alert">{error}</p>{/if}

	{#if data.newcomers.length === 0}
		<div class="card none">지금 정착 중인 새가족이 없어요. {terms.student} 명단에서 상태를 '새가족'으로 두면 여기에 나와요.</div>
	{:else}
		<div class="list">
			{#each data.newcomers as n (n.id)}
				<article class="card item">
					<header>
						<a class="name" href={url(`/d/${data.dept.id}/students/${n.id}`)}>{n.name}</a>
						<span class="muted">{n.gradeLabel}{n.className ? ' · ' + n.className : ''}{n.school ? ' · ' + n.school : ''}</span>
						<span class="since">등록 {dotted(n.since)} · {doneCount(n)}/{steps.length} 단계</span>
					</header>
					<div class="steps">
						{#each steps as s (s.key)}
							{@const doneOn = n.steps[s.key]}
							<label class="step" class:on={!!doneOn} title={s.hint}>
								<input type="checkbox" checked={!!doneOn} disabled={busy} onchange={(e) => toggle(n, s.key, e.currentTarget.checked)} />
								<span class="lbl">{s.label}</span>
								{#if doneOn}
									<small>{dotted(doneOn)}</small>
								{:else if auto(n, s.key)}
									<small class="auto">자동 확인됨</small>
								{/if}
							</label>
						{/each}
					</div>
				</article>
			{/each}
		</div>
	{/if}
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.err { color: var(--danger-ink); font-weight: 700; }
	.none { padding: 28px; text-align: center; color: var(--muted); }
	.list { display: flex; flex-direction: column; gap: 12px; }
	.item { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
	header { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
	.name { font-size: 17px; font-weight: 700; color: var(--ink); }
	.since { margin-left: auto; font-size: 12.5px; color: var(--muted); }
	.steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
	.step { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border: 1px solid var(--line-strong); border-radius: 12px; background: var(--surface); cursor: pointer; }
	.step input { position: absolute; opacity: 0; pointer-events: none; }
	.step.on { background: var(--accent-soft); border-color: var(--accent); }
	.step:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 2px; }
	.lbl { font-weight: 700; font-size: 14px; }
	.step.on .lbl::before { content: '✓ '; color: var(--accent); }
	small { font-size: 12px; color: var(--muted); }
	small.auto { color: var(--ok-ink); font-weight: 700; }
	@media (max-width: 720px) { .steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } .since { margin-left: 0; width: 100%; } }
</style>
