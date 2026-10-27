<script lang="ts">
	import { getCalendar } from '../api';
	import { addDays, fromISO, longMD, monthKey, toISO, today, weekdayLabels } from '../dates';
	import { eventKinds } from '../palette';
	import type { CalendarItem, EventKind } from '../types';
	import Icon from './Icon.svelte';

	let { deptId }: { deptId: number } = $props();

	const todayISO = today();
	let month = $state(monthKey(todayISO)); // YYYY-MM
	let items = $state<CalendarItem[]>([]);
	let selected = $state(todayISO);

	$effect(() => {
		let cancelled = false;
		getCalendar(deptId, month).then((r) => {
			if (!cancelled) items = r;
		});
		return () => {
			cancelled = true;
		};
	});

	function shift(delta: number) {
		const [y, m] = month.split('-').map(Number);
		month = toISO(new Date(y, m - 1 + delta, 1)).slice(0, 7);
	}
	function goToday() {
		month = monthKey(todayISO);
		selected = todayISO;
	}

	const title = $derived.by(() => {
		const [y, m] = month.split('-').map(Number);
		return `${y}년 ${m}월`;
	});

	interface Cell {
		date: string;
		day: number;
		inMonth: boolean;
		col: number;
		items: CalendarItem[];
	}

	const cells = $derived.by<Cell[]>(() => {
		const [y, m] = month.split('-').map(Number);
		const first = new Date(y, m - 1, 1);
		const daysInMonth = new Date(y, m, 0).getDate();
		const offset = first.getDay();
		const total = Math.ceil((offset + daysInMonth) / 7) * 7;
		const start = addDays(toISO(first), -offset);
		const byDate = new Map<string, CalendarItem[]>();
		for (const it of items) byDate.set(it.date, [...(byDate.get(it.date) ?? []), it]);
		return Array.from({ length: total }, (_, i) => {
			const date = addDays(start, i);
			const d = fromISO(date);
			return { date, day: d.getDate(), inMonth: monthKey(date) === month, col: i % 7, items: byDate.get(date) ?? [] };
		});
	});

	const selectedItems = $derived(items.filter((i) => i.date === selected));
	const order: EventKind[] = ['ministry', 'meeting', 'visit', 'due', 'birthday'];
	const sorted = (list: CalendarItem[]) => [...list].sort((a, b) => order.indexOf(a.kind) - order.indexOf(b.kind));
</script>

<div class="card cal">
	<div class="head">
		<div>
			<h2>사역 캘린더</h2>
			<div class="sub">생일 · 심방 · 부서 일정을 한눈에</div>
		</div>
		<div class="nav">
			<button class="sq" aria-label="이전 달" onclick={() => shift(-1)}><Icon name="chevronLeft" size={18} /></button>
			<div class="month" aria-live="polite">{title}</div>
			<button class="sq" aria-label="다음 달" onclick={() => shift(1)}><Icon name="chevronRight" size={18} /></button>
			<button class="today" onclick={goToday}>오늘</button>
		</div>
	</div>

	<div class="grid wk" aria-hidden="true">
		{#each weekdayLabels as w, i (w)}
			<div class:sun={i === 0} class:sat={i === 6}>{w}</div>
		{/each}
	</div>

	<div class="grid body" role="grid" aria-label="{title} 캘린더">
		{#each cells as c (c.date)}
			{@const list = sorted(c.items)}
			{@const shown = list.length > 2 ? list.slice(0, 1) : list}
			<button
				class="cell"
				class:out={!c.inMonth}
				class:today={c.date === todayISO}
				class:sel={c.date === selected}
				role="gridcell"
				aria-label="{longMD(c.date)}{list.length ? `, 일정 ${list.length}건` : ''}"
				onclick={() => (selected = c.date)}
			>
				<span class="num" class:sun={c.col === 0} class:sat={c.col === 6}>{c.day}</span>
				<span class="chips">
					{#each shown as it, i (i)}
						<span class="chip tone-{eventKinds[it.kind].tone}" title={it.label}>{it.label}</span>
					{/each}
					{#if list.length > 2}<span class="more">+{list.length - 1}건</span>{/if}
				</span>
				<span class="dots" aria-hidden="true">
					{#each list.slice(0, 4) as it, i (i)}<i class="d tone-{eventKinds[it.kind].tone}"></i>{/each}
				</span>
			</button>
		{/each}
	</div>

	<ul class="legend">
		{#each Object.entries(eventKinds) as [k, v] (k)}
			<li class="tone-{v.tone}"><i class="sw"></i>{v.label}</li>
		{/each}
	</ul>

	<div class="agenda" aria-live="polite">
		<strong>{longMD(selected)}</strong>
		{#if selectedItems.length}
			<span class="list">
				{#each sorted(selectedItems) as it, i (i)}
					<span class="badge badge-sq tone-{eventKinds[it.kind].tone}">{eventKinds[it.kind].label} · {it.label}</span>
				{/each}
			</span>
		{:else}
			<span class="muted">일정이 없습니다.</span>
		{/if}
	</div>
</div>

<style>
	.cal { padding: 16px 18px 14px; display: flex; flex-direction: column; gap: 8px; }
	.head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
	h2 { font-size: 16px; font-weight: 700; line-height: 1.3; }
	.sub { font-size: 12.5px; color: var(--muted); }
	.nav { display: flex; align-items: center; gap: 6px; }
	.sq {
		width: 36px; height: 36px; border: 1px solid var(--line-strong); border-radius: 10px; background: var(--surface);
		display: flex; align-items: center; justify-content: center;
	}
	.month { min-width: 92px; text-align: center; font-size: 15px; font-weight: 700; }
	.today { height: 36px; padding: 0 14px; margin-left: 4px; border: 0; border-radius: 10px; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: 13.5px; }

	.grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; }
	.wk { text-align: center; font-size: 12.5px; font-weight: 600; color: var(--ink-2); }
	.wk div { padding: 2px 0; }
	.sun { color: var(--sun); }
	.sat { color: var(--sat); }

	.cell {
		min-width: 0; height: 64px; padding: 4px 4px 3px; border-radius: 10px; text-align: left;
		display: flex; flex-direction: column; gap: 2px; overflow: hidden;
		background: var(--field-bg); border: 1px solid var(--line-soft); color: var(--ink);
	}
	.cell:hover { border-color: var(--line-strong); }
	.cell.out { background: transparent; border-color: transparent; }
	.cell.out .num { color: #98a2b3; }
	.cell.today { background: var(--accent-soft); border: 1.5px solid var(--accent); }
	.cell.sel:not(.today) { border-color: var(--accent); }
	.num { font-size: 12.5px; font-weight: 700; line-height: 1.3; padding-left: 3px; }
	.cell.today .num { color: var(--accent); }
	.cell:not(.out):not(.today) .num.sun { color: var(--sun); }
	.cell:not(.out):not(.today) .num.sat { color: var(--sat); }

	.chips { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
	.chip {
		font-size: 11px; line-height: 1.4; padding: 0 5px; border-radius: 5px; font-weight: 600;
		white-space: nowrap; overflow: hidden; text-overflow: ellipsis; background: var(--bg); color: var(--fg);
	}
	.more { font-size: 10.5px; font-weight: 600; color: var(--muted); padding-left: 5px; line-height: 1.3; }
	.dots { display: none; gap: 3px; padding-left: 3px; }
	.d { width: 6px; height: 6px; border-radius: 50%; background: var(--fg); }

	.legend { display: flex; gap: 16px; flex-wrap: wrap; list-style: none; margin: 2px 0 0; padding: 0; }
	.legend li { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--ink-2); }
	.sw { width: 10px; height: 10px; border-radius: 3px; background: var(--fg); }

	.agenda {
		display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 12px;
		border-radius: var(--radius-sm); background: var(--field-bg); border: 1px solid var(--line-soft); font-size: 13.5px;
	}
	.list { display: flex; gap: 6px; flex-wrap: wrap; }

	/* 좁은 화면: 글자 대신 색 점으로 표시하고, 아래 "선택한 날" 목록에서 내용을 읽는다. */
	@media (max-width: 720px) {
		.cell { height: 52px; align-items: center; }
		.chips { display: none; }
		.dots { display: flex; }
		.num { padding-left: 0; }
	}
</style>
