<script lang="ts">
	import { ApiError } from '../api';
	import { terms } from '../terms.svelte';
	import type { CustomField, CustomValues, TeacherInput } from '../types';
	import CustomFields from './CustomFields.svelte';
	import { lunarText, solarToLunar } from '../lunar';

	let {
		classes,
		fields = [],
		initial,
		submitLabel = '저장',
		onsubmit,
		oncancel
	}: {
		classes: Array<{ id: number; name: string }>;
		fields?: CustomField[];
		initial?: Partial<TeacherInput>;
		submitLabel?: string;
		onsubmit: (v: TeacherInput) => Promise<void>;
		oncancel: () => void;
	} = $props();

	let name = $state(initial?.name ?? '');
	let position = $state(initial?.position ?? '');
	let officerRole = $state(initial?.officerRole ?? '');
	let birthDate = $state(initial?.birthDate ?? '');
	let phone = $state(initial?.phone ?? '');
	let status = $state<'active' | 'inactive'>(initial?.status ?? 'active');
	let notes = $state(initial?.notes ?? '');
	let custom = $state<CustomValues>({ ...(initial?.custom ?? {}) });
	let classIds = $state<number[]>(initial?.classIds ? [...initial.classIds] : []);
	const lunarOfBirth = $derived(birthDate ? solarToLunar(birthDate) : null);
	let error = $state('');
	let busy = $state(false);

	function toggle(id: number) {
		classIds = classIds.includes(id) ? classIds.filter((x) => x !== id) : [...classIds, id];
	}

	async function submit(e: SubmitEvent) {
		e.preventDefault();
		error = '';
		if (!name.trim()) return void (error = '이름을 입력해 주세요.');
		busy = true;
		try {
			await onsubmit({ name: name.trim(), position, officerRole, birthDate, phone, status, notes, classIds, custom });
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
		<label class="field"><span>직분</span><input class="input" bind:value={position} placeholder="집사, 권사, 청년…" /></label>
		<label class="field"><span>임원</span><input class="input" bind:value={officerRole} placeholder="{terms.head}, 총무, 서기…" /></label>
		<label class="field"><span>생년월일</span><input class="input" type="date" bind:value={birthDate} />{#if lunarOfBirth}<small class="hint">{lunarText(lunarOfBirth)}</small>{/if}</label>
		<label class="field"><span>연락처</span><input class="input" type="tel" bind:value={phone} placeholder="010-0000-0000" /></label>
		<label class="field"><span>상태</span>
			<select class="input" bind:value={status}><option value="active">활동</option><option value="inactive">쉬는 중</option></select>
		</label>
	</div>

	<fieldset>
		<legend>담당 {terms.class}</legend>
		<div class="chips">
			{#each classes as c (c.id)}
				<label class="chip" class:on={classIds.includes(c.id)}><input type="checkbox" checked={classIds.includes(c.id)} onchange={() => toggle(c.id)} />{c.name}</label>
			{:else}
				<span class="muted">설정에서 {terms.class}을(를) 먼저 만들 수 있어요.</span>
			{/each}
		</div>
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
	fieldset { margin: 0; padding: 12px 14px; border: 1px solid var(--line); border-radius: 14px; }
	legend { padding: 0 6px; font-size: 13px; font-weight: 700; color: var(--ink-2); }
	.chips { display: flex; gap: 8px; flex-wrap: wrap; }
	.chip { display: inline-flex; align-items: center; height: 36px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--line-strong); background: var(--surface); font-weight: 600; color: var(--ink-2); cursor: pointer; }
	.chip.on { background: var(--accent); border-color: transparent; color: #fff; }
	.chip input { position: absolute; opacity: 0; pointer-events: none; }
	.chip:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 2px; }
	.err { color: var(--danger-ink); font-weight: 700; }
	.buttons { display: flex; justify-content: flex-end; gap: 10px; }
	button:disabled { opacity: 0.6; }
	@media (max-width: 720px) { .form { padding: 18px 16px; } .grid { grid-template-columns: 1fr 1fr; } }
	@media (max-width: 480px) { .grid { grid-template-columns: 1fr; } }
	.hint { display: block; font-size: 12.5px; color: var(--muted); font-weight: 400; }
</style>
