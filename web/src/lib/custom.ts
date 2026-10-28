import type { CustomField, CustomValues } from './types';

/** 추가 항목 값을 화면에 보일 글로 바꾼다. 비어 있으면 null. */
export function showCustom(f: CustomField, values: CustomValues | undefined): string | null {
	const v = values?.[f.id];
	if (v === undefined || v === '' || v === null) return null;
	if (f.type === 'bool') return v === true ? '예' : '아니오';
	return String(v);
}
