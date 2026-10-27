<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { updateTerms } from '$lib/api';
	import Tile from '$lib/components/Tile.svelte';
	import { presets } from '$lib/presets';
	import type { Terms } from '$lib/types';

	let { data } = $props();

	let form = $state<Terms>({ ...data.dept.terms });
	let saved = $state(false);
	let picked = $state('');

	$effect(() => {
		form = { ...data.dept.terms };
	});

	const fields: Array<{ key: keyof Terms; label: string; hint: string; tone: string }> = [
		{ key: 'department', label: '부서 이름', hint: '화면 위쪽에 표시', tone: 'blue' },
		{ key: 'student', label: '학생', hint: '명단 이름', tone: 'green' },
		{ key: 'teacher', label: '교사', hint: '교사 명단 이름', tone: 'violet' },
		{ key: 'class', label: '반', hint: '반별 보기 이름', tone: 'sky' },
		{ key: 'grade', label: '학년', hint: '기수로 바꿔 쓸 수 있어요', tone: 'pink' },
		{ key: 'cohortFormat', label: '기수 표시 형식', hint: '{n}은 숫자', tone: 'orange' },
		{ key: 'head', label: '부서 책임자', hint: '부장 · 부서장 등', tone: 'teal' },
		{ key: 'pastor', label: '교역자', hint: '담당 교역자 호칭', tone: 'amber' },
		{ key: 'visit', label: '심방', hint: '메뉴 이름', tone: 'blue' },
		{ key: 'newMember', label: '새가족', hint: '상태 이름', tone: 'green' }
	];

	function applyPreset(key: string) {
		const p = presets.find((x) => x.key === key);
		if (!p) return;
		picked = key;
		form = { ...p.terms };
		saved = false;
	}

	async function save(e: SubmitEvent) {
		e.preventDefault();
		await updateTerms(data.dept.id, { ...form, department: form.department.trim() || data.dept.name });
		await invalidateAll();
		saved = true;
	}

	function reset() {
		form = { ...data.dept.terms };
		saved = false;
	}

	const previewNav = $derived([
		{ label: `${form.student} 명단`, icon: 'users', tone: 'green' as const, on: true },
		{ label: `${form.teacher} 명단`, icon: 'user', tone: 'violet' as const, on: false },
		{ label: '출석 체크', icon: 'check', tone: 'sky' as const, on: false },
		{ label: `${form.visit} 기록`, icon: 'heart', tone: 'pink' as const, on: false }
	]);
</script>

<div class="cols">
	<form class="card main" onsubmit={save}>
		<div>
			<h2>우리 부서에서 쓰는 말</h2>
			<p class="sub">화면 전체의 이름이 한꺼번에 바뀝니다. 프리셋을 고른 뒤 필요한 것만 고치세요.</p>
		</div>

		<div class="presets" role="group" aria-label="프리셋">
			<span class="plabel">프리셋</span>
			{#each presets.filter((p) => !['custom', 'later'].includes(p.key)) as p (p.key)}
				<button type="button" class:on={picked === p.key} onclick={() => applyPreset(p.key)}>{p.name}</button>
			{/each}
		</div>

		<div class="grid">
			{#each fields as f, i (f.key)}
				<label class="field">
					<span class="lab tone-{f.tone}"><i></i>{f.label} <span class="hint">· {f.hint}</span></span>
					<input class="input" bind:value={form[f.key]} oninput={() => (saved = false)} />
				</label>
			{/each}
		</div>

		<div class="foot">
			<button type="button" class="link" onclick={reset}>저장하지 않은 변경 되돌리기</button>
			<div class="buttons">
				{#if saved}<span class="ok" role="status">저장했어요</span>{/if}
				<button class="btn btn-primary" type="submit">저장</button>
			</div>
		</div>
	</form>

	<aside class="side" aria-label="미리보기">
		<div class="sh">미리보기</div>
		<div class="card pv">
			<div class="pb"><span class="lg">은</span><strong>{form.department}</strong></div>
			<div class="nav">
				{#each previewNav as n (n.label)}
					<div class="ni" class:on={n.on}><Tile icon={n.icon} tone={n.tone} size={26} iconSize={16} />{n.label}</div>
				{/each}
			</div>
		</div>
		<div class="card pv">
			<div class="sub">{form.student} 목록 · {form.class}별</div>
			<div class="pr"><strong>김하준</strong><span class="badge badge-sq tone-blue">{form.cohortFormat.replace('{n}', '1')} · 샘물 {form.class}</span></div>
			<div class="pr"><strong>박서윤</strong><span class="badge badge-sq tone-blue">{form.cohortFormat.replace('{n}', '1')} · 샘물 {form.class}</span></div>
			<div class="who">담당 <b class="v">{form.pastor}</b> · <b class="g">{form.head}</b></div>
		</div>
		<p class="note">미리보기는 지금 입력한 용어로 화면이 어떻게 보이는지 보여줍니다. 저장 전에는 실제 화면이 바뀌지 않습니다.</p>
	</aside>
</div>

<style>
	.cols { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start; }
	.main { padding: 22px 26px; display: flex; flex-direction: column; gap: 14px; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13px; color: var(--muted); }
	.presets { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
	.plabel { font-size: 13px; font-weight: 700; color: var(--ink-2); margin-right: 4px; }
	.presets button { height: 34px; padding: 0 14px; border-radius: 999px; border: 0; background: #eef2fb; color: var(--ink-2); font-weight: 700; font-size: 13.5px; }
	.presets button.on { background: var(--accent); color: #fff; }
	.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 18px; }
	.lab { display: flex; align-items: center; gap: 8px; }
	.lab i { width: 10px; height: 10px; border-radius: 3px; background: var(--fg); display: inline-block; }
	.hint { font-weight: 400; color: var(--muted); }
	.foot { display: flex; justify-content: space-between; align-items: center; padding-top: 6px; border-top: 1px solid var(--line-soft); gap: 12px; flex-wrap: wrap; }
	.link { border: 0; background: transparent; color: var(--ink-2); font-weight: 600; text-decoration: underline; padding: 8px 4px; }
	.buttons { display: flex; align-items: center; gap: 12px; }
	.ok { color: var(--ok-ink); font-weight: 700; }

	.side { display: flex; flex-direction: column; gap: 12px; }
	.sh { font-size: 13px; font-weight: 700; color: var(--ink-2); }
	.pv { padding: 16px 18px; display: flex; flex-direction: column; gap: 8px; }
	.pb { display: flex; align-items: center; gap: 8px; }
	.lg { width: 30px; height: 30px; border-radius: 9px; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
	.nav { display: flex; flex-direction: column; gap: 2px; font-size: 13.5px; }
	.ni { display: flex; align-items: center; gap: 10px; padding: 5px 8px; border-radius: 10px; color: var(--ink-2); }
	.ni.on { background: var(--accent-soft); font-weight: 700; color: var(--ink); }
	.pr { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-top: 1px solid var(--line-soft); }
	.who { font-size: 12.5px; color: var(--ink-2); border-top: 1px solid var(--line-soft); padding-top: 8px; }
	.v { color: #6d28d9; }
	.g { color: #0f7b4a; }
	.note { font-size: 12.5px; color: var(--muted); line-height: 1.6; background: #fff8ec; border: 1px solid #ffd9a8; border-radius: var(--radius-sm); padding: 10px 12px; }

	@media (max-width: 1000px) { .cols { grid-template-columns: 1fr; } }
	@media (max-width: 640px) { .grid { grid-template-columns: 1fr; } .main { padding: 18px 16px; } }
</style>
