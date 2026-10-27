<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { updateFonts } from '$lib/api';
	import Tile from '$lib/components/Tile.svelte';
	import { defaultFonts, ensureLoaded, fontDef, fonts, roleLabels } from '$lib/fonts';
	import type { FontId, FontRole, FontSettings } from '$lib/types';

	let { data } = $props();

	let form = $state<FontSettings>({ ...data.church.fonts });
	let saved = $state(false);

	$effect(() => {
		form = { ...data.church.fonts };
	});

	// 미리보기에 쓸 글꼴 파일을 미리 불러온다.
	$effect(() => {
		for (const id of Object.values(form)) void ensureLoaded(id);
	});

	const roles: FontRole[] = ['title', 'menu', 'body'];
	const stack = (role: FontRole) => fontDef(form[role]).stack;

	function pick(role: FontRole, id: FontId) {
		form[role] = id;
		saved = false;
	}

	async function save(e: SubmitEvent) {
		e.preventDefault();
		await updateFonts(form);
		await invalidateAll();
		saved = true;
	}

	function reset() {
		form = { ...defaultFonts };
		saved = false;
	}
</script>

<div class="cols">
	<form class="card main" onsubmit={save}>
		<div>
			<h2>글꼴</h2>
			<p class="sub">세 곳의 글꼴을 따로 고를 수 있어요. 모든 부서에 적용되고, 글꼴 파일은 이 프로그램에 들어 있어서 인터넷 글꼴 서비스를 쓰지 않아요.</p>
		</div>

		{#each roles as role (role)}
			<label class="field">
				<span class="lab">{roleLabels[role].label} <span class="where">· {roleLabels[role].where}</span></span>
				<select class="input" value={form[role]} onchange={(e) => pick(role, e.currentTarget.value as FontId)}>
					{#each fonts as f (f.id)}
						<option value={f.id}>{f.label} — {f.note}</option>
					{/each}
				</select>
			</label>
		{/each}

		<div class="foot">
			<button type="button" class="link" onclick={reset}>기본 글꼴로 되돌리기</button>
			<div class="buttons">
				{#if saved}<span class="ok" role="status">저장했어요</span>{/if}
				<button class="btn btn-primary" type="submit">저장</button>
			</div>
		</div>
	</form>

	<aside class="side" aria-label="미리보기">
		<div class="sh">미리보기</div>

		<div class="card pv">
			<div class="cap">타이틀</div>
			<div class="brand" style:font-family={stack('title')}><span class="lg">{data.church.name.slice(0, 1)}</span>{data.church.name}</div>
			<div class="h1" style:font-family={stack('title')}>대시보드</div>
		</div>

		<div class="card pv">
			<div class="cap">메뉴</div>
			<div class="nav" style:font-family={stack('menu')}>
				<div class="ni on"><Tile icon="home" tone="blue" size={26} iconSize={16} />대시보드</div>
				<div class="ni"><Tile icon="users" tone="green" size={26} iconSize={16} />{data.dept.terms.student} 명단</div>
				<div class="ni"><Tile icon="check" tone="sky" size={26} iconSize={16} />출석 체크</div>
			</div>
		</div>

		<div class="card pv">
			<div class="cap">내용</div>
			<div style:font-family={stack('body')}>
				<p class="para">이번 주일 출석은 38명이고, 장기 결석자 3명에게 {data.dept.terms.visit} 기록을 남겼습니다.</p>
				<div class="row"><strong>김하준</strong><span>중1 A · 재적</span></div>
				<div class="row"><strong>박서윤</strong><span>중1 A · {data.dept.terms.newMember}</span></div>
				<p class="num">0123456789 ABC abc</p>
			</div>
		</div>
	</aside>
</div>

<style>
	.cols { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start; }
	.main { padding: 22px 26px; display: flex; flex-direction: column; gap: 16px; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13px; color: var(--muted); }
	.lab { font-weight: 700; }
	.where { font-weight: 400; color: var(--muted); }
	.foot { display: flex; justify-content: space-between; align-items: center; padding-top: 6px; border-top: 1px solid var(--line-soft); gap: 12px; flex-wrap: wrap; }
	.link { border: 0; background: transparent; color: var(--ink-2); font-weight: 600; text-decoration: underline; padding: 8px 4px; }
	.buttons { display: flex; align-items: center; gap: 12px; }
	.ok { color: var(--ok-ink); font-weight: 700; }

	.side { display: flex; flex-direction: column; gap: 12px; }
	.sh { font-size: 13px; font-weight: 700; color: var(--ink-2); }
	.pv { padding: 14px 18px; display: flex; flex-direction: column; gap: 8px; }
	.cap { font-size: 12px; font-weight: 700; color: var(--muted); font-family: var(--font-body); }
	.brand { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; }
	.lg { width: 30px; height: 30px; border-radius: 9px; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
	.h1 { font-size: 24px; font-weight: 700; line-height: 1.3; }
	.nav { display: flex; flex-direction: column; gap: 2px; font-size: 14px; }
	.ni { display: flex; align-items: center; gap: 10px; padding: 5px 8px; border-radius: 10px; color: var(--ink-2); font-weight: 500; }
	.ni.on { background: var(--accent-soft); font-weight: 700; color: var(--ink); }
	.para { font-size: 14px; line-height: 1.6; margin-bottom: 4px; }
	.row { display: flex; justify-content: space-between; padding: 7px 0; border-top: 1px solid var(--line-soft); font-size: 14px; }
	.row span { color: var(--muted); }
	.num { font-size: 13px; color: var(--ink-2); border-top: 1px solid var(--line-soft); padding-top: 7px; }

	@media (max-width: 1000px) { .cols { grid-template-columns: 1fr; } }
	@media (max-width: 640px) { .main { padding: 18px 16px; } }
</style>
