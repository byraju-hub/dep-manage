<script lang="ts">
	import { goto } from '$app/navigation';
	import { downloadStats } from '$lib/excel';
	import { shortMD } from '$lib/dates';
	import Avatar from '$lib/components/Avatar.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';

	let { data } = $props();
	const s = $derived(data.stats);
	const teachersMode = $derived(data.who === 'teachers');
	const who = $derived(teachersMode ? terms.teacher : terms.student);

	type Sort = 'order' | 'low' | 'high';
	let sort = $state<Sort>('order');
	const people = $derived(
		[...s.people].sort((a, b) => (sort === 'order' ? 0 : ((a.rate ?? -1) - (b.rate ?? -1)) * (sort === 'low' ? 1 : -1)))
	);

	function go(patch: { who?: string; weeks?: number }) {
		const p = new URLSearchParams();
		const w = patch.who ?? data.who;
		if (w === 'teachers') p.set('who', 'teachers');
		p.set('weeks', String(patch.weeks ?? data.weeks));
		void goto(`${url(`/d/${data.dept.id}/attendance/stats`)}?${p}`, { noScroll: true });
	}

	/** 출석률에 따라 막대 색을 정한다. 숫자는 항상 같이 보여서 색만으로 구분하지 않는다. */
	const level = (r: number | null) => (r === null ? 'none' : r >= 80 ? 'good' : r >= 50 ? 'mid' : 'low');

	async function exportXlsx() {
		const head = teachersMode ? ['이름', '직분', '출석', '온라인', '결석', '출석률(%)'] : ['이름', terms.grade, terms.class, '출석', '온라인', '결석', '출석률(%)'];
		const rows = s.people.map((p) => (teachersMode ? [p.name, p.gradeLabel, p.present, p.online, p.absent, p.rate] : [p.name, p.gradeLabel, p.className, p.present, p.online, p.absent, p.rate]));
		await downloadStats(`${data.dept.name} ${who} 출석률 (${s.from} ~ ${s.to}, 모임 ${s.checkedWeeks}회)`, head, rows, `${data.dept.name}_${who}_출석률_${s.to}.xlsx`);
	}
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>출석률</h1>
			<p class="sub"><a class="back" href={url(`/d/${data.dept.id}/attendance${teachersMode ? '?who=teachers' : ''}`)}>← 출석 체크</a></p>
		</div>
		<div class="actions">
			<button class="btn" onclick={exportXlsx} disabled={!s.people.length}><Icon name="download" size={18} />엑셀로 내려받기</button>
		</div>
	</header>

	<div class="bar">
		<div class="tabs" role="tablist" aria-label="대상">
			<button role="tab" aria-selected={!teachersMode} class:on={!teachersMode} onclick={() => go({ who: 'students' })}>{terms.student}</button>
			<button role="tab" aria-selected={teachersMode} class:on={teachersMode} onclick={() => go({ who: 'teachers' })}>{terms.teacher}</button>
		</div>
		<label class="period">
			<span class="sr-only">기간</span>
			<select class="input" value={data.weeks} onchange={(e) => go({ weeks: Number(e.currentTarget.value) })}>
				{#each [4, 8, 12, 26, 52] as w (w)}<option value={w}>최근 {w}주</option>{/each}
			</select>
		</label>
	</div>

	{#if s.checkedWeeks === 0}
		<div class="card none">이 기간에는 출석 체크 기록이 없어요. 출석 체크 화면에서 저장하면 여기에 나타나요.</div>
	{:else}
		<section class="stats">
			<div class="card stat"><span>전체 출석률</span><strong class="big {level(s.overall.rate)}">{s.overall.rate ?? '-'}<small>%</small></strong></div>
			<div class="card stat"><span>모임이 있었던 주</span><strong>{s.checkedWeeks}<small>회</small></strong></div>
			<div class="card stat"><span>출석 · 온라인 · 결석</span><strong class="trio"><b class="g">{s.overall.present}</b> · <b class="v">{s.overall.online}</b> · <b class="r">{s.overall.absent}</b></strong></div>
		</section>

		{#if !teachersMode && s.classes.length}
			<section class="card box">
				<h2>{terms.class}별 출석률</h2>
				<ul class="bars">
					{#each s.classes as c (c.id)}
						<li class="tone-{c.tone}">
							<span class="nm">{c.name}</span>
							<span class="track"><i class={level(c.rate)} style:width="{c.rate ?? 0}%"></i></span>
							<span class="val">{c.rate ?? '-'}%</span>
							<span class="muted cnt">{c.members}명</span>
						</li>
					{/each}
				</ul>
			</section>
		{/if}

		<section class="card box">
			<div class="head">
				<h2>{who}별 출석률</h2>
				<label class="sortsel">
					<span class="sr-only">정렬</span>
					<select class="input" bind:value={sort}>
						<option value="order">기본 순서</option>
						<option value="low">출석률 낮은 순</option>
						<option value="high">출석률 높은 순</option>
					</select>
				</label>
			</div>
			<ul class="people">
				{#each people as p, i (p.id)}
					<li>
						<Avatar name={p.name} tone={p.tone} index={i} size={32} />
						<div class="who">
							<span class="name tone-{p.tone}">{p.name}</span>
							<span class="muted meta">{p.gradeLabel}{p.className ? ` · ${p.className}` : ''}</span>
						</div>
						<span class="track"><i class={level(p.rate)} style:width="{p.rate ?? 0}%"></i></span>
						<span class="val">{p.rate ?? '-'}%</span>
						<span class="muted cnt" title="출석 {p.present} · 온라인 {p.online} · 결석 {p.absent}">{p.present + p.online}/{s.checkedWeeks}</span>
					</li>
				{/each}
			</ul>
		</section>

		<section class="card box">
			<h2>주별 출석 인원</h2>
			<ol class="weeks">
				{#each s.byDate as d (d.date)}
					<li><span class="d">{shortMD(d.date)}</span><span class="track"><i class="good" style:width="{d.recorded ? (d.attended / d.recorded) * 100 : 0}%"></i></span><span class="val">{d.attended}/{d.recorded}명</span></li>
				{/each}
			</ol>
		</section>

		<p class="muted note">출석률 = (출석 + 온라인) ÷ 출석 체크를 한 주의 수. 체크하지 않은 주는 모임이 없었던 것으로 보고 세지 않아요. 체크한 주에 기록이 없는 사람은 결석으로 세요.</p>
	{/if}
</div>

<style>
	.sub { font-size: 13.5px; }
	.back { color: var(--accent); font-weight: 700; }
	.bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
	.tabs { display: inline-flex; background: #e6ecfa; border-radius: 12px; padding: 4px; gap: 2px; }
	.tabs button { padding: 7px 22px; border: 0; border-radius: 9px; background: transparent; font-weight: 600; color: var(--ink-2); }
	.tabs button.on { background: var(--surface); color: var(--accent); font-weight: 700; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.12); }
	.period .input { width: auto; height: 40px; }
	.none { padding: 28px; text-align: center; color: var(--muted); }

	.stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
	.stat { padding: 14px 18px; display: flex; flex-direction: column; }
	.stat span { font-size: 13px; color: var(--ink-2); }
	.stat strong { font-size: 28px; line-height: 1.3; }
	.stat small { font-size: 14px; font-weight: 600; margin-left: 2px; }
	.big.good { color: var(--ok-ink); }
	.big.mid { color: #a04a07; }
	.big.low { color: var(--danger-ink); }
	.trio { font-size: 24px; }
	.trio .g { color: var(--ok-ink); } .trio .v { color: #5b21b6; } .trio .r { color: var(--danger-ink); }

	.box { padding: 18px 22px; display: flex; flex-direction: column; gap: 10px; }
	.box h2 { font-size: 16px; font-weight: 700; }
	.head { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
	.sortsel .input { width: auto; height: 36px; }
	ul, ol { list-style: none; margin: 0; padding: 0; }
	.bars li, .people li, .weeks li { display: grid; align-items: center; gap: 10px; padding: 6px 0; border-top: 1px solid var(--line-soft); }
	.bars li:first-child, .people li:first-child, .weeks li:first-child { border-top: 0; }
	.bars li { grid-template-columns: 110px minmax(0, 1fr) 48px 48px; }
	.people li { grid-template-columns: 32px minmax(0, 150px) minmax(0, 1fr) 48px 56px; }
	.weeks li { grid-template-columns: 56px minmax(0, 1fr) 80px; }
	.nm { font-weight: 600; color: var(--fg); }
	.name { font-weight: 600; color: var(--fg); display: block; }
	.who { min-width: 0; line-height: 1.3; }
	.meta { font-size: 12px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.track { height: 10px; border-radius: 5px; background: #eef2fb; overflow: hidden; display: block; }
	.track i { display: block; height: 100%; border-radius: 5px; background: var(--accent); }
	.track i.good { background: #0f8a55; } .track i.mid { background: #d97706; } .track i.low { background: #e11d48; }
	.val { font-weight: 700; text-align: right; font-size: 13.5px; }
	.cnt { font-size: 12.5px; text-align: right; }
	.d { color: var(--muted); font-size: 13px; }
	.note { font-size: 12.5px; line-height: 1.6; }
	button:disabled { opacity: 0.5; }

	@media (max-width: 720px) {
		.stats { grid-template-columns: 1fr; }
		.bars li { grid-template-columns: 90px minmax(0, 1fr) 44px; }
		.bars .cnt { display: none; }
		.people li { grid-template-columns: 32px minmax(0, 1fr) 90px 44px; }
		.people .cnt { display: none; }
	}
</style>
