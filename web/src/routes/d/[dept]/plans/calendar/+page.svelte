<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { ApiError, createEvent, createEventsBulk, deleteEvent, type EventInput } from '$lib/api';
	import { dotted, shortMD, today, weekdayLabels, fromISO } from '$lib/dates';
	import { eventKinds } from '$lib/palette';
	import { can } from '$lib/session.svelte';
	import { url } from '$lib/url';
	import type { EventKind } from '$lib/types';

	let { data } = $props();
	const canWrite = $derived(can(data.dept.id, 'admin'));

	let date = $state(today());
	let endDate = $state('');
	let kind = $state<EventKind>('ministry');
	let title = $state('');
	let prayer = $state('');
	let error = $state('');
	let message = $state('');

	// 엑셀에서 복사해 붙여넣기: 한 줄에 "날짜 [탭] 제목 [탭] 기도자(선택)"
	let paste = $state('');
	let showPaste = $state(false);

	const kindEntries = Object.entries(eventKinds) as Array<[EventKind, (typeof eventKinds)[EventKind]]>;
	const months = $derived(
		Array.from({ length: 12 }, (_, i) => ({
			month: i + 1,
			items: data.events.filter((e) => Number(e.date.slice(5, 7)) === i + 1)
		})).filter((m) => m.items.length)
	);

	function year(delta: number) {
		void goto(`${url(`/d/${data.dept.id}/plans/calendar`)}?year=${data.year + delta}`);
	}

	async function add(e: SubmitEvent) {
		e.preventDefault();
		error = message = '';
		if (!title.trim()) return void (error = '제목을 입력해 주세요.');
		try {
			await createEvent(data.dept.id, { date, endDate: endDate || null, kind, title: title.trim(), prayer, notes: '' });
			title = prayer = '';
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '저장하지 못했어요.';
		}
	}

	function parsePaste(text: string): { rows: EventInput[]; bad: number } {
		const rows: EventInput[] = [];
		let bad = 0;
		for (const line of text.split(/\r?\n/)) {
			if (!line.trim()) continue;
			const [d, t, p] = line.split('\t').map((x) => x.trim());
			// 2026-10-25, 2026.10.25, 2026/10/25, 10/25 모두 받는다 (연도가 없으면 보고 있는 연도).
			const m = d?.match(/^(?:(\d{4})[-./])?(\d{1,2})[-./](\d{1,2})\.?$/);
			if (!m || !t) {
				bad++;
				continue;
			}
			const iso = `${m[1] ?? data.year}-${m[2].padStart(2, '0')}-${m[3].padStart(2, '0')}`;
			rows.push({ date: iso, endDate: null, kind: 'ministry', title: t, prayer: p ?? '', notes: '' });
		}
		return { rows, bad };
	}

	async function importPaste() {
		error = message = '';
		const { rows, bad } = parsePaste(paste);
		if (!rows.length) return void (error = '읽을 수 있는 줄이 없어요. 날짜와 제목을 탭으로 나눠 붙여넣어 주세요.');
		try {
			const r = await createEventsBulk(data.dept.id, rows);
			message = `${r.saved}건을 넣었어요.${bad ? ` (읽지 못한 ${bad}줄은 건너뛰었어요.)` : ''}`;
			paste = '';
			showPaste = false;
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '저장하지 못했어요.';
		}
	}

	async function remove(id: number) {
		error = '';
		try {
			await deleteEvent(id);
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '지우지 못했어요.';
		}
	}

	const wd = (iso: string) => weekdayLabels[fromISO(iso).getDay()];
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>연간 계획</h1>
			<p class="sub">{data.dept.name}의 한 해 일정이에요. 여기서 넣은 일정은 대시보드의 사역 캘린더에도 나와요.</p>
		</div>
		<div class="yr" role="group" aria-label="연도">
			<button aria-label="이전 해" onclick={() => year(-1)}>‹</button>
			<strong>{data.year}년</strong>
			<button aria-label="다음 해" onclick={() => year(1)}>›</button>
		</div>
	</header>

	{#if canWrite}
		<form class="card add" onsubmit={add} novalidate>
			<label class="field"><span>날짜</span><input class="input" type="date" bind:value={date} /></label>
			<label class="field"><span>끝나는 날 (여러 날 행사)</span><input class="input" type="date" bind:value={endDate} min={date} /></label>
			<label class="field"><span>종류</span>
				<select class="input" bind:value={kind}>{#each kindEntries as [k, v] (k)}<option value={k}>{v.label}</option>{/each}</select>
			</label>
			<label class="field wide"><span>제목</span><input class="input" bind:value={title} placeholder="예: 주일 공과, 수련회" /></label>
			<label class="field"><span>기도자 (선택)</span><input class="input" bind:value={prayer} /></label>
			<div class="go"><button class="btn btn-primary" type="submit">일정 추가</button></div>
		</form>

		<div class="bulk">
			<button class="btn" onclick={() => (showPaste = !showPaste)}>{showPaste ? '붙여넣기 닫기' : '엑셀에서 붙여넣기'}</button>
			{#if showPaste}
				<div class="card paste">
					<p class="muted">엑셀에서 <b>날짜, 제목, 기도자</b> 칸을 복사해서 붙여넣으세요. (기도자는 없어도 돼요. 날짜가 <b>10/25</b>처럼 연도가 없으면 {data.year}년으로 넣어요.)</p>
					<textarea class="input" rows="6" bind:value={paste} placeholder={'2026-11-01\t주일 공과\t이상현 선생\n2026-11-08\t추수감사 예배'}></textarea>
					<div><button class="btn btn-primary" onclick={importPaste}>가져오기</button></div>
				</div>
			{/if}
		</div>
	{/if}

	{#if error}<p class="err" role="alert">{error}</p>{/if}
	{#if message}<p class="okmsg" role="status">{message}</p>{/if}

	{#each months as m (m.month)}
		<section class="card month">
			<h2>{m.month}월</h2>
			<ul>
				{#each m.items as ev (ev.id)}
					<li>
						<span class="when">{shortMD(ev.date)}<small>({wd(ev.date)})</small>{#if ev.endDate && ev.endDate !== ev.date} ~ {shortMD(ev.endDate)}{/if}</span>
						<span class="badge badge-sq tone-{eventKinds[ev.kind].tone}">{eventKinds[ev.kind].label}</span>
						<span class="t">{ev.title}</span>
						{#if ev.prayer}<span class="muted">기도 · {ev.prayer}</span>{/if}
						{#if canWrite}<button class="del" onclick={() => remove(ev.id)} aria-label="{dotted(ev.date)} {ev.title} 삭제">삭제</button>{/if}
					</li>
				{/each}
			</ul>
		</section>
	{:else}
		<div class="card none">{data.year}년 일정이 아직 없어요.</div>
	{/each}
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.yr { display: inline-flex; align-items: center; gap: 6px; }
	.yr button { width: 36px; height: 36px; border: 1px solid var(--line-strong); border-radius: 10px; background: var(--surface); font-size: 18px; }
	.yr strong { min-width: 72px; text-align: center; }
	.add { padding: 16px 20px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; align-items: end; }
	.wide { grid-column: span 2; }
	.go { display: flex; }
	.bulk { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
	.paste { padding: 16px 20px; width: 100%; display: flex; flex-direction: column; gap: 10px; }
	.err { color: var(--danger-ink); font-weight: 700; }
	.okmsg { color: var(--ok-ink); font-weight: 700; }
	.month { padding: 14px 20px; }
	.month h2 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
	ul { list-style: none; margin: 0; padding: 0; }
	li { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px solid var(--line-soft); flex-wrap: wrap; }
	li:first-child { border-top: 0; }
	.when { width: 120px; font-weight: 600; flex-shrink: 0; }
	.when small { color: var(--muted); font-weight: 400; margin-left: 2px; }
	.t { font-weight: 600; }
	.del { margin-left: auto; border: 0; background: transparent; color: var(--muted); font-size: 13px; text-decoration: underline; }
	.none { padding: 28px; text-align: center; color: var(--muted); }
	@media (max-width: 720px) { .add { grid-template-columns: 1fr 1fr; } .wide { grid-column: span 2; } .when { width: 100%; } }
</style>
