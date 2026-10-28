<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { ApiError, completeFollowUp, saveVisit, searchPeople } from '$lib/api';
	import { addDays, dotted, shortMD, today } from '$lib/dates';
	import Icon from '$lib/components/Icon.svelte';
	import { methodTone, visitMethods } from '$lib/palette';
	import { session } from '$lib/session.svelte';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';
	import type { VisitMethod, VisitTarget, VisitVisibility } from '$lib/types';

	let { data } = $props();
	const dept = $derived(data.dept);

	// ===== 새 기록 폼 =====
	let targets = $state<Array<VisitTarget & { name: string }>>([]);
	let date = $state(today());
	let method = $state<VisitMethod>('가정');
	let companions = $state('');
	let title = $state('');
	let content = $state('');
	let followUp = $state('');
	let followUpDue = $state('');
	let visibility = $state<VisitVisibility>('pastors');
	let error = $state('');
	let ok = $state('');

	// 대상 검색
	let q = $state('');
	let results = $state<Awaited<ReturnType<typeof searchPeople>>>([]);
	$effect(() => {
		const k = q;
		searchPeople(dept.id, k).then((r) => (results = r.filter((p) => !targets.some((t) => t.type === p.type && t.id === p.id))));
	});
	function addTarget(p: { type: 'student' | 'teacher'; id: number; name: string }) {
		targets.push({ type: p.type, id: p.id, name: p.name });
		q = '';
	}

	async function submit(e: SubmitEvent) {
		e.preventDefault();
		error = '';
		if (!targets.length) return (error = '대상을 한 명 이상 추가해 주세요.');
		if (!title.trim()) return (error = '제목을 입력해 주세요.');
		try {
			await saveVisit(dept.id, {
				date,
				targets: targets.map(({ type, id }) => ({ type, id })),
				method,
				companions,
				title: title.trim(),
				content,
				followUp: followUp.trim(),
				followUpDue: followUp.trim() && followUpDue ? followUpDue : null,
				visibility
			});
		} catch (err) {
			return (error = err instanceof ApiError ? err.message : '저장하지 못했어요.');
		}
		ok = `${terms.visit} 기록을 저장했어요.`;
		targets = [];
		title = content = followUp = followUpDue = companions = '';
		await invalidateAll();
	}

	async function done(id: number) {
		await completeFollowUp(id);
		await invalidateAll();
	}

	const visLabels: Array<{ key: VisitVisibility; label: string; hint?: string }> = [
		{ key: 'private', label: '나만 (작성자)' },
		{ key: 'pastors', label: `${terms.pastor} · ${terms.head}`, hint: '기본값' },
		{ key: 'staff', label: `부서 ${terms.teacher} 전체` }
	];
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>{terms.visit}</h1>
			<p class="sub">목록 {data.visits.length}건 · 후속 조치 {data.pending}건 남음</p>
		</div>
	</header>

	<div class="cols">
		<section class="card list" aria-label="{terms.visit} 목록">
			<div class="filters">
				<a class="f" class:on={data.filter === 'all'} href={url(`/d/${dept.id}/visits`)}>전체</a>
				<a class="f warn" class:on={data.filter === 'pending'} href={url(`/d/${dept.id}/visits?filter=pending`)}>후속 조치 남음</a>
			</div>
			<ul>
				{#each data.visits as v (v.id)}
					<li class="item">
						<div class="top">
							<strong>{v.targetNames.length > 1 ? `${v.targetNames[0]} 외 ${v.targetNames.length - 1}명` : v.targetNames[0]}</strong>
							<span class="date">{shortMD(v.date)}</span>
						</div>
						<div class="mid">
							<span class="badge badge-sq tone-{methodTone[v.method]}">{v.method}</span>
							<span class="t">{v.title}</span>
							{#if v.visibility !== 'staff'}<span class="lock" title="열람 범위가 제한된 기록"><Icon name="lock" size={14} /></span>{/if}
						</div>
						{#if v.followUp && !v.followUpDone}
							<div class="fu">
								<span class="badge tone-orange">후속 조치 {v.followUpDue ? shortMD(v.followUpDue) : ''} · {v.followUp}</span>
								<button class="mini" onclick={() => done(v.id)}>완료</button>
							</div>
						{/if}
					</li>
				{:else}
					<li class="none">{terms.visit} 기록이 없습니다.</li>
				{/each}
			</ul>
		</section>

		<section class="card form" aria-label="새 {terms.visit} 기록">
			<h2>새 {terms.visit} 기록</h2>
			<form onsubmit={submit} novalidate>
				<div class="field">
					<span class="field-label">대상</span>
					<div class="targets">
						{#each targets as t, i (t.type + t.id)}
							<span class="tag">{t.name}<button type="button" aria-label="{t.name} 제거" onclick={() => targets.splice(i, 1)}>×</button></span>
						{/each}
						<div class="find">
							<input class="input" type="search" placeholder="이름으로 {terms.student} · {terms.teacher} 찾기" bind:value={q} aria-label="대상 검색" />
							{#if q && results.length}
								<ul class="pop" role="listbox">
									{#each results as p (p.type + p.id)}
										<li><button type="button" role="option" aria-selected="false" onclick={() => addTarget(p)}>{p.name} <span class="muted">{p.type === 'student' ? terms.student : terms.teacher}</span></button></li>
									{/each}
								</ul>
							{/if}
						</div>
					</div>
				</div>

				<div class="two">
					<label class="field"><span>날짜</span><input class="input" type="date" bind:value={date} max={addDays(today(), 0)} /></label>
					<div class="field">
						<span class="field-label">방법</span>
						<div class="methods" role="radiogroup" aria-label="방법">
							{#each visitMethods as m (m)}
								<button type="button" role="radio" aria-checked={method === m} class="m tone-{methodTone[m]}" class:on={method === m} onclick={() => (method = m)}>{m}</button>
							{/each}
						</div>
					</div>
				</div>

				<label class="field"><span>함께한 {terms.teacher}</span><input class="input" bind:value={companions} /></label>
				<label class="field"><span>제목</span><input class="input" bind:value={title} /></label>
				<label class="field"><span>내용</span><textarea class="input" rows="4" bind:value={content}></textarea></label>

				<div class="two fu2">
					<label class="field"><span>후속 조치</span><input class="input" bind:value={followUp} /></label>
					<label class="field"><span>기한</span><input class="input" type="date" bind:value={followUpDue} min={today()} /></label>
				</div>

				<fieldset>
					<legend>누가 볼 수 있나요?</legend>
					{#each visLabels as v (v.key)}
						<label class="radio"><input type="radio" name="vis" value={v.key} bind:group={visibility} /> <span>{v.label}{#if v.hint} <span class="muted small">{v.hint}</span>{/if}</span></label>
					{/each}
				</fieldset>

				{#if error}<p class="err" role="alert">{error}</p>{/if}
				{#if ok}<p class="okmsg" role="status">{ok}</p>{/if}
				<div class="buttons">
					<button type="button" class="btn" onclick={() => goto(url(`/d/${dept.id}`))}>취소</button>
					<button type="submit" class="btn btn-primary">저장</button>
				</div>
			</form>
		</section>
	</div>
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.cols { display: grid; grid-template-columns: 380px minmax(0, 1fr); gap: 16px; align-items: start; }
	.list { padding: 14px 14px 6px; display: flex; flex-direction: column; gap: 8px; }
	.filters { display: flex; gap: 8px; flex-wrap: wrap; }
	.f { height: 34px; padding: 0 14px; border-radius: 999px; background: #eef2fb; color: var(--ink-2); font-weight: 700; font-size: 13.5px; display: inline-flex; align-items: center; }
	.f.warn { background: #ffebd0; color: #a04a07; }
	.f.on { background: var(--accent); color: #fff; }
	.f.warn.on { background: #a04a07; }
	ul { list-style: none; margin: 0; padding: 0; }
	.item { display: flex; flex-direction: column; gap: 3px; padding: 11px 10px; border-top: 1px solid var(--line-soft); }
	.top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
	.date { font-size: 12.5px; color: var(--muted); }
	.mid { display: flex; align-items: center; gap: 8px; }
	.t { font-size: 13.5px; color: var(--ink-2); }
	.lock { color: var(--muted); display: inline-flex; }
	.fu { display: flex; align-items: center; gap: 8px; margin-top: 2px; flex-wrap: wrap; }
	.mini { height: 28px; padding: 0 10px; border-radius: 9px; border: 1px solid var(--line-strong); background: var(--surface); font-size: 12.5px; font-weight: 700; color: var(--ok-ink); }
	.none { color: var(--muted); padding: 24px; text-align: center; }

	.form { padding: 22px 26px; }
	.form h2 { font-size: 18px; font-weight: 700; margin-bottom: 12px; }
	form { display: flex; flex-direction: column; gap: 16px; }
	.two { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 16px; }
	.fu2 { grid-template-columns: minmax(0, 1fr) 200px; }
	.targets { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
	.tag { display: inline-flex; align-items: center; gap: 4px; height: 34px; padding: 0 6px 0 12px; border-radius: 999px; background: #ffe3ee; color: #be185d; font-weight: 700; }
	.tag button { width: 24px; height: 24px; border: 0; border-radius: 50%; background: transparent; color: #be185d; font-size: 16px; line-height: 1; }
	.find { position: relative; flex: 1; min-width: 200px; }
	.pop { position: absolute; z-index: 10; left: 0; right: 0; top: calc(100% + 4px); background: var(--surface); border: 1px solid var(--line-strong); border-radius: var(--radius-sm); padding: 6px; box-shadow: 0 8px 24px rgba(23, 32, 42, 0.12); }
	.pop button { width: 100%; text-align: left; padding: 9px 10px; border: 0; border-radius: 8px; background: transparent; }
	.pop button:hover { background: var(--field-bg); }
	.methods { display: flex; gap: 6px; flex-wrap: wrap; }
	.m { height: 34px; padding: 0 14px; border-radius: 999px; border: 0; background: var(--bg); color: var(--fg); font-weight: 700; font-size: 13.5px; }
	.m.on { background: var(--fg); color: #fff; }

	fieldset { margin: 0; padding: 12px 14px; border: 1px solid #ffd9a8; border-radius: 14px; background: #fff8ec; display: flex; flex-direction: column; gap: 6px; }
	legend { padding: 0 6px; font-size: 13px; font-weight: 700; color: #a04a07; }
	.radio { display: flex; align-items: center; gap: 10px; min-height: 32px; }
	.radio input { accent-color: var(--accent); width: 18px; height: 18px; margin: 0; }
	.small { font-size: 12.5px; }
	.err { color: var(--danger-ink); font-weight: 600; }
	.okmsg { color: var(--ok-ink); font-weight: 700; }
	.buttons { display: flex; justify-content: flex-end; gap: 10px; }

	@media (max-width: 1000px) {
		.cols { grid-template-columns: 1fr; }
	}
	@media (max-width: 560px) {
		.form { padding: 18px 16px; }
		.two, .fu2 { grid-template-columns: 1fr; }
	}
</style>
