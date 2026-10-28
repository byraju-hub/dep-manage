<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, transferStudents } from '$lib/api';
	import { can } from '$lib/session.svelte';
	import { terms } from '$lib/terms.svelte';
	import type { StudentStatus } from '$lib/types';

	let { data } = $props();

	// 양쪽 부서의 관리자여야 옮길 수 있으니, 내가 관리하는 다른 부서만 보여 준다. (서버도 다시 확인한다.)
	const targets = $derived(data.depts.filter((d) => d.id !== data.dept.id && can(d.id, 'admin')));

	let showAll = $state(false);
	let q = $state('');
	let picked = $state<Set<number>>(new Set());
	let toDeptId = $state<number | null>(null);
	let gradeId = $state<number | null>(null);
	let status = $state<StudentStatus>('new');
	let busy = $state(false);
	let error = $state('');
	let done = $state<{ moved: number; to: string } | null>(null);

	// 기본은 졸업했거나 쉬는 사람. 필요하면 전체에서 고를 수 있다.
	const list = $derived(data.students.filter((s) => (showAll || s.status === 'inactive') && (!q.trim() || s.name.includes(q.trim()))));
	const target = $derived(targets.find((d) => d.id === toDeptId) ?? null);

	function pickDept(id: number | null) {
		toDeptId = id;
		gradeId = targets.find((d) => d.id === id)?.gradeLevels[0]?.id ?? null;
	}
	function toggle(id: number) {
		const n = new Set(picked);
		if (n.has(id)) n.delete(id);
		else n.add(id);
		picked = n;
	}
	const allOn = $derived(list.length > 0 && list.every((s) => picked.has(s.id)));
	function toggleAll() {
		picked = allOn ? new Set([...picked].filter((id) => !list.some((s) => s.id === id))) : new Set([...picked, ...list.map((s) => s.id)]);
	}

	async function move() {
		if (!target || !gradeId || !picked.size) return;
		if (!confirm(`${picked.size}명을 '${target.name}'(으)로 옮길까요?\n옮긴 뒤에는 이 부서 명단에서 사라지고, 반 배정은 비워져요.`)) return;
		busy = true;
		error = '';
		try {
			const r = await transferStudents(data.dept.id, { studentIds: [...picked], toDeptId: target.id, gradeId, status });
			done = { moved: r.moved, to: target.name };
			picked = new Set();
			await invalidateAll();
		} catch (e) {
			error = e instanceof ApiError ? e.message : '옮기지 못했어요.';
		} finally {
			busy = false;
		}
	}
</script>

<div class="stack">
	<section class="card box">
		<h2>{terms.student} 부서 이동 · 졸업</h2>
		<p class="sub">졸업하거나 진급해서 다른 부서로 가는 {terms.student}을(를) 옮겨요. 출석 기록은 따라가고, <b>심방 기록은 지금 부서에 남아서</b> 옮겨 간 부서에서는 보이지 않아요. 옮기려면 양쪽 부서의 관리자여야 해요.</p>
		{#if !targets.length}
			<p class="muted">옮겨 갈 다른 부서가 없어요. 교회 관리자가 설정 → 부서 관리에서 부서를 먼저 추가해 주세요.</p>
		{/if}
		{#if done}<p class="okmsg" role="status">{done.moved}명을 '{done.to}'(으)로 옮겼어요.</p>{/if}
		{#if error}<p class="err" role="alert">{error}</p>{/if}
	</section>

	{#if targets.length}
		<section class="card box">
			<h3>1. 옮겨 갈 곳</h3>
			<div class="make">
				<label class="field"><span>부서</span>
					<select class="input" value={toDeptId ?? ''} onchange={(e) => pickDept(Number(e.currentTarget.value) || null)}>
						<option value="">부서를 골라 주세요</option>
						{#each targets as d (d.id)}<option value={d.id}>{d.name}</option>{/each}
					</select>
				</label>
				{#if target}
					<label class="field"><span>{target.terms.grade}</span>
						<select class="input" bind:value={gradeId}>{#each target.gradeLevels as g (g.id)}<option value={g.id}>{g.label}</option>{/each}</select>
					</label>
					<label class="field"><span>상태</span>
						<select class="input" bind:value={status}><option value="new">{target.terms.newMember}</option><option value="active">재적</option><option value="inactive">쉬는 중</option></select>
					</label>
				{/if}
			</div>
		</section>

		<section class="card box">
			<h3>2. 옮길 {terms.student} 고르기</h3>
			<div class="bar">
				<input class="input" placeholder="이름 검색" bind:value={q} aria-label="이름 검색" />
				<label class="chk"><input type="checkbox" bind:checked={showAll} /> 재학 중인 {terms.student}도 보기</label>
				<span class="muted">{picked.size}명 선택</span>
			</div>
			{#if list.length}
				<div class="scroll">
					<table>
						<thead><tr><th><input type="checkbox" checked={allOn} onchange={toggleAll} aria-label="전체 선택" /></th><th>이름</th><th>{terms.grade}</th><th>상태</th></tr></thead>
						<tbody>
							{#each list as s (s.id)}
								<tr>
									<td><input type="checkbox" checked={picked.has(s.id)} onchange={() => toggle(s.id)} aria-label="{s.name} 선택" /></td>
									<td class="strong">{s.name}</td>
									<td>{s.gradeLabel}</td>
									<td>{s.status === 'inactive' ? '졸업 · 쉬는 중' : s.status === 'new' ? terms.newMember : '재적'}</td>
								</tr>
							{/each}
						</tbody>
					</table>
				</div>
			{:else}
				<p class="muted">{showAll ? '해당하는 사람이 없어요.' : '졸업했거나 쉬는 중인 사람이 없어요. 재학 중인 사람도 보려면 위 상자를 눌러 주세요.'}</p>
			{/if}
			<div><button class="btn btn-primary" disabled={busy || !picked.size || !target || !gradeId} onclick={move}>{picked.size}명 옮기기</button></div>
		</section>
	{/if}
</div>

<style>
	.stack { display: flex; flex-direction: column; gap: 16px; }
	.box { padding: 20px 24px; display: flex; flex-direction: column; gap: 12px; }
	h2 { font-size: 18px; font-weight: 700; }
	h3 { font-size: 15px; font-weight: 700; }
	.sub { font-size: 13.5px; color: var(--muted); }
	.err { color: var(--danger-ink); font-weight: 700; }
	.okmsg { color: var(--ok-ink); font-weight: 700; }
	.make { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
	.bar { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
	.bar .input { max-width: 220px; }
	.chk { display: flex; align-items: center; gap: 6px; font-size: 14px; }
	.scroll { overflow-x: auto; max-height: 420px; overflow-y: auto; }
	table { width: 100%; border-collapse: collapse; font-size: 14px; }
	th { text-align: left; font-size: 12.5px; color: var(--ink-2); padding: 8px; font-weight: 600; position: sticky; top: 0; background: var(--surface); }
	td { padding: 8px; border-top: 1px solid var(--line-soft); }
	.strong { font-weight: 600; }
</style>
