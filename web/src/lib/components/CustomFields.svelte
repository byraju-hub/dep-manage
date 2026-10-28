<script lang="ts">
	import type { CustomField, CustomValues } from '../types';

	// 학생·교사 입력 화면에 붙는 추가 항목 칸들. 값은 { 항목번호: 값 } 모양으로 values 에 담긴다.
	let { fields, values = $bindable({}) }: { fields: CustomField[]; values?: CustomValues } = $props();

	function set(id: number, v: string | number | boolean | undefined) {
		const next = { ...values };
		if (v === undefined || v === '') delete next[id];
		else next[id] = v;
		values = next;
	}
</script>

{#if fields.length}
	<fieldset>
		<legend>추가 항목</legend>
		<div class="grid">
			{#each fields as f (f.id)}
				{@const v = values[f.id]}
				{#if f.type === 'bool'}
					<label class="chk"><input type="checkbox" checked={v === true} onchange={(e) => set(f.id, e.currentTarget.checked ? true : undefined)} /> {f.label}</label>
				{:else if f.type === 'longtext'}
					<label class="field wide"><span>{f.label}</span><textarea class="input" rows="3" maxlength="2000" value={String(v ?? '')} oninput={(e) => set(f.id, e.currentTarget.value)}></textarea></label>
				{:else if f.type === 'select'}
					<label class="field"><span>{f.label}</span>
						<select class="input" value={String(v ?? '')} onchange={(e) => set(f.id, e.currentTarget.value)}>
							<option value="">선택 안 함</option>
							{#each f.options as o (o)}<option value={o}>{o}</option>{/each}
						</select>
					</label>
				{:else if f.type === 'date'}
					<label class="field"><span>{f.label}</span><input class="input" type="date" value={String(v ?? '')} onchange={(e) => set(f.id, e.currentTarget.value)} /></label>
				{:else if f.type === 'number'}
					<label class="field"><span>{f.label}</span><input class="input" type="number" value={v === undefined ? '' : String(v)} oninput={(e) => set(f.id, e.currentTarget.value === '' ? undefined : Number(e.currentTarget.value))} /></label>
				{:else}
					<label class="field"><span>{f.label}</span><input class="input" maxlength="200" value={String(v ?? '')} oninput={(e) => set(f.id, e.currentTarget.value)} /></label>
				{/if}
			{/each}
		</div>
	</fieldset>
{/if}

<style>
	fieldset { border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 12px 16px 16px; margin: 0; }
	legend { font-size: 13px; font-weight: 700; color: var(--ink-2); padding: 0 6px; }
	.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end; }
	.wide { grid-column: 1 / -1; }
	.chk { display: flex; align-items: center; gap: 8px; min-height: 40px; font-size: 14.5px; }
</style>
