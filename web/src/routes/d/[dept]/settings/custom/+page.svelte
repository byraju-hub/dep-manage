<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, createCustomField, deleteCustomField, updateCustomField } from '$lib/api';
	import { terms } from '$lib/terms.svelte';
	import type { CustomField, CustomType } from '$lib/types';

	let { data } = $props();

	const typeLabels: Record<CustomType, string> = { text: '짧은 글', longtext: '긴 글', bool: '예 / 아니오', select: '목록에서 고르기', date: '날짜', number: '숫자' };

	type Entity = 'student' | 'teacher';
	interface Quick { entity: Entity; label: string; type: CustomType; options?: string[]; hint: string }
	// 자주 쓰는 항목. 누르면 바로 만들어진다.
	const quick: Quick[] = [
		{ entity: 'student', label: '카카오톡 등록', type: 'bool', hint: '단톡방 초대 여부' },
		{ entity: 'student', label: '수련회 참석', type: 'bool', hint: '올해 수련회' },
		{ entity: 'student', label: '장래희망', type: 'text', hint: '한 줄' },
		{ entity: 'student', label: '알레르기 · 건강 유의사항', type: 'longtext', hint: '간식·행사 때 꼭 필요' },
		{ entity: 'student', label: '사진 · 개인정보 이용 동의', type: 'select', options: ['동의', '일부 동의', '미동의', '확인 전'], hint: '미성년자는 보호자 동의가 필요해요' },
		{ entity: 'student', label: '입교(세례)일', type: 'date', hint: '' },
		{ entity: 'student', label: '등록 경로', type: 'select', options: ['가족 중 교인', '친구 초청', '전도', '스스로 방문', '기타'], hint: '새가족 정착 돌봄에 도움' },
		{ entity: 'student', label: '교통 지원 필요', type: 'bool', hint: '차량·픽업' },
		{ entity: 'student', label: '가족 중 교인', type: 'text', hint: '형제·부모 이름' },
		{ entity: 'teacher', label: '봉사 부서 · 특기', type: 'text', hint: '찬양, 영상, 간식 등' },
		{ entity: 'teacher', label: '교사 교육 이수', type: 'bool', hint: '' },
		{ entity: 'teacher', label: '직업', type: 'text', hint: '' },
		{ entity: 'teacher', label: '가능한 요일', type: 'text', hint: '주일 외 모임' }
	];

	let error = $state('');
	let busy = $state(false);

	const of = (e: Entity) => data.fields.filter((f) => f.entity === e);
	const entityLabel = (e: Entity) => (e === 'student' ? terms.student : terms.teacher);
	const has = (q: Quick) => data.fields.some((f) => f.entity === q.entity && f.label === q.label);

	async function run(fn: () => Promise<unknown>) {
		error = '';
		busy = true;
		try {
			await fn();
			await invalidateAll();
		} catch (e) {
			error = e instanceof ApiError ? e.message : '처리하지 못했어요.';
		} finally {
			busy = false;
		}
	}

	// 직접 만들기
	let entity = $state<Entity>('student');
	let label = $state('');
	let type = $state<CustomType>('text');
	let optionsText = $state('');
	let showInList = $state(false);
	const splitOptions = (t: string) => t.split(/[,\n]/).map((s) => s.trim()).filter(Boolean);

	async function add() {
		await run(async () => {
			await createCustomField(data.dept.id, { entity, label: label.trim(), type, options: type === 'select' ? splitOptions(optionsText) : [], showInList });
			label = optionsText = '';
			showInList = false;
		});
	}

	// 고치기
	let editId = $state<number | null>(null);
	let eLabel = $state('');
	let eOptions = $state('');
	let eList = $state(false);
	function startEdit(f: CustomField) {
		editId = f.id;
		eLabel = f.label;
		eOptions = f.options.join(', ');
		eList = f.showInList;
	}
	async function saveEdit(f: CustomField) {
		await run(async () => {
			await updateCustomField(f.id, { label: eLabel.trim(), options: f.type === 'select' ? splitOptions(eOptions) : [], showInList: eList });
			editId = null;
		});
	}
	async function remove(f: CustomField) {
		if (!confirm(`'${f.label}' 항목을 지울까요?\n입력해 둔 값은 화면에서 보이지 않게 돼요.`)) return;
		await run(() => deleteCustomField(f.id));
	}
</script>

<div class="stack">
	<section class="card box">
		<h2>추가 항목</h2>
		<p class="sub">기본 항목(이름·연락처·학교 등) 말고 우리 부서에만 필요한 정보를 {terms.student}·{terms.teacher} 입력 화면에 더할 수 있어요. 항목은 <b>{data.dept.name}에만</b> 적용되고, 각각 20개까지 만들 수 있어요.</p>
		{#if error}<p class="err" role="alert">{error}</p>{/if}

		{#each ['student', 'teacher'] as const as e (e)}
			<h3>{entityLabel(e)} 항목</h3>
			{#if of(e).length}
				<ul class="fields">
					{#each of(e) as f (f.id)}
						<li>
							{#if editId === f.id}
								<div class="edit">
									<label class="field"><span>이름</span><input class="input" bind:value={eLabel} maxlength="40" /></label>
									{#if f.type === 'select'}<label class="field"><span>고를 수 있는 값 (쉼표로 구분)</span><input class="input" bind:value={eOptions} /></label>{/if}
									<label class="chk"><input type="checkbox" bind:checked={eList} /> 명단 표에도 보이기</label>
									<span class="row"><button class="btn btn-primary sm" disabled={busy} onclick={() => saveEdit(f)}>저장</button><button class="btn sm" onclick={() => (editId = null)}>취소</button></span>
								</div>
							{:else}
								<span class="nm">{f.label}</span>
								<span class="badge tone-slate">{typeLabels[f.type]}</span>
								{#if f.type === 'select'}<span class="muted opt">{f.options.join(' · ')}</span>{/if}
								{#if f.showInList}<span class="badge tone-blue">명단에 표시</span>{/if}
								<span class="row"><button class="btn sm" onclick={() => startEdit(f)}>고치기</button><button class="btn sm" onclick={() => remove(f)}>삭제</button></span>
							{/if}
						</li>
					{/each}
				</ul>
			{:else}
				<p class="muted">아직 없어요. 아래에서 골라 담거나 직접 만들어 보세요.</p>
			{/if}
		{/each}
	</section>

	<section class="card box">
		<h2>자주 쓰는 항목 · 눌러서 추가</h2>
		<div class="chips">
			{#each quick as q (q.entity + q.label)}
				<button class="chip" disabled={busy || has(q)} title={q.hint} onclick={() => run(() => createCustomField(data.dept.id, { entity: q.entity, label: q.label, type: q.type, options: q.options ?? [] }))}>
					<small>{entityLabel(q.entity)}</small> {q.label}{#if has(q)} ✓{/if}
				</button>
			{/each}
		</div>
	</section>

	<section class="card box">
		<h2>직접 만들기</h2>
		<form class="make" onsubmit={(ev) => { ev.preventDefault(); void add(); }}>
			<label class="field"><span>대상</span>
				<select class="input" bind:value={entity}><option value="student">{terms.student}</option><option value="teacher">{terms.teacher}</option></select>
			</label>
			<label class="field"><span>항목 이름</span><input class="input" bind:value={label} maxlength="40" required placeholder="예: 가정 예배 참여" /></label>
			<label class="field"><span>종류</span>
				<select class="input" bind:value={type}>{#each Object.entries(typeLabels) as [k, l] (k)}<option value={k}>{l}</option>{/each}</select>
			</label>
			{#if type === 'select'}
				<label class="field wide"><span>고를 수 있는 값 (쉼표로 구분, 2개 이상)</span><input class="input" bind:value={optionsText} placeholder="예: 매주, 가끔, 안 함" /></label>
			{/if}
			<label class="chk"><input type="checkbox" bind:checked={showInList} /> 명단 표에도 보이기</label>
			<button class="btn btn-primary" type="submit" disabled={busy}>항목 추가</button>
		</form>
		<p class="sub">종류는 만든 뒤에는 바꿀 수 없어요. (이미 적은 값의 뜻이 달라지지 않게)</p>
	</section>
</div>

<style>
	.stack { display: flex; flex-direction: column; gap: 16px; }
	.box { padding: 20px 24px; display: flex; flex-direction: column; gap: 12px; }
	h2 { font-size: 18px; font-weight: 700; }
	h3 { font-size: 15px; font-weight: 700; margin-top: 6px; }
	.sub { font-size: 13.5px; color: var(--muted); }
	.err { color: var(--danger-ink); font-weight: 700; }
	.fields { list-style: none; margin: 0; padding: 0; }
	.fields li { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 9px 0; border-top: 1px solid var(--line-soft); }
	.nm { font-weight: 600; }
	.opt { font-size: 13px; }
	.row { margin-left: auto; display: flex; gap: 6px; }
	.btn.sm { height: 32px; padding: 0 12px; font-size: 13px; }
	.edit { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; width: 100%; }
	.chips { display: flex; flex-wrap: wrap; gap: 8px; }
	.chip { height: 36px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--line-strong); background: var(--surface); font-weight: 600; color: var(--ink-2); }
	.chip small { color: var(--muted); font-weight: 500; margin-right: 2px; }
	.chip:hover:not(:disabled) { background: var(--accent-soft); border-color: var(--accent); }
	.chip:disabled { opacity: 0.55; }
	.make { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: end; }
	.wide { grid-column: 1 / -1; }
	.chk { display: flex; align-items: center; gap: 6px; font-size: 14px; min-height: 40px; }
</style>
