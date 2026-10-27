<script lang="ts">
	import { dotted, shortMD } from '../dates';

	let { data }: { data: Array<{ date: string; count: number }> } = $props();

	const W = 536;
	const H = 224;
	const padL = 30;
	const padR = 14;
	const base = 190;
	const top = 30;

	let asTable = $state(false);
	let hover = $state<number | null>(null);

	const max = $derived(Math.max(1, ...data.map((d) => d.count)));
	const scaleMax = $derived(Math.max(15, Math.ceil((max * 1.1) / 15) * 15));
	const ticks = $derived([0, 1, 2, 3].map((i) => (scaleMax / 3) * i));
	const x = (i: number) => padL + (data.length > 1 ? (i / (data.length - 1)) * (W - padL - padR) : 0);
	const y = (v: number) => base - (v / scaleMax) * (base - top);
	const pts = $derived(data.map((d, i) => ({ x: x(i), y: y(d.count), ...d })));
	const line = $derived(pts.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '));
	const area = $derived(
		pts.length
			? `M${pts[0].x},${base} L${pts.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' L')} L${pts[pts.length - 1].x},${base} Z`
			: ''
	);
	const peak = $derived(pts.length ? pts.reduce((a, b) => (b.count > a.count ? b : a)) : null);
	const last = $derived(pts[pts.length - 1]);
	const summary = $derived(
		data.length ? `최근 ${data.length}주 학생 출석 인원 꺾은선 그래프. ${shortMD(data[0].date)} ${data[0].count}명에서 ${shortMD(data[data.length - 1].date)} ${data[data.length - 1].count}명, 최고 ${peak?.count}명.` : ''
	);
</script>

<div class="wrap">
	<div class="head">
		<div>
			<h2>주별 출석 추이</h2>
			<div class="sub">최근 {data.length}주 · 학생 출석 인원 (온라인 포함)</div>
		</div>
		<button class="link" onclick={() => (asTable = !asTable)}>{asTable ? '그래프로 보기' : '표로 보기'}</button>
	</div>

	{#if asTable}
		<table>
			<caption class="sr-only">주별 출석 인원</caption>
			<thead><tr><th scope="col">날짜</th><th scope="col">출석 인원</th></tr></thead>
			<tbody>
				{#each [...data].reverse() as d (d.date)}
					<tr><td>{dotted(d.date)}</td><td>{d.count}명</td></tr>
				{/each}
			</tbody>
		</table>
	{:else}
		<svg viewBox="0 0 {W} {H}" role="img" aria-label={summary} onmouseleave={() => (hover = null)}>
			{#each ticks as tk (tk)}
				<line x1={padL} x2={W - padR} y1={y(tk)} y2={y(tk)} stroke={tk === 0 ? '#dce3f3' : '#edf1fa'} />
				<text x={padL - 8} y={y(tk) + 4} text-anchor="end" font-size="11" fill="#5b6678">{Math.round(tk)}</text>
			{/each}
			<path d={area} fill="var(--accent)" fill-opacity="0.12" />
			<polyline points={line} fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
			{#each pts as p, i (p.date)}
				<text x={p.x} y="210" text-anchor="middle" font-size="11" fill="#5b6678">{shortMD(p.date)}</text>
				{#if p === peak || p === last || hover === i}
					<circle cx={p.x} cy={p.y} r="5" fill="var(--accent)" stroke="#fff" stroke-width="2" />
				{/if}
			{/each}
			{#if peak}<text x={peak.x} y={peak.y - 12} text-anchor="middle" font-size="12" font-weight="600" fill="#17202a">{peak.count}</text>{/if}
			{#if last && last !== peak}<text x={last.x} y={last.y - 12} text-anchor="middle" font-size="12" font-weight="600" fill="#17202a">{last.count}</text>{/if}
			{#each pts as p, i (p.date)}
				<!-- 눈에 안 보이는 큰 원: 마우스를 올리기 쉽게 한다 -->
				<circle cx={p.x} cy={p.y} r="16" fill="transparent" role="presentation" onmouseenter={() => (hover = i)} onclick={() => (hover = i)} />
			{/each}
			{#if hover !== null && pts[hover]}
				{@const p = pts[hover]}
				{@const tx = Math.min(Math.max(p.x, 70), W - 70)}
				<g pointer-events="none">
					<rect x={tx - 62} y={p.y - 52} width="124" height="34" rx="8" fill="#17202a" />
					<text x={tx} y={p.y - 31} text-anchor="middle" font-size="12" fill="#fff">{dotted(p.date)} · {p.count}명</text>
				</g>
			{/if}
		</svg>
	{/if}
</div>

<style>
	.wrap { padding: 16px 20px 10px; }
	.head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
	h2 { font-size: 16px; font-weight: 700; }
	.sub { font-size: 12.5px; color: var(--muted); }
	.link { border: 0; background: transparent; color: var(--accent); font-weight: 700; font-size: 13px; padding: 6px 4px; }
	svg { display: block; width: 100%; height: auto; margin-top: 2px; }
	table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 13.5px; }
	th, td { text-align: left; padding: 6px 4px; border-top: 1px solid var(--line-soft); }
	th { color: var(--muted); font-size: 12.5px; border-top: 0; }
</style>
