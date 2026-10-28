import { lunarBirthdayIn, lunarText, parseLunarValue } from './lunar';
import type { CustomField, CustomValues } from './types';

/** 추가 항목 값을 화면에 보일 글로 바꾼다. 비어 있으면 null. */
export function showCustom(f: CustomField, values: CustomValues | undefined): string | null {
	const v = values?.[f.id];
	if (v === undefined || v === '' || v === null) return null;
	if (f.type === 'bool') return v === true ? '예' : '아니오';
	if (f.type === 'lunar') {
		const l = parseLunarValue(String(v));
		if (!l) return String(v);
		const year = new Date().getFullYear();
		const solar = lunarBirthdayIn(year, l.m, l.d, l.leap);
		return `${lunarText(l, true)}${solar ? ` · 올해 양력 ${Number(solar.slice(5, 7))}월 ${Number(solar.slice(8, 10))}일` : ''}`;
	}
	return String(v);
}
