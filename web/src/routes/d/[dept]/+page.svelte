<script lang="ts">
	import { fullDate, shortMD, today } from '$lib/dates';
	import { invalidateAll } from '$app/navigation';
	import { completeFollowUp } from '$lib/api';
	import Avatar from '$lib/components/Avatar.svelte';
	import AttendanceChart from '$lib/components/AttendanceChart.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import MinistryCalendar from '$lib/components/MinistryCalendar.svelte';
	import StatCard from '$lib/components/StatCard.svelte';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';

	let { data } = $props();
	const dash = $derived(data.dash);
	const dept = $derived(data.dept);

	const diff = (n: number) => (n > 0 ? `▲ ${n}명 (전주 대비)` : n < 0 ? `▼ ${-n}명 (전주 대비)` : '전주와 같음');
	const diffTone = (n: number) => (n > 0 ? 'ok' : n < 0 ? 'bad' : 'muted') as 'ok' | 'bad' | 'muted';

	async function done(id: number) {
		await completeFollowUp(id);
		await invalidateAll();
	}
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>대시보드</h1>
			<p class="sub">{fullDate(today())} · {dept.name}</p>
		</div>
		<div class="actions">
			<a class="btn" href={url(`/d/${dept.id}/visits`)}><span class="pink"><Icon name="heart" size={18} /></span>{terms.visit} 기록</a>
			<a class="btn btn-primary" href={url(`/d/${dept.id}/attendance`)}><Icon name="check" size={18} />출석 체크하기</a>
		</div>
	</header>

	<section class="stats">
		<StatCard label="지난 주일 {terms.student} 출석" value={dash.studentCount} unit="/ 재적 {dash.enrolledTotal}명" note={diff(dash.studentDiff)} noteTone={diffTone(dash.studentDiff)} icon="users" tone="blue" />
		<StatCard label="지난 주일 {terms.teacher} 출석" value={dash.teacherCount} unit="/ {dash.teacherTotal}명" note={diff(dash.teacherDiff)} noteTone={diffTone(dash.teacherDiff)} icon="check" tone="green" />
		<StatCard label={terms.newMember} value={dash.newCount} unit="명" note="정착 진행 중" icon="userPlus" tone="orange" />
		<StatCard label="이번 주 생일" value={dash.birthdayCount} unit="명" note={dash.birthdayRange} icon="gift" tone="pink" />
	</section>

	<section class="row">
		<MinistryCalendar deptId={dept.id} />

		<div class="col">
			<div class="card box">
				<div class="box-head">
					<h2>장기 결석</h2>
					<span class="badge tone-orange">연속 3주 이상</span>
				</div>
				{#each dash.absentees as p, i (p.id)}
					<div class="line">
						<Avatar name={p.name} tone={p.tone} />
						<div class="grow">
							<div class="strong tone-{p.tone}">{p.name}</div>
							<div class="sub">{p.gradeLabel}{p.className && p.className !== p.gradeLabel ? ` · ${p.className}` : ''} · {p.weeks}주 연속 결석</div>
						</div>
						<a class="pill-link" href={url(`/d/${dept.id}/visits`)}>{terms.visit}</a>
					</div>
				{:else}
					<p class="empty">장기 결석자가 없습니다.</p>
				{/each}
			</div>

			<div class="card box">
				<div class="box-head">
					<h2>후속 조치가 남은 {terms.visit}</h2>
					<span class="badge tone-orange">{dash.followUps.length}건</span>
				</div>
				{#each dash.followUps as v (v.id)}
					<div class="line follow">
						<div class="grow">
							<div class="row2"><span class="strong">{v.targetNames.join(', ')} · {v.method}</span><span class="due">~ {v.followUpDue ? shortMD(v.followUpDue) : ''}</span></div>
							<div class="sub2">{v.followUp}</div>
						</div>
						<button class="mini" onclick={() => done(v.id)}>완료</button>
					</div>
				{:else}
					<p class="empty">남은 후속 조치가 없습니다.</p>
				{/each}
			</div>
		</div>
	</section>

	<section class="row">
		<div class="card"><AttendanceChart data={dash.trend} /></div>
		<div class="card box">
			<h2>공지사항</h2>
			{#each dash.notices as n (n.id)}
				<div class="line note">
					<div>
						<div class="meta">
							<span class="badge badge-sq {n.deptId === null ? 'tone-blue' : n.pinned ? 'tone-orange' : 'tone-green'}">{n.deptId === null ? '교회 전체' : n.pinned ? '고정' : '부서'}</span>
							<span class="sub">{shortMD(n.date)}</span>
						</div>
						<div class="strong">{n.title}</div>
					</div>
				</div>
			{/each}
		</div>
	</section>
</div>

<style>
	.pink { color: #be185d; display: inline-flex; }
	.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
	.row { display: grid; grid-template-columns: 1.8fr 1fr; gap: 16px; align-items: stretch; }
	.col { display: flex; flex-direction: column; gap: 16px; }
	.box { padding: 16px 18px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
	.box h2 { font-size: 16px; font-weight: 700; }
	.box-head { display: flex; align-items: center; justify-content: space-between; padding-bottom: 4px; gap: 8px; }
	.line { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-top: 1px solid var(--line-soft); }
	.grow { flex-grow: 1; min-width: 0; line-height: 1.35; }
	.strong { font-weight: 600; }
	.strong[class*='tone-'] { color: var(--fg); }
	.sub { font-size: 12.5px; color: var(--muted); }
	.sub2 { font-size: 13px; color: var(--ink-2); }
	.row2 { display: flex; justify-content: space-between; gap: 8px; }
	.due { font-size: 12.5px; color: #a04a07; font-weight: 600; flex-shrink: 0; }
	.pill-link {
		height: 32px; padding: 0 12px; border: 1px solid var(--line-strong); border-radius: 10px; font-size: 13px; font-weight: 600;
		color: #be185d; background: #fff5f9; display: inline-flex; align-items: center;
	}
	.mini { height: 30px; padding: 0 12px; border-radius: 9px; border: 1px solid var(--line-strong); background: var(--surface); font-size: 12.5px; font-weight: 700; color: var(--ok-ink); }
	.mini:hover { background: #ddf5e8; }
	.note .meta { display: flex; align-items: center; gap: 6px; margin-bottom: 2px; }
	.empty { color: var(--muted); padding: 12px 0; font-size: 13.5px; }

	@media (max-width: 1000px) {
		.stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.row { grid-template-columns: 1fr; }
	}
	@media (max-width: 480px) {
		.actions { width: 100%; }
		.actions .btn { flex: 1; }
	}
</style>
