<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { ApiError, archiveDepartment, createDepartment, deleteDepartment, orderDepartments } from '$lib/api';
	import { presets } from '$lib/presets';
	import { session } from '$lib/session.svelte';
	import { url } from '$lib/url';
	import Tile from '$lib/components/Tile.svelte';

	let { data } = $props();

	// 부서 보관·순서·삭제
	let manageError = $state('');
	const active = $derived(data.all.filter((d) => !d.archived));
	const archived = $derived(data.all.filter((d) => d.archived));
	let deleting = $state<number | null>(null);
	let deleteName = $state('');

	async function manage(fn: () => Promise<unknown>) {
		manageError = '';
		try {
			await fn();
			await invalidateAll();
		} catch (e) {
			manageError = e instanceof ApiError ? e.message : '처리하지 못했어요.';
		}
	}
	function move(i: number, dir: -1 | 1) {
		const ids = active.map((d) => d.id);
		const j = i + dir;
		if (j < 0 || j >= ids.length) return;
		[ids[i], ids[j]] = [ids[j], ids[i]];
		void manage(() => orderDepartments(ids));
	}

	const kinds = presets.filter((p) => p.key !== 'later');
	let kind = $state(kinds[2].key);
	let name = $state('');
	let pastorName = $state('');
	let headName = $state('');
	let busy = $state(false);
	let error = $state('');
	let made = $state<{ id: number; name: string } | null>(null);

	const preset = $derived(kinds.find((k) => k.key === kind) ?? kinds[0]);
	const shownName = $derived(name.trim() || (kind === 'custom' ? '' : preset.name));

	async function add() {
		error = '';
		if (!shownName) {
			error = '부서 이름을 적어 주세요.';
			return;
		}
		busy = true;
		try {
			const d = await createDepartment({
				name: shownName,
				pastorName: pastorName.trim(),
				headName: headName.trim(),
				terms: { ...preset.terms, department: shownName },
				grades: preset.grades
			});
			made = { id: d.id, name: d.name };
			name = pastorName = headName = '';
			await invalidateAll();
		} catch (e) {
			error = e instanceof ApiError ? e.message : '부서를 만들지 못했어요.';
		} finally {
			busy = false;
		}
	}
</script>

{#if !session.user?.isOwner}
	<div class="card none">부서를 만들고 관리하는 일은 교회 관리자만 할 수 있어요.</div>
{:else}
	<div class="stack">
		<section class="card box">
			<h2>부서 목록</h2>
			<p class="sub">교회 관리자는 모든 부서를 볼 수 있어요. 다른 사람은 <b>소속된 부서만</b> 보여요. (사용자 탭에서 부서마다 역할을 정해요.)</p>
			<ul class="list">
				{#each active as d, i (d.id)}
					<li>
						<span class="n">{d.name}</span>
						<span class="muted">{d.gradeLevels.length}개 {d.terms.grade} · {d.terms.student} {d.students}명 · {d.pastorName || '담당 교역자 미정'}</span>
						<span class="acts">
							<button class="btn sm" onclick={() => move(i, -1)} disabled={i === 0} aria-label="{d.name} 위로">▲</button>
							<button class="btn sm" onclick={() => move(i, 1)} disabled={i === active.length - 1} aria-label="{d.name} 아래로">▼</button>
							{#if d.id !== data.dept.id}<a class="btn sm" href={url(`/d/${d.id}/settings`)}>설정 열기</a>{:else}<span class="badge tone-blue">지금 보는 부서</span>{/if}
							<button class="btn sm" onclick={() => manage(() => archiveDepartment(d.id, true))} disabled={active.length < 2} title="목록에서 숨겨요. 자료는 그대로 남아요.">보관</button>
						</span>
					</li>
				{/each}
			</ul>
			{#if manageError}<p class="err" role="alert">{manageError}</p>{/if}
		</section>

		{#if archived.length}
			<section class="card box">
				<h2>보관한 부서</h2>
				<p class="sub">보관한 부서는 전환 메뉴에서 숨겨지고 자료는 그대로 남아 있어요. 다시 꺼내거나, 완전히 지울 수 있어요.</p>
				<ul class="list">
					{#each archived as d (d.id)}
						<li>
							<span class="n">{d.name}</span>
							<span class="muted">{d.terms.student} {d.students}명 · {d.terms.teacher} {d.teachers}명</span>
							<span class="acts">
								<button class="btn sm" onclick={() => manage(() => archiveDepartment(d.id, false))}>다시 꺼내기</button>
								<button class="btn sm danger" onclick={() => { deleting = d.id; deleteName = ''; }}>완전히 지우기</button>
							</span>
						</li>
						{#if deleting === d.id}
							<li class="confirmrow">
								<p class="warn"><b>{d.name}</b>의 {d.terms.student}·{d.terms.teacher}·출석·심방·사진 등 <b>모든 자료가 지워지고 되돌릴 수 없어요.</b> 먼저 백업을 받아 두세요. 계속하려면 부서 이름을 똑같이 입력하세요.</p>
								<input class="input" bind:value={deleteName} placeholder={d.name} autocomplete="off" />
								<button class="btn" onclick={() => (deleting = null)}>취소</button>
								<button class="btn danger solid" disabled={deleteName !== d.name} onclick={() => manage(async () => { await deleteDepartment(d.id, deleteName); deleting = null; })}>지우기</button>
							</li>
						{/if}
					{/each}
				</ul>
			</section>
		{/if}

		<section class="card box">
			<h2>부서 추가</h2>
			<p class="sub">종류를 고르면 용어({preset.terms.student}·{preset.terms.teacher}·{preset.terms.class})와 {preset.terms.grade} 구분이 미리 채워져요. 만든 뒤에 설정에서 얼마든지 바꿀 수 있어요.</p>
			<div class="kinds" role="radiogroup" aria-label="부서 종류">
				{#each kinds as k (k.key)}
					<button type="button" role="radio" aria-checked={kind === k.key} class="kind" class:on={kind === k.key} onclick={() => (kind = k.key)}>
						<Tile icon={k.icon} tone={k.tone} size={34} iconSize={19} />
						<span><b>{k.name}</b><small>{k.range}</small></span>
					</button>
				{/each}
			</div>
			<form class="make" onsubmit={(e) => { e.preventDefault(); void add(); }}>
				<label class="field"><span>부서 이름</span><input class="input" bind:value={name} placeholder={kind === 'custom' ? '예: 새벽기도 청소년' : preset.name} maxlength="40" /></label>
				<label class="field"><span>담당 교역자 (선택)</span><input class="input" bind:value={pastorName} maxlength="50" /></label>
				<label class="field"><span>{preset.terms.head} (선택)</span><input class="input" bind:value={headName} maxlength="50" /></label>
				<button class="btn btn-primary" type="submit" disabled={busy}>{busy ? '만드는 중…' : '부서 만들기'}</button>
			</form>
			{#if preset.grades.length}<p class="sub">{preset.terms.grade}: {preset.grades.join(' → ')}</p>{/if}
			{#if error}<p class="err" role="alert">{error}</p>{/if}
			{#if made}
				<p class="okmsg" role="status"><b>{made.name}</b>을(를) 만들었어요. <button class="link" onclick={() => goto(url(`/d/${made?.id}`))}>바로 가기</button></p>
			{/if}
		</section>
	</div>
{/if}

<style>
	.stack { display: flex; flex-direction: column; gap: 16px; }
	.box { padding: 20px 24px; display: flex; flex-direction: column; gap: 12px; }
	.none { padding: 28px; color: var(--muted); text-align: center; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13.5px; color: var(--muted); }
	.err { color: var(--danger-ink); font-weight: 700; }
	.okmsg { color: var(--ok-ink); font-weight: 600; }
	.link { border: 0; background: none; color: var(--accent); font-weight: 700; text-decoration: underline; padding: 0; }
	.list { list-style: none; margin: 0; padding: 0; }
	.list li { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px solid var(--line-soft); flex-wrap: wrap; }
	.list li:first-child { border-top: 0; }
	.n { font-weight: 700; min-width: 110px; }
	.acts { margin-left: auto; display: inline-flex; gap: 6px; align-items: center; flex-wrap: wrap; }
	.confirmrow { flex-direction: column; align-items: stretch; gap: 8px; background: var(--field-bg); border-radius: 12px; padding: 12px 14px; }
	.warn { color: var(--danger-ink); font-size: 13.5px; line-height: 1.6; }
	.btn.danger { color: var(--danger-ink); }
	.btn.danger.solid { background: var(--danger-ink); color: #fff; }
	.btn.sm { height: 32px; padding: 0 12px; font-size: 13px; display: inline-flex; align-items: center; }
	.kinds { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 8px; }
	.kind { display: flex; align-items: center; gap: 10px; text-align: left; padding: 10px 12px; border: 1px solid var(--line-strong); border-radius: var(--radius-sm); background: var(--surface); color: var(--ink); }
	.kind small { display: block; font-size: 12px; color: var(--muted); }
	.kind.on { border-color: var(--accent); background: var(--accent-soft); box-shadow: 0 0 0 1px var(--accent); }
	.make { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: end; }
</style>
