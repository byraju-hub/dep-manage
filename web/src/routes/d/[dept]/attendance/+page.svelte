<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { ApiError, photoUrl, saveAttendance, saveTeacherAttendance } from '$lib/api';
	import { addDays, fullDate, longMD } from '$lib/dates';
	import Avatar from '$lib/components/Avatar.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import { attendanceColor } from '$lib/palette';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';
	import type { AttendanceStatus, Tone } from '$lib/types';

	let { data } = $props();
	const dept = $derived(data.dept);
	const teachersMode = $derived(data.who === 'teachers');

	interface Item {
		id: number;
		name: string;
		meta: string;
		tone: Tone | undefined;
		photo: string | null;
		status: AttendanceStatus;
		classId: number | null;
	}

	// 학생과 교사를 같은 모양으로 만들어 한 화면에서 다룬다.
	const all = $derived<Item[]>(
		teachersMode
			? (data.tatt?.teachers ?? []).map((t) => ({ id: t.id, name: t.name, meta: [t.position, t.officerRole].filter(Boolean).join(' · '), tone: 'violet' as Tone, photo: photoUrl('teacher', t.id, t.photo, true), status: t.status, classId: null }))
			: (data.att?.students ?? []).map((s) => ({
					id: s.id,
					name: s.name,
					meta: `${s.gradeLabel}${s.className ? ` · ${s.className}` : ''}${s.status === 'new' ? ` · ${terms.newMember}` : ''}`,
					tone: s.tone,
					photo: photoUrl('student', s.id, s.photo, true),
					status: s.attendance,
					classId: s.classId
				}))
	);
	const date = $derived(data.date);
	const classes = $derived(data.att?.classes ?? []);

	// 저장하기 전까지는 화면에서만 바뀌는 임시 값
	let draft = $state<Record<number, AttendanceStatus>>({});
	let saved = $state(false);
	let saveError = $state('');
	$effect(() => {
		draft = Object.fromEntries(all.map((i) => [i.id, i.status]));
		saved = false;
		saveError = '';
	});

	const list = $derived(all.filter((i) => !data.cls || i.classId === data.cls));
	const count = (st: AttendanceStatus) => list.filter((i) => (draft[i.id] ?? 'present') === st).length;
	const total = $derived(list.length);
	const pct = (n: number) => (total ? `${(n / total) * 100}%` : '0%');

	function go(patch: { date?: string; cls?: number | null; who?: 'students' | 'teachers' }) {
		const p = new URLSearchParams();
		const who = patch.who ?? data.who;
		p.set('date', patch.date ?? data.date);
		if (who === 'teachers') p.set('who', 'teachers');
		const cls = patch.cls === undefined ? data.cls : patch.cls;
		if (cls && who === 'students') p.set('class', String(cls));
		void goto(`${url(`/d/${dept.id}/attendance`)}?${p}`, { noScroll: true });
	}

	function set(id: number, st: AttendanceStatus) {
		draft[id] = st;
		saved = false;
	}

	async function save() {
		saveError = '';
		const entries = Object.entries(draft).map(([id, st]) => [Number(id), st] as [number, AttendanceStatus]);
		try {
			await (teachersMode ? saveTeacherAttendance(dept.id, date, entries) : saveAttendance(dept.id, date, entries));
			saved = true;
			await invalidateAll();
		} catch (err) {
			saveError = err instanceof ApiError ? err.message : '저장하지 못했어요.';
		}
	}

	const choices: Array<{ st: AttendanceStatus; label: string }> = [
		{ st: 'present', label: '출석' },
		{ st: 'online', label: '온라인' },
		{ st: 'absent', label: '결석' }
	];
	const who = $derived(teachersMode ? terms.teacher : terms.student);
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>출석 체크</h1>
			<p class="sub">{fullDate(data.date)} · {dept.name}</p>
		</div>
		<div class="actions">
			<a class="btn" href={url(`/d/${dept.id}/attendance/stats${teachersMode ? '?who=teachers' : ''}`)}><Icon name="calendar" size={18} />출석률 보기</a>
			<button class="btn btn-primary desk" onclick={save}><Icon name="check" size={18} />저장하기</button>
		</div>
	</header>

	<div class="tabs" role="tablist" aria-label="출석 대상">
		<button role="tab" aria-selected={!teachersMode} class:on={!teachersMode} onclick={() => go({ who: 'students', cls: null })}>{terms.student}</button>
		<button role="tab" aria-selected={teachersMode} class:on={teachersMode} onclick={() => go({ who: 'teachers', cls: null })}>{terms.teacher}</button>
	</div>

	<div class="date" role="group" aria-label="날짜 선택">
		<button aria-label="이전 주" onclick={() => go({ date: addDays(data.date, -7) })}><Icon name="chevronLeft" size={20} /></button>
		<strong>{longMD(data.date)}</strong>
		<button aria-label="다음 주" onclick={() => go({ date: addDays(data.date, 7) })}><Icon name="chevronRight" size={20} /></button>
	</div>

	{#if !teachersMode}
		<div class="chips" role="group" aria-label="{terms.class} 선택">
			<button class:on={!data.cls} onclick={() => go({ cls: null })}>전체</button>
			{#each classes as c (c.id)}
				<button class:on={data.cls === c.id} onclick={() => go({ cls: c.id })}>{c.name}</button>
			{/each}
		</div>
	{/if}

	<section class="summary" aria-live="polite">
		<div class="box tone-green"><span>출석</span><strong>{count('present')}<small> 명</small></strong></div>
		<div class="box tone-violet"><span>온라인</span><strong>{count('online')}<small> 명</small></strong></div>
		<div class="box tone-pink"><span>결석</span><strong>{count('absent')}<small> 명</small></strong></div>
	</section>
	<div class="bar" aria-hidden="true">
		<i style:width={pct(count('present'))} style:background={attendanceColor.present}></i>
		<i style:width={pct(count('online'))} style:background={attendanceColor.online}></i>
		<i style:width={pct(count('absent'))} style:background={attendanceColor.absent}></i>
	</div>

	<ul class="rows">
		{#each list as s, i (s.id)}
			{@const cur = draft[s.id] ?? 'present'}
			<li class="row">
				<Avatar name={s.name} tone={s.tone} index={i} size={40} src={s.photo} />
				<div class="nm">
					<div class="n tone-{s.tone ?? 'slate'}">{s.name}</div>
					<div class="m">{s.meta}</div>
				</div>
				<div class="seg" role="group" aria-label="{s.name} 출석 상태">
					{#each choices as c (c.st)}
						<button class="s-{c.st}" class:on={cur === c.st} aria-pressed={cur === c.st} onclick={() => set(s.id, c.st)}>{c.label}</button>
					{/each}
				</div>
			</li>
		{:else}
			<li class="none">{teachersMode ? `활동 중인 ${who}이(가) 없어요.` : `이 ${terms.class}에 ${who}이(가) 없어요.`}</li>
		{/each}
	</ul>

	<div class="savebar">
		{#if saved}<span class="ok" role="status">저장했어요</span>{/if}
		{#if saveError}<span class="bad" role="alert">{saveError}</span>{/if}
		<button class="btn btn-primary btn-lg btn-block" onclick={save}>저장하기</button>
	</div>
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.tabs { display: inline-flex; background: #e6ecfa; border-radius: 12px; padding: 4px; gap: 2px; width: fit-content; }
	.tabs button { padding: 7px 22px; border: 0; border-radius: 9px; background: transparent; font-weight: 600; color: var(--ink-2); }
	.tabs button.on { background: var(--surface); color: var(--accent); font-weight: 700; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.12); }
	.date { display: flex; align-items: center; justify-content: space-between; background: #eef2fb; border-radius: var(--radius-sm); padding: 4px; max-width: 420px; }
	.date button { width: 44px; height: 40px; border: 0; background: transparent; display: flex; align-items: center; justify-content: center; border-radius: 10px; }
	.date button:hover { background: rgba(255, 255, 255, 0.7); }
	.chips { display: flex; gap: 8px; flex-wrap: wrap; }
	.chips button { height: 36px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--line-strong); background: var(--surface); font-weight: 600; color: var(--ink-2); }
	.chips button.on { background: var(--accent); border-color: transparent; color: #fff; }

	.summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; max-width: 520px; }
	.box { background: var(--bg); color: var(--fg); border-radius: var(--radius-sm); padding: 8px 12px; line-height: 1.25; display: flex; flex-direction: column; }
	.box span { font-size: 12.5px; font-weight: 600; }
	.box strong { font-size: 22px; }
	.box small { font-size: 13px; font-weight: 600; }
	.bar { display: flex; height: 8px; border-radius: 4px; overflow: hidden; gap: 2px; background: #eef2fb; max-width: 520px; }

	.rows { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
	.row { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 8px 8px 8px 10px; display: flex; align-items: center; gap: 10px; min-height: 62px; }
	.nm { flex-grow: 1; min-width: 0; line-height: 1.35; }
	.n { font-weight: 600; font-size: 16px; white-space: nowrap; color: var(--fg); }
	.m { font-size: 12.5px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.none { color: var(--muted); padding: 24px; text-align: center; }

	.seg { display: flex; gap: 2px; background: #eef2fb; border-radius: var(--radius-sm); padding: 3px; flex-shrink: 0; }
	.seg button { width: 50px; height: 40px; border: 0; border-radius: 9px; background: transparent; color: var(--ink-2); font-size: 13px; font-weight: 700; padding: 0; }
	.seg .s-present.on { background: var(--ok); color: #fff; }
	.seg .s-online.on { background: var(--online); color: #fff; }
	.seg .s-absent.on { background: var(--danger); color: #fff; }

	.savebar { display: flex; align-items: center; gap: 12px; }
	.ok { color: var(--ok-ink); font-weight: 700; white-space: nowrap; }
	.bad { color: var(--danger-ink); font-weight: 700; font-size: 13px; }
	.savebar :global(.btn) { max-width: 360px; }
	.desk { display: none; }

	@media (min-width: 901px) {
		.desk { display: inline-flex; }
		.savebar { display: none; }
		.rows { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
	}
	@media (max-width: 900px) {
		.savebar { position: sticky; bottom: calc(76px + env(safe-area-inset-bottom)); z-index: 5; background: var(--ground); padding: 8px 0; }
	}
</style>
