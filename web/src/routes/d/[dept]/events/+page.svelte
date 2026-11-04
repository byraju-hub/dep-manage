<script lang="ts">
	import { goto } from '$app/navigation';
	import { ApiError, setParticipant } from '$lib/api';
	import { dotted } from '$lib/dates';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';
	import type { ParticipantRow } from '$lib/types';

	let { data } = $props();

	let rows = $state<ParticipantRow[]>([]);
	let error = $state('');
	let onlyApplied = $state(false);
	let saving = $state(0);

	// 선택한 행사가 바뀌면 표를 다시 채운다.
	$effect(() => {
		rows = (data.detail?.people ?? []).map((p) => ({ ...p }));
	});

	const shown = $derived(onlyApplied ? rows.filter((r) => r.applied) : rows);
	const applied = $derived(rows.filter((r) => r.applied));
	const paid = $derived(applied.filter((r) => r.paid));
	const total = $derived(applied.reduce((sum, r) => sum + (r.paid ? (r.amount ?? 0) : 0), 0));

	async function save(r: ParticipantRow) {
		if (!data.detail) return;
		error = '';
		saving++;
		try {
			await setParticipant(data.detail.event.id, r.type, r.id, { applied: r.applied, paid: r.paid && r.applied, amount: r.applied ? r.amount : null, note: r.applied ? r.note : '' });
		} catch (e) {
			error = e instanceof ApiError ? e.message : '저장하지 못했어요.';
		} finally {
			saving--;
		}
	}

	function setApplied(r: ParticipantRow, on: boolean) {
		r.applied = on;
		if (!on) {
			r.paid = false;
			r.amount = null;
			r.note = '';
		}
		void save(r);
	}

	const pick = (id: string) => goto(url(`/d/${data.dept.id}/events?year=${data.year}${id ? '&event=' + id : ''}`), { reset: false });
	const money = (n: number) => n.toLocaleString('ko-KR') + '원';
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>행사 참가</h1>
			<p class="sub">연간 계획에 있는 행사(수련회 등)의 참가 신청과 회비 납부를 확인해요. 체크하면 바로 저장돼요.</p>
		</div>
		<label class="field yr">
			<span class="sr-only">연도</span>
			<select class="input" value={data.year} onchange={(e) => goto(url(`/d/${data.dept.id}/events?year=${e.currentTarget.value}`), { reset: false })}>
				{#each [data.year - 1, data.year, data.year + 1] as y (y)}<option value={y}>{y}년</option>{/each}
			</select>
		</label>
	</header>

	<div class="card pickbox">
		<label class="field">
			<span>행사 고르기</span>
			<select class="input" value={data.selected?.id ?? ''} onchange={(e) => pick(e.currentTarget.value)}>
				<option value="">행사를 골라 주세요</option>
				{#each data.events as ev (ev.id)}<option value={ev.id}>{dotted(ev.date)} · {ev.title}</option>{/each}
			</select>
		</label>
		{#if data.events.length === 0}<p class="muted">{data.year}년 연간 계획에 일정이 없어요. 연간 계획에서 행사를 먼저 추가해 주세요.</p>{/if}
	</div>

	{#if error}<p class="err" role="alert">{error}</p>{/if}

	{#if data.detail}
		<div class="stats">
			<div class="card stat"><span>신청</span><b>{applied.length}명</b></div>
			<div class="card stat"><span>납부 완료</span><b>{paid.length}명</b></div>
			<div class="card stat"><span>미납</span><b>{applied.length - paid.length}명</b></div>
			<div class="card stat"><span>납부 합계</span><b>{money(total)}</b></div>
		</div>

		<label class="chk"><input type="checkbox" bind:checked={onlyApplied} /> 신청한 사람만 보기</label>

		<div class="card tablecard">
			<div class="scroll">
				<table>
					<caption class="sr-only">{data.detail.event.title} 참가자</caption>
					<thead>
						<tr><th scope="col">이름</th><th scope="col">구분</th><th scope="col">참가 신청</th><th scope="col">납부</th><th scope="col">금액(원)</th><th scope="col">메모</th></tr>
					</thead>
					<tbody>
						{#each shown as r (r.type + r.id)}
							<tr class:off={!r.applied}>
								<td class="strong">{r.name}</td>
								<td class="muted">{r.type === 'student' ? r.detail || terms.student : (r.detail || terms.teacher)}</td>
								<td><input type="checkbox" checked={r.applied} onchange={(e) => setApplied(r, e.currentTarget.checked)} aria-label="{r.name} 참가 신청" /></td>
								<td><input type="checkbox" bind:checked={r.paid} disabled={!r.applied} onchange={() => save(r)} aria-label="{r.name} 납부" /></td>
								<td><input class="input amt" type="number" min="0" step="1000" inputmode="numeric" disabled={!r.applied} bind:value={r.amount} onchange={() => save(r)} aria-label="{r.name} 금액" /></td>
								<td><input class="input" maxlength="100" disabled={!r.applied} bind:value={r.note} onchange={() => save(r)} aria-label="{r.name} 메모" /></td>
							</tr>
						{:else}
							<tr><td colspan="6" class="none">표시할 사람이 없어요.</td></tr>
						{/each}
					</tbody>
				</table>
			</div>
		</div>
		{#if saving > 0}<p class="muted">저장 중…</p>{/if}
	{/if}
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.err { color: var(--danger-ink); font-weight: 700; }
	.yr { width: 120px; }
	.pickbox { padding: 16px 20px; display: flex; flex-direction: column; gap: 8px; max-width: 640px; }
	.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
	.stat { padding: 14px 18px; display: flex; flex-direction: column; gap: 4px; }
	.stat span { font-size: 12.5px; color: var(--muted); }
	.stat b { font-size: 22px; }
	.chk { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
	.tablecard { padding: 4px 8px; }
	.scroll { overflow-x: auto; overflow-y: hidden; }
	table { width: 100%; border-collapse: collapse; min-width: 640px; }
	th { text-align: left; font-size: 13px; color: var(--muted); font-weight: 700; padding: 10px 8px; white-space: nowrap; }
	td { padding: 6px 8px; border-top: 1px solid var(--line-soft); vertical-align: middle; }
	tr.off td { color: var(--muted); }
	.strong { font-weight: 700; white-space: nowrap; }
	input[type='checkbox'] { width: 20px; height: 20px; }
	.amt { width: 110px; }
	.none { text-align: center; color: var(--muted); padding: 24px; }
	@media (max-width: 720px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
