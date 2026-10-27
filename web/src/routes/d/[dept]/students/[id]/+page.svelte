<script lang="ts">
	import { age, dotted, shortMD } from '$lib/dates';
	import Avatar from '$lib/components/Avatar.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import { attendanceColor, methodTone } from '$lib/palette';
	import { terms } from '$lib/terms.svelte';
	import { url } from '$lib/url';

	let { data } = $props();
	const d = $derived(data.detail);
	const s = $derived(d.student);
	const statusText = { present: '출석', online: '온라인', absent: '결석' } as const;
	const presentCount = $derived(d.history.filter((h) => h.status !== 'absent').length);
</script>

<div class="page">
	<a class="back" href={url(`/d/${data.dept.id}/students`)}><Icon name="chevronLeft" size={16} />{terms.student} 명단</a>

	<header class="hero card">
		<Avatar name={s.name} index={s.id} size={64} />
		<div class="who">
			<h1>{s.name}</h1>
			<div class="chips">
				<span class="badge badge-sq tone-blue">{d.gradeLabel}{d.className ? ` · ${d.className}` : ''}</span>
				<span class="badge tone-{s.status === 'active' ? 'green' : s.status === 'new' ? 'orange' : 'slate'}">{s.status === 'active' ? '재적' : s.status === 'new' ? terms.newMember : '비활동'}</span>
				<span class="muted">{s.gender} · 만 {age(s.birthDate)}세</span>
			</div>
		</div>
	</header>

	<div class="cols">
		<section class="card box">
			<h2>기본 정보</h2>
			<dl>
				<div><dt>생년월일</dt><dd>{dotted(s.birthDate)}</dd></div>
				<div><dt>연락처</dt><dd>{s.phone}</dd></div>
				<div><dt>학교</dt><dd>{s.school}</dd></div>
				<div><dt>세례</dt><dd>{s.baptism}</dd></div>
				{#if s.mentor}<div><dt>인도자</dt><dd>{s.mentor}</dd></div>{/if}
			</dl>
		</section>

		<section class="card box">
			<h2>보호자</h2>
			<dl>
				{#each s.guardians as g (g.relation)}
					<div><dt>{g.relation}</dt><dd>{g.name} · {g.phone}</dd></div>
				{/each}
			</dl>
		</section>
	</div>

	<section class="card box">
		<div class="head">
			<h2>최근 12주 출석</h2>
			<span class="muted">{presentCount}주 출석</span>
		</div>
		<ol class="hist">
			{#each [...d.history].reverse() as h (h.date)}
				<li>
					<span class="mark" style:background={h.status === 'absent' ? 'transparent' : attendanceColor[h.status]} style:border-color={attendanceColor[h.status]} title="{dotted(h.date)} {statusText[h.status]}">
						{h.status === 'absent' ? '' : h.status === 'online' ? '온' : '✓'}
					</span>
					<span class="d">{shortMD(h.date)}</span>
					<span class="sr-only">{statusText[h.status]}</span>
				</li>
			{/each}
		</ol>
		<ul class="legend">
			<li><i style:background={attendanceColor.present}></i>출석</li>
			<li><i style:background={attendanceColor.online}></i>온라인</li>
			<li><i class="hollow" style:border-color={attendanceColor.absent}></i>결석</li>
		</ul>
	</section>

	<section class="card box">
		<h2>{terms.visit} 기록</h2>
		{#each d.visits as v (v.id)}
			<div class="visit">
				<span class="badge badge-sq tone-{methodTone[v.method]}">{v.method}</span>
				<div class="grow"><strong>{v.title}</strong><div class="muted">{dotted(v.date)} · {v.author}</div></div>
				{#if v.followUp && !v.followUpDone}<span class="badge tone-orange">후속 {v.followUpDue ? shortMD(v.followUpDue) : ''}</span>{/if}
			</div>
		{:else}
			<p class="muted none">{terms.visit} 기록이 없습니다.</p>
		{/each}
	</section>
</div>

<style>
	.back { display: inline-flex; align-items: center; gap: 4px; color: var(--accent); font-weight: 700; font-size: 13.5px; width: fit-content; padding: 4px 0; }
	.hero { display: flex; align-items: center; gap: 18px; padding: 18px 22px; }
	h1 { font-size: 24px; font-weight: 700; }
	.chips { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 4px; }
	.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
	.box { padding: 16px 20px; display: flex; flex-direction: column; gap: 8px; }
	.box h2 { font-size: 16px; font-weight: 700; }
	.head { display: flex; justify-content: space-between; align-items: baseline; }
	dl { margin: 0; display: flex; flex-direction: column; }
	dl div { display: flex; gap: 12px; padding: 8px 0; border-top: 1px solid var(--line-soft); }
	dt { width: 72px; color: var(--muted); font-size: 13px; flex-shrink: 0; }
	dd { margin: 0; font-weight: 500; }

	.hist { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 6px; }
	.hist li { display: flex; flex-direction: column; align-items: center; gap: 4px; }
	.mark { width: 28px; height: 28px; border-radius: 50%; border: 2px solid; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; font-weight: 700; }
	.d { font-size: 11.5px; color: var(--muted); }
	.legend { display: flex; gap: 16px; list-style: none; margin: 0; padding: 0; font-size: 12.5px; color: var(--ink-2); }
	.legend li { display: inline-flex; align-items: center; gap: 6px; }
	.legend i { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
	.legend i.hollow { background: transparent; border: 2px solid; }

	.visit { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px solid var(--line-soft); }
	.grow { flex-grow: 1; line-height: 1.35; }
	.none { padding: 8px 0; }

	@media (max-width: 720px) {
		.cols { grid-template-columns: 1fr; }
		.hist { grid-template-columns: repeat(6, minmax(0, 1fr)); row-gap: 10px; }
		.hero { padding: 14px 16px; }
	}
</style>
