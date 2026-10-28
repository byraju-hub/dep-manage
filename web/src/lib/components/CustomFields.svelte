<script lang="ts">
	import { lunarBirthdayIn, lunarText, formatLunarValue, parseLunarValue, solarToLunar, type Lunar } from '../lunar';
	import type { CustomField, CustomValues } from '../types';

	// 학생·교사 입력 화면에 붙는 추가 항목 칸들. 값은 { 항목번호: 값 } 모양으로 values 에 담긴다.
	let { fields, birthDate = '', values = $bindable({}) }: { fields: CustomField[]; birthDate?: string; values?: CustomValues } = $props();

	const thisYear = new Date().getFullYear();
	const lunarOf = (id: number): Lunar | null => parseLunarValue(String(values[id] ?? ''));
	function setLunar(id: number, patch: Partial<Lunar>) {
		const cur = lunarOf(id) ?? { y: Number(birthDate.slice(0, 4)) || 2010, m: 1, d: 1, leap: false };
		set(id, formatLunarValue({ ...cur, ...patch }));
	}
	function fromSolar(id: number) {
		const l = solarToLunar(birthDate);
		if (l) set(id, formatLunarValue(l));
	}

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
				{:else if f.type === 'lunar'}
					{@const l = lunarOf(f.id)}
					{@const solar = l ? lunarBirthdayIn(thisYear, l.m, l.d, l.leap) : null}
					<div class="field wide">
						<span>{f.label}</span>
						<div class="lunar">
							<span>음력</span>
							<input class="input y" type="number" min="1900" max="2100" aria-label="{f.label} 연도" value={l?.y ?? ''} oninput={(e) => Number(e.currentTarget.value) && setLunar(f.id, { y: Number(e.currentTarget.value) })} />년
							<select class="input m" aria-label="{f.label} 월" value={l?.m ?? ''} onchange={(e) => setLunar(f.id, { m: Number(e.currentTarget.value) })}>
								<option value="" disabled>월</option>{#each Array.from({ length: 12 }, (_, i) => i + 1) as n (n)}<option value={n}>{n}월</option>{/each}
							</select>
							<select class="input m" aria-label="{f.label} 일" value={l?.d ?? ''} onchange={(e) => setLunar(f.id, { d: Number(e.currentTarget.value) })}>
								<option value="" disabled>일</option>{#each Array.from({ length: 30 }, (_, i) => i + 1) as n (n)}<option value={n}>{n}일</option>{/each}
							</select>
							<label class="chk"><input type="checkbox" checked={l?.leap ?? false} onchange={(e) => setLunar(f.id, { leap: e.currentTarget.checked })} /> 윤달</label>
							{#if birthDate}<button type="button" class="btn sm" onclick={() => fromSolar(f.id)}>생년월일로 채우기</button>{/if}
							{#if l}<button type="button" class="btn sm" onclick={() => set(f.id, undefined)}>지우기</button>{/if}
						</div>
						{#if l}<small class="hint">{lunarText(l, true)}{solar ? ` → 올해(${thisYear}) 양력 ${Number(solar.slice(5, 7))}월 ${Number(solar.slice(8, 10))}일` : ` → 올해(${thisYear})에는 해당하는 날이 없어요`}</small>{/if}
					</div>
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
	.lunar { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
	.lunar .y { width: 92px; }
	.lunar .m { width: 78px; }
	.btn.sm { height: 34px; padding: 0 10px; font-size: 13px; }
	.hint { display: block; font-size: 12.5px; color: var(--muted); font-weight: 400; margin-top: 2px; }
	.chk { display: flex; align-items: center; gap: 8px; min-height: 40px; font-size: 14.5px; }
</style>
