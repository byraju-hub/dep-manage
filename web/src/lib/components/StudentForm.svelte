<script lang="ts">
	import { ApiError } from '../api';
	import { terms } from '../terms.svelte';
	import type { CustomField, CustomValues, Department, Guardian, StudentInput, StudentStatus } from '../types';
	import CustomFields from './CustomFields.svelte';
	import { lunarText, solarToLunar } from '../lunar';

	let {
		dept,
		classes,
		fields = [],
		initial,
		submitLabel = '저장',
		onsubmit,
		oncancel
	}: {
		dept: Department;
		classes: Array<{ id: number; name: string }>;
		fields?: CustomField[];
		initial?: Partial<StudentInput>;
		submitLabel?: string;
		onsubmit: (v: StudentInput) => Promise<void>;
		oncancel: () => void;
	} = $props();

	const first = dept.gradeLevels.find((g) => !g.isGraduate) ?? dept.gradeLevels[0];

	let name = $state(initial?.name ?? '');
	let gender = $state(initial?.gender ?? '');
	let birthDate = $state(initial?.birthDate ?? '');
	let phone = $state(initial?.phone ?? '');
	let school = $state(initial?.school ?? '');
	let gradeId = $state<number>(initial?.gradeId ?? first?.id ?? 0);
	let classId = $state<number | null>(initial?.classId ?? null);
	let status = $state<StudentStatus>(initial?.status ?? 'active');
	let baptism = $state(initial?.baptism ?? '-');
	let mentor = $state(initial?.mentor ?? '');
	let address = $state(initial?.address ?? '');
	let notes = $state(initial?.notes ?? '');
	let custom = $state<CustomValues>({ ...(initial?.custom ?? {}) });
	let guardians = $state<Guardian[]>(initial?.guardians?.length ? initial.guardians.map((g) => ({ ...g })) : [{ relation: '모', name: '', phone: '' }, { relation: '부', name: '', phone: '' }]);

	const lunarOfBirth = $derived(birthDate ? solarToLunar(birthDate) : null);
	let error = $state('');
	let busy = $state(false);

	async function submit(e: SubmitEvent) {
		e.preventDefault();
		error = '';
		if (!name.trim()) return void (error = '이름을 입력해 주세요.');
		if (!gradeId) return void (error = `${terms.grade}을(를) 선택해 주세요. (설정 → ${terms.grade} 구조에서 단계를 먼저 만들어야 해요.)`);
		busy = true;
		try {
			await onsubmit({
				name: name.trim(), gender, birthDate, phone, school, gradeId, classId, status, baptism, mentor, address, notes, custom,
				guardians: guardians.filter((g) => g.name.trim())
			});
		} catch (err) {
			error = err instanceof ApiError ? err.message : '저장하지 못했어요.';
		} finally {
			busy = false;
		}
	}
</script>

<form class="card form" onsubmit={submit} novalidate>
	<div class="grid">
		<label class="field"><span>이름 *</span><input class="input" bind:value={name} autocomplete="off" /></label>
		<label class="field"><span>성별</span>
			<select class="input" bind:value={gender}><option value="">선택 안 함</option><option value="남">남</option><option value="여">여</option></select>
		</label>
		<label class="field"><span>생년월일</span><input class="input" type="date" bind:value={birthDate} />{#if lunarOfBirth}<small class="hint">{lunarText(lunarOfBirth)}</small>{/if}</label>
		<label class="field"><span>연락처</span><input class="input" type="tel" bind:value={phone} placeholder="010-0000-0000" /></label>
		<label class="field"><span>학교</span><input class="input" bind:value={school} /></label>
		<label class="field"><span>{terms.grade} *</span>
			<select class="input" bind:value={gradeId}>{#each dept.gradeLevels as g (g.id)}<option value={g.id}>{g.label}</option>{/each}</select>
		</label>
		<label class="field"><span>{terms.class}</span>
			<select class="input" bind:value={classId}><option value={null}>{terms.class} 없음</option>{#each classes as c (c.id)}<option value={c.id}>{c.name}</option>{/each}</select>
		</label>
		<label class="field"><span>상태</span>
			<select class="input" bind:value={status}><option value="active">재적</option><option value="new">{terms.newMember}</option><option value="inactive">비활동</option></select>
		</label>
		<label class="field"><span>세례</span>
			<select class="input" bind:value={baptism}>{#each ['-', '유아', '입교', '학습', '세례'] as b (b)}<option value={b}>{b === '-' ? '없음' : b}</option>{/each}</select>
		</label>
		<label class="field"><span>인도자</span><input class="input" bind:value={mentor} /></label>
	</div>

	<label class="field"><span>주소</span><input class="input" bind:value={address} /></label>

	<fieldset>
		<legend>보호자</legend>
		{#each guardians as g, i (i)}
			<div class="g">
				<input class="input rel" bind:value={g.relation} aria-label="관계" placeholder="관계" />
				<input class="input" bind:value={g.name} aria-label="보호자 이름" placeholder="이름" />
				<input class="input" type="tel" bind:value={g.phone} aria-label="보호자 연락처" placeholder="연락처" />
				<button type="button" class="x" aria-label="보호자 줄 지우기" onclick={() => guardians.splice(i, 1)}>×</button>
			</div>
		{/each}
		{#if guardians.length < 4}
			<button type="button" class="btn sm" onclick={() => guardians.push({ relation: '', name: '', phone: '' })}>+ 보호자 추가</button>
		{/if}
	</fieldset>

	<CustomFields {fields} {birthDate} bind:values={custom} />

	<label class="field"><span>비고</span><textarea class="input" rows="3" bind:value={notes}></textarea></label>

	{#if error}<p class="err" role="alert">{error}</p>{/if}
	<div class="buttons">
		<button type="button" class="btn" onclick={oncancel}>취소</button>
		<button type="submit" class="btn btn-primary" disabled={busy}>{busy ? '저장 중…' : submitLabel}</button>
	</div>
</form>

<style>
	.form { padding: 22px 26px; display: flex; flex-direction: column; gap: 16px; max-width: 860px; }
	.grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
	fieldset { margin: 0; padding: 12px 14px; border: 1px solid var(--line); border-radius: 14px; display: flex; flex-direction: column; gap: 8px; }
	legend { padding: 0 6px; font-size: 13px; font-weight: 700; color: var(--ink-2); }
	.g { display: grid; grid-template-columns: 80px 1fr 1fr 32px; gap: 8px; align-items: center; }
	.rel { padding: 0 8px; }
	.x { width: 28px; height: 28px; border: 0; border-radius: 50%; background: transparent; color: var(--muted); font-size: 18px; }
	.x:hover { background: var(--field-bg); }
	.btn.sm { height: 34px; padding: 0 12px; font-size: 13px; align-self: flex-start; }
	.err { color: var(--danger-ink); font-weight: 700; }
	.buttons { display: flex; justify-content: flex-end; gap: 10px; }
	button:disabled { opacity: 0.6; }
	@media (max-width: 720px) {
		.form { padding: 18px 16px; }
		.grid { grid-template-columns: 1fr 1fr; }
		.g { grid-template-columns: 64px 1fr 32px; }
		.g input:nth-of-type(3) { grid-column: 1 / 3; grid-row: 2; }
	}
	@media (max-width: 480px) { .grid { grid-template-columns: 1fr; } }
	.hint { display: block; font-size: 12.5px; color: var(--muted); font-weight: 400; }
</style>
