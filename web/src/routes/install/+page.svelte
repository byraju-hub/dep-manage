<script lang="ts">
	import Tile from '$lib/components/Tile.svelte';
	import { presets } from '$lib/presets';
	import { url } from '$lib/url';
	import type { Tone } from '$lib/types';

	// 설치 마법사 미리보기. 실제 설치(DB 연결, 테이블 만들기 등)는 PHP 쪽에서 구현한다.
	const steps = [
		['환경 점검', 'PHP · 폴더 권한'],
		['데이터베이스 연결', '접속 정보 입력'],
		['테이블 만들기', '자동으로 진행'],
		['교회 정보', '교회 이름'],
		['첫 부서', '종류 · 담당자'],
		['관리자 계정', '아이디 · 비밀번호'],
		['완료', '로그인하기']
	];
	const current = 4;

	let pick = $state('youth');
	let deptName = $state('중고등부');
	const preset = $derived(presets.find((p) => p.key === pick) ?? presets[3]);
	const tones: Tone[] = ['blue', 'green', 'orange', 'pink', 'violet', 'teal', 'sky', 'amber'];

	function choose(key: string) {
		pick = key;
		const p = presets.find((x) => x.key === key);
		if (p && !['custom', 'later'].includes(key)) deptName = p.name;
	}
</script>

<svelte:head><title>설치 · dep-manage</title></svelte:head>

<div class="wrap">
	<aside class="side">
		<div class="brand">
			<div class="logo">d</div>
			<div>
				<div class="bn">dep-manage</div>
				<div class="bs">교회 부서 관리 · 설치</div>
			</div>
		</div>

		<ol>
			{#each steps as [label, sub], i (label)}
				<li aria-current={i === current ? 'step' : undefined}>
					<span class="dot" class:done={i < current} class:now={i === current}>{i < current ? '✓' : i + 1}</span>
					<span class="txt">
						<span class="l" class:strong={i === current} class:past={i < current}>{label}</span>
						<span class="s">{sub}</span>
					</span>
				</li>
			{/each}
		</ol>

		<p class="lock">설치가 끝나면 이 주소(<b>/install</b>)는 자동으로 잠깁니다.</p>
	</aside>

	<main class="main">
		<div>
			<span class="step-pill">5 / 7 단계</span>
			<h1>첫 번째 부서를 만들어요</h1>
			<p class="sub">부서 종류를 고르면 학년 단계와 용어가 자동으로 채워집니다. 나머지 부서는 설치 후에 추가할 수 있어요.</p>
		</div>

		<div class="presets" role="radiogroup" aria-label="부서 종류">
			{#each presets as p (p.key)}
				<button role="radio" aria-checked={pick === p.key} class:on={pick === p.key} onclick={() => choose(p.key)}>
					<Tile icon={p.icon} tone={p.tone} size={36} iconSize={20} />
					<span class="pn">{p.name}</span>
					<span class="pr">{p.range}</span>
				</button>
			{/each}
		</div>

		<div class="card form">
			<div class="three">
				<label class="field"><span>부서 이름</span><input class="input big" bind:value={deptName} /></label>
				<label class="field"><span>담당 교역자</span><input class="input big" placeholder="예: 김은혜 목사" /></label>
				<label class="field"><span>부장 · 부서장</span><input class="input big" placeholder="예: 박성실 집사" /></label>
			</div>

			<div class="grades">
				<div class="field-label">{preset.terms.grade} 단계 <span class="muted n">· 해가 바뀔 때 이 순서대로 올라갑니다</span></div>
				{#if preset.grades.length}
					<div class="chain">
						{#each preset.grades as g, i (g)}
							<span class="gs">
								<span class="badge badge-sq tone-{g === '졸업' ? 'slate' : g.startsWith('예비') ? 'slate' : tones[i % tones.length]}" class:dash={g.startsWith('예비')}>{g}</span>
								{#if i < preset.grades.length - 1}<span class="arr" aria-hidden="true">›</span>{/if}
							</span>
						{/each}
					</div>
				{:else}
					<p class="muted">설치 후 설정에서 직접 단계를 만들 수 있어요.</p>
				{/if}
				<p class="hint">설치 후 <b>설정 → 학년 · 반 구조</b>에서 단계를 추가하거나 이름을 바꿀 수 있어요.</p>
			</div>
		</div>

		<div class="nav">
			<a class="btn btn-lg" href={url('/login')}>이전</a>
			<a class="btn btn-primary btn-lg" href={url('/login')}>다음: 관리자 계정</a>
		</div>
	</main>
</div>

<style>
	.wrap { display: flex; min-height: 100vh; }
	.side { width: 320px; flex-shrink: 0; background: var(--surface); border-right: 1px solid var(--line); padding: 32px 28px; display: flex; flex-direction: column; gap: 28px; }
	.brand { display: flex; align-items: center; gap: 10px; }
	.logo { width: 42px; height: 42px; border-radius: 13px; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 19px; }
	.bn { font-size: 17px; font-weight: 700; line-height: 1.2; }
	.bs { font-size: 12.5px; color: var(--muted); }
	ol { list-style: none; margin: 0; padding: 0; }
	li { display: flex; gap: 14px; align-items: flex-start; padding-bottom: 18px; }
	.dot { width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; border: 2px solid var(--line-strong); color: var(--muted); background: var(--surface); }
	.dot.done { background: var(--ok); border-color: var(--ok); color: #fff; }
	.dot.now { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
	.txt { padding-top: 3px; line-height: 1.35; display: flex; flex-direction: column; }
	.l { font-size: 14.5px; font-weight: 500; color: var(--muted); }
	.l.strong { font-weight: 700; color: var(--ink); }
	.l.past { font-weight: 600; color: var(--ink-2); }
	.s { font-size: 12.5px; color: var(--muted); }
	.lock { margin-top: auto; font-size: 12.5px; color: var(--muted); line-height: 1.6; }

	.main { flex: 1; min-width: 0; padding: 40px 56px; display: flex; flex-direction: column; gap: 20px; max-width: 980px; }
	.step-pill { display: inline-block; font-size: 12.5px; font-weight: 700; color: var(--accent); background: var(--accent-soft); border-radius: 999px; padding: 2px 12px; margin-bottom: 6px; }
	h1 { font-size: 26px; font-weight: 700; line-height: 1.35; }
	.sub { color: var(--muted); }

	.presets { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
	.presets button { display: flex; flex-direction: column; align-items: flex-start; text-align: left; gap: 2px; padding: 12px 14px; border-radius: 14px; border: 1px solid var(--line-strong); background: var(--surface); }
	.presets button.on { border: 2px solid var(--accent); background: var(--accent-soft); padding: 11px 13px; }
	.pn { font-size: 15px; font-weight: 700; margin-top: 4px; }
	.pr { font-size: 12.5px; color: var(--muted); }

	.form { padding: 22px 24px; display: flex; flex-direction: column; gap: 16px; }
	.three { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
	.big { height: 44px; font-size: 15px; }
	.chain { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 8px 0; }
	.gs { display: inline-flex; align-items: center; gap: 6px; }
	.gs .badge { height: 32px; padding: 0 12px; font-size: 13.5px; font-weight: 700; }
	.dash { background: var(--surface); border: 1px dashed #98a2b3; color: var(--ink-2); }
	.arr { color: #98a2b3; font-size: 18px; }
	.n { font-weight: 400; }
	.hint { font-size: 12.5px; color: var(--muted); }
	.nav { margin-top: auto; display: flex; justify-content: space-between; }

	@media (max-width: 960px) {
		.wrap { flex-direction: column; }
		.side { width: auto; padding: 20px 16px; gap: 16px; border-right: 0; border-bottom: 1px solid var(--line); }
		ol { display: flex; gap: 8px; overflow-x: auto; }
		li { padding-bottom: 0; flex-shrink: 0; }
		.txt { display: none; }
		.lock { display: none; }
		.main { padding: 24px 16px 40px; }
		.presets { grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.three { grid-template-columns: 1fr; }
	}
</style>
