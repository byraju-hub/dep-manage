<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, applyPromotions } from '$lib/api';
	import { dotted } from '$lib/dates';
	import { terms } from '$lib/terms.svelte';

	let { data } = $props();
	const plan = $derived(data.plan);

	let keepClass = $state(false);
	let confirming = $state(false);
	let busy = $state(false);
	let error = $state('');
	let done = $state<{ year: number; count: number } | null>(null);

	const toGraduate = $derived(plan.changes.filter((c) => c.graduate).length);
	const toEnroll = $derived(plan.changes.filter((c) => c.enroll).length);

	async function apply() {
		busy = true;
		error = '';
		try {
			done = await applyPromotions(data.dept.id, keepClass);
			confirming = false;
			await invalidateAll();
		} catch (e) {
			error = e instanceof ApiError ? e.message : '반영하지 못했어요.';
		} finally {
			busy = false;
		}
	}

	const chain = $derived(data.dept.gradeLevels.map((g) => g.label).join(' → '));
</script>

<section class="card box">
	<div>
		<h2>{plan.year}년 {terms.grade} 승급</h2>
		<p class="sub">{terms.student}을(를) 한 단계씩 올려요: <span class="chain">{chain}</span></p>
	</div>

	{#if plan.applied}
		<div class="ok" role="status">
			{plan.year}년 승급은 이미 반영되었어요. ({dotted(plan.applied.at.slice(0, 10))}, {plan.applied.count}명)<br />
			<span class="muted">같은 해에는 다시 할 수 없어요. 새해에 다시 열 수 있어요.</span>
		</div>
	{:else if done}
		<div class="ok" role="status">승급을 반영했어요. ({done.count}명)</div>
	{:else}
		<div class="warn">
			<b>미리보기예요.</b> 아래를 확인한 뒤 반영해 주세요. <b>한 번 반영하면 되돌릴 수 없어요.</b>
			{#if plan.month !== 1}
				<br />지금은 {plan.month}월이에요. 승급은 보통 1월에 한 번만 해요. 지금 반영하면 {plan.year}년 승급이 끝난 것으로 기록되어 {plan.year + 1}년 1월까지 다시 할 수 없어요.
			{/if}
		</div>
	{/if}

	{#if plan.changes.length}
		<ul class="sum">
			<li><b>{plan.changes.length}명</b>의 {terms.grade}이(가) 올라가요</li>
			{#if toGraduate}<li><b>{toGraduate}명</b>은 졸업하며 비활동 상태가 돼요</li>{/if}
			{#if toEnroll}<li><b>{toEnroll}명</b>은 {terms.newMember}(으)로 등록돼요 (예비 → 정식)</li>{/if}
		</ul>

		<div class="scroll">
			<table>
				<caption class="sr-only">승급 미리보기</caption>
				<thead><tr><th scope="col">이름</th><th scope="col">지금</th><th scope="col">승급 후</th></tr></thead>
				<tbody>
					{#each plan.changes as c (c.id)}
						<tr>
							<td class="strong">{c.name}</td>
							<td>{c.fromLabel}</td>
							<td><span class="badge badge-sq tone-{c.toTone}">{c.toLabel}</span>{#if c.graduate} <span class="muted">(비활동으로 바뀜)</span>{/if}{#if c.enroll} <span class="muted">({terms.newMember}으로 등록)</span>{/if}</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
	{:else if !plan.applied}
		<p class="muted">승급할 {terms.student}이(가) 없어요.</p>
	{/if}

	{#if !plan.applied && !done && plan.changes.length}
		<fieldset>
			<legend>{terms.class}은(는) 어떻게 할까요?</legend>
			<label class="radio"><input type="radio" name="kc" checked={!keepClass} onchange={() => (keepClass = false)} /> <span>비우고 새로 배정할게요 <span class="muted">(권장. {terms.class} 이름에 학년이 들어 있는 경우)</span></span></label>
			<label class="radio"><input type="radio" name="kc" checked={keepClass} onchange={() => (keepClass = true)} /> <span>그대로 유지할게요 <span class="muted">(고등 A반처럼 여러 {terms.grade}이 같은 {terms.class}을 쓰는 경우)</span></span></label>
			<p class="muted small">졸업하는 {terms.student}과(와) 예비 → 정식으로 올라가는 {terms.student}은(는) 항상 {terms.class}이(가) 비워져요.</p>
		</fieldset>

		{#if !confirming}
			<div><button class="btn btn-primary" onclick={() => (confirming = true)}>이 내용으로 승급하기…</button></div>
		{:else}
			<div class="confirm" role="alertdialog" aria-label="승급 확인">
				<p><b>{plan.changes.length}명의 {terms.grade}을(를) 한 단계씩 올려요.</b><br />되돌릴 수 없어요. 정말 진행할까요?</p>
				<div class="row">
					<button class="btn" onclick={() => (confirming = false)} disabled={busy}>취소</button>
					<button class="btn btn-primary" onclick={apply} disabled={busy}>{busy ? '반영 중…' : '정말 반영하기'}</button>
				</div>
			</div>
		{/if}
	{/if}
	{#if error}<p class="err" role="alert">{error}</p>{/if}
</section>

<style>
	.box { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; max-width: 860px; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13.5px; color: var(--muted); }
	.chain { color: var(--ink-2); font-weight: 600; }
	.ok { background: #ddf5e8; color: #0f6b40; border-radius: 12px; padding: 12px 16px; font-weight: 600; }
	.ok .muted { font-weight: 400; }
	.warn { background: #fff8ec; border: 1px solid #ffd9a8; color: #7a4a06; border-radius: 12px; padding: 12px 16px; line-height: 1.7; }
	.sum { margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 2px; }
	.scroll { overflow-x: auto; max-height: 420px; overflow-y: auto; border: 1px solid var(--line); border-radius: 12px; }
	table { width: 100%; border-collapse: collapse; font-size: 14px; }
	th { position: sticky; top: 0; background: var(--ground); text-align: left; font-size: 12.5px; color: var(--ink-2); padding: 8px 12px; }
	td { padding: 7px 12px; border-top: 1px solid var(--line-soft); }
	.strong { font-weight: 600; }
	fieldset { margin: 0; padding: 12px 14px; border: 1px solid var(--line); border-radius: 14px; display: flex; flex-direction: column; gap: 6px; }
	legend { padding: 0 6px; font-size: 13px; font-weight: 700; color: var(--ink-2); }
	.radio { display: flex; gap: 10px; align-items: flex-start; }
	.radio input { margin-top: 4px; accent-color: var(--accent); }
	.small { font-size: 12.5px; }
	.confirm { background: #fff5f7; border: 1px solid #f5b8c7; border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
	.row { display: flex; gap: 10px; }
	.err { color: var(--danger-ink); font-weight: 700; }
	button:disabled { opacity: 0.6; }
</style>
