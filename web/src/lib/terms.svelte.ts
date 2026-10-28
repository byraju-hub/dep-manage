// 현재 부서의 용어. 화면 문구는 하드코딩하지 않고 t('student') 처럼 불러 쓴다.
import type { Terms } from './types';

export const DEFAULT_TERMS: Terms = {
	department: '중고등부',
	student: '학생',
	teacher: '교사',
	class: '반',
	grade: '학년',
	cohortFormat: '{n}기',
	head: '부장',
	pastor: '교역자',
	visit: '심방',
	newMember: '새가족'
};

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
