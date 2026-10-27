<script lang="ts">
	import { goto } from '$app/navigation';
	import { age, dotted } from '$lib/dates';
	import Avatar from '$lib/components/Avatar.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import ViewTabs from '$lib/components/ViewTabs.svelte';
	import { avatarTone } from '$lib/palette';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';
	import type { StudentStatus, Tone } from '$lib/types';

	let { data } = $props();
	const dept = $derived(data.dept);

	const views = $derived([
		{ key: 'basic', label: '기본' },
		{ key: 'full', label: '상세(전체)' },
		{ key: 'class', label: `${terms.class}별` }
	]);

	function query(patch: Record<string, string | null>) {
		const p = new URLSearchParams();
		const next = { view: data.view, q: data.q, grade: data.grade ? String(data.grade) : '', status: data.status, ...patch };
		for (const [k, v] of Object.entries(next)) if (v && !(k === 'view' && v === 'basic') && !(k === 'status' && v === 'enrolled')) p.set(k, v);
		const s = p.toString();
		return url(`/d/${dept.id}/students`) + (s ? `?${s}` : '');
	}

	let timer: ReturnType<typeof setTimeout>;
	function onSearch(e: Event) {
		const value = (e.target as HTMLInputElement).value;
		clearTimeout(timer);
		timer = setTimeout(() => goto(query({ q: value }), { replaceState: true, keepFocus: true, noScroll: true }), 250);
	}

	const statusLabel: Record<StudentStatus, string> = { active: '재적', new: terms.newMember, inactive: '비활동' };
	const statusTone: Record<StudentStatus, Tone> = { active: 'green', new: 'orange', inactive: 'slate' };
	const toneOf = (gradeId: number): Tone => dept.gradeLevels.find((g) => g.id === gradeId)?.tone ?? 'slate';

	// 목록이 길면 15명씩 나눠 보여준다.
	const PAGE = 15;
	let pageNo = $state(1);
	$effect(() => {
		data.students; // 필터가 바뀌면 첫 쪽으로
		pageNo = 1;
	});
	const pages = $derived(Math.max(1, Math.ceil(data.students.length / PAGE)));
	const shown = $derived(data.students.slice((pageNo - 1) * PAGE, pageNo * PAGE));

	const counts = $derived({
		active: data.students.filter((s) => s.status === 'active').length,
		new: data.students.filter((s) => s.status === 'new').length
	});
	const guardian = (g: { relation: string; name: string; phone: string }[]) => g.map((x) => `${x.relation} ${x.name}`).join(' / ');
	/** "중1" + "중1 A반" → "중1 A", "고1" + "고등 B반" → "고1 B", 반 구분이 없으면 학년만 */
	const gradeClass = (grade: string, cls: string) => {
		const m = cls.match(/([A-Z])반$/);
		return m ? `${grade} ${m[1]}` : grade;
	};
</script>

{#snippet dots(recent: boolean[])}
	<span class="dots" role="img" aria-label="최근 4주 중 {recent.filter(Boolean).length}주 출석">
		{#each recent as on, i (i)}<i class:on></i>{/each}
	</span>
{/snippet}

<div class="page">
	<header class="page-head">
		<div>
			<h1>{terms.student} 명단</h1>
			<div class="chips">
				<span class="badge tone-green">재적 {counts.active}명</span>
				<span class="badge tone-orange">{terms.newMember} {counts.new}명</span>
				{#if data.status !== 'enrolled'}<span class="badge tone-slate">비활동 포함 보기</span>{/if}
			</div>
		</div>
		<div class="actions">
			<button class="btn"><span class="g"><Icon name="download" size={18} /></span>내보내기</button>
			<button class="btn btn-primary"><Icon name="plus" size={18} stroke={2.2} />{terms.student} 추가</button>
		</div>
	</header>

	<div class="bar">
		<ViewTabs {views} current={data.view} hrefFor={(k) => query({ view: k })} />
		<div class="filters">
			<label class="search">
				<span class="sr-only">이름 또는 학교 검색</span>
				<Icon name="search" size={18} />
				<input class="input" type="search" placeholder="이름 · 학교 검색" value={data.q} oninput={onSearch} />
			</label>
			<label>
				<span class="sr-only">{terms.grade} 선택</span>
				<select class="input sel" value={data.grade ?? ''} onchange={(e) => goto(query({ grade: e.currentTarget.value }), { noScroll: true })}>
					<option value="">{terms.grade}: 전체</option>
					{#each dept.gradeLevels.filter((g) => !g.isGraduate) as g (g.id)}<option value={g.id}>{g.label}</option>{/each}
				</select>
			</label>
			<label>
				<span class="sr-only">상태 선택</span>
				<select class="input sel" value={data.status} onchange={(e) => goto(query({ status: e.currentTarget.value }), { noScroll: true })}>
					<option value="enrolled">상태: 재적·{terms.newMember}</option>
					<option value="inactive">상태: 비활동</option>
					<option value="all">상태: 전체</option>
				</select>
			</label>
		</div>
	</div>

	{#if data.view === 'class'}
		<section class="classes">
			{#each data.classes as c (c.id)}
				<div class="card cls tone-{c.tone}">
					<div class="cls-head">
						<div class="cls-top">
							<h2>{c.name}</h2>
							<span class="count">{c.students.length}명</span>
						</div>
						<div class="sub">{terms.teacher} {c.leaders.length ? c.leaders.join(' · ') : '미정'}</div>
					</div>
					<ul>
						{#each c.students.slice(0, 6) as s (s.id)}
							<li>
								<a href={url(`/d/${dept.id}/students/${s.id}`)}>
									<Avatar name={s.name} tone={c.tone} size={28} />
									<span class="nm">{s.name}</span>
									{#if s.status === 'new'}<span class="badge badge-sq tone-orange sm">{terms.newMember}</span>{/if}
								</a>
							</li>
						{/each}
					</ul>
					{#if c.students.length > 6}
						<a class="more" href={query({ view: 'basic' })}>외 {c.students.length - 6}명 보기</a>
					{:else}
						<a class="more" href={url(`/d/${dept.id}/attendance`)}>출석 체크 열기</a>
					{/if}
				</div>
			{/each}
		</section>
	{:else}
		<div class="card tablecard">
			<div class="scroll">
				<table class:full={data.view === 'full'}>
					<caption class="sr-only">{terms.student} 명단</caption>
					<thead>
						<tr>
							<th scope="col" class="first">이름</th>
							<th scope="col">{terms.grade} · {terms.class}</th>
							<th scope="col">성별</th>
							{#if data.view === 'full'}
								<th scope="col">생년월일</th>
								<th scope="col">연락처</th>
							{/if}
							<th scope="col">학교</th>
							<th scope="col">상태</th>
							{#if data.view === 'full'}
								<th scope="col">세례</th>
								<th scope="col">인도자</th>
								<th scope="col">보호자</th>
							{/if}
							<th scope="col">최근 4주 출석</th>
							<th scope="col" class="end"><span class="sr-only">상세</span></th>
						</tr>
					</thead>
					<tbody>
						{#each shown as s, i (s.id)}
							<tr>
								<td class="first">
									<a class="who" href={url(`/d/${dept.id}/students/${s.id}`)}>
										<Avatar name={s.name} index={(pageNo - 1) * PAGE + i} />
										<span class="strong">{s.name}</span>
									</a>
								</td>
								<td><span class="badge badge-sq tone-{toneOf(s.gradeId)}">{gradeClass(s.gradeLabel, s.className)}</span></td>
								<td class="c2">{s.gender}</td>
								{#if data.view === 'full'}
									<td class="c2">{dotted(s.birthDate)} <span class="muted">({age(s.birthDate)}세)</span></td>
									<td class="c2 nowrap">{s.phone}</td>
								{/if}
								<td class="c2">{s.school}</td>
								<td><span class="badge tone-{statusTone[s.status]}">{statusLabel[s.status]}</span></td>
								{#if data.view === 'full'}
									<td class="c2">{s.baptism}</td>
									<td class="c2">{s.mentor || '-'}</td>
									<td class="c2 nowrap">{guardian(s.guardians)}</td>
								{/if}
								<td>{@render dots(s.recent)}</td>
								<td class="end"><a href={url(`/d/${dept.id}/students/${s.id}`)} aria-label="{s.name} 상세"><Icon name="chevronRight" size={18} /></a></td>
							</tr>
						{:else}
							<tr><td colspan="12" class="none">조건에 맞는 {terms.student}이(가) 없습니다.</td></tr>
						{/each}
					</tbody>
				</table>
			</div>
		</div>

		<div class="foot">
			<div class="legend">
				<span>{data.students.length}명 중 {data.students.length ? (pageNo - 1) * PAGE + 1 : 0}–{Math.min(pageNo * PAGE, data.students.length)} · {terms.grade} → {terms.class} → 이름 순</span>
				<span class="lg"><i class="dot on"></i>출석</span>
				<span class="lg"><i class="dot"></i>결석</span>
			</div>
			<div class="pager">
				<button aria-label="이전 쪽" disabled={pageNo === 1} onclick={() => pageNo--}>‹</button>
				{#each Array.from({ length: pages }, (_, i) => i + 1) as n (n)}
					<button class:cur={n === pageNo} aria-current={n === pageNo ? 'page' : undefined} onclick={() => (pageNo = n)}>{n}</button>
				{/each}
				<button aria-label="다음 쪽" disabled={pageNo === pages} onclick={() => pageNo++}>›</button>
			</div>
		</div>
	{/if}
</div>

<style>
	.g { color: var(--ok-ink); display: inline-flex; }
	.chips { display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
	.bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
	.filters { display: flex; gap: 10px; flex-wrap: wrap; }
	.search { position: relative; display: block; }
	.search :global(svg) { position: absolute; left: 12px; top: 11px; color: var(--muted); }
	.search .input { width: 240px; height: 40px; padding-left: 38px; }
	.sel { width: auto; height: 40px; font-weight: 500; }

	.tablecard { overflow: hidden; }
	.scroll { overflow-x: auto; }
	table { width: 100%; border-collapse: collapse; font-size: 14px; }
	table.full { min-width: 1180px; }
	thead tr { background: var(--ground); text-align: left; color: var(--ink-2); font-size: 12.5px; }
	th { padding: 12px 10px; font-weight: 600; white-space: nowrap; }
	td { padding: 8px 10px; border-top: 1px solid var(--line-soft); }
	.first { padding-left: 20px; }
	.end { padding-right: 20px; width: 32px; text-align: right; }
	.end :global(svg) { color: var(--muted); }
	.c2 { color: var(--ink-2); }
	.nowrap { white-space: nowrap; }
	.who { display: flex; align-items: center; gap: 12px; }
	.strong { font-weight: 600; }
	.none { text-align: center; color: var(--muted); padding: 32px; }
	tbody tr:hover { background: var(--field-bg); }

	.dots { display: inline-flex; gap: 6px; align-items: center; }
	.dots i, .dot { width: 12px; height: 12px; box-sizing: border-box; border-radius: 50%; border: 2px solid var(--danger); background: var(--surface); display: inline-block; }
	.dots i.on, .dot.on { background: var(--ok); border-color: var(--ok); }

	.foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 13px; color: var(--muted); }
	.legend { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
	.lg { display: inline-flex; align-items: center; gap: 6px; }
	.pager { display: flex; gap: 6px; }
	.pager button { min-width: 36px; height: 36px; border: 1px solid var(--line-strong); border-radius: 10px; background: var(--surface); font-weight: 600; }
	.pager button.cur { background: var(--accent); border-color: transparent; color: #fff; font-weight: 700; }
	.pager button:disabled { opacity: 0.4; cursor: default; }

	.classes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
	.cls { overflow: hidden; display: flex; flex-direction: column; }
	.cls-head { background: var(--bg); padding: 14px 18px 12px; display: flex; flex-direction: column; gap: 2px; }
	.cls-top { display: flex; align-items: center; justify-content: space-between; }
	.cls h2 { font-size: 17px; font-weight: 700; color: var(--fg); }
	.count { font-size: 12.5px; font-weight: 700; color: #fff; background: var(--fg); border-radius: 999px; padding: 2px 10px; }
	.sub { font-size: 12.5px; color: var(--ink-2); }
	.cls ul { list-style: none; margin: 0; padding: 6px 18px 0; }
	.cls li a { display: flex; align-items: center; gap: 10px; padding: 6px 0; border-top: 1px solid var(--line-soft); }
	.cls li:first-child a { border-top: 0; }
	.nm { flex-grow: 1; font-weight: 500; }
	.sm { font-size: 11.5px; padding: 1px 8px; }
	.more { margin: 4px 18px 12px; padding: 8px 0 2px; border-top: 1px solid var(--line-soft); font-size: 13px; font-weight: 700; color: var(--fg); }

	@media (max-width: 1000px) { .classes { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
	@media (max-width: 640px) {
		.classes { grid-template-columns: 1fr; }
		.search, .search .input { width: 100%; }
		.filters { width: 100%; }
		.filters label { flex: 1; }
		.sel { width: 100%; }
	}
</style>
