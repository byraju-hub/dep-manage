// 현재 부서의 용어. 화면 문구는 하드코딩하지 않고 t('student') 처럼 불러 쓴다.
import { DEFAULT_TERMS } from './mock/seed';
import type { Terms } from './types';

export const terms = $state<Terms>({ ...DEFAULT_TERMS });

export function setTerms(next: Terms) {
	Object.assign(terms, next);
}

export function t(key: keyof Terms): string {
	return terms[key];
}

/** "{n}기" 같은 형식에 숫자를 넣는다. */
export function cohort(n: number): string {
	return terms.cohortFormat.replace('{n}', String(n));
}
