import type { Terms } from './types';

/** 부서 종류별 기본 용어와 학년 단계. 설치 마법사와 용어 설정에서 함께 쓴다. */
export interface Preset {
	key: string;
	name: string;
	range: string;
	icon: string;
	tone: 'pink' | 'amber' | 'green' | 'blue' | 'orange' | 'violet' | 'teal' | 'slate';
	terms: Terms;
	grades: string[];
}

const base: Terms = {
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

export const presets: Preset[] = [
	{ key: 'infant', name: '영아부', range: '0 ~ 3세', icon: 'heart', tone: 'pink', terms: { ...base, department: '영아부', student: '아기', teacher: '선생님', class: '반', grade: '나이', head: '부서장' }, grades: ['0세', '1세', '2세', '3세'] },
	{ key: 'kinder', name: '유치부', range: '4 ~ 7세', icon: 'star', tone: 'amber', terms: { ...base, department: '유치부', student: '어린이', teacher: '선생님', head: '부서장' }, grades: ['4세', '5세', '6세', '7세'] },
	{ key: 'elementary', name: '유초등부', range: '초1 ~ 초6', icon: 'book', tone: 'green', terms: { ...base, department: '유초등부', student: '어린이', teacher: '선생님', head: '부서장' }, grades: ['초1', '초2', '초3', '초4', '초5', '초6'] },
	{ key: 'youth', name: '중고등부', range: '중1 ~ 고3', icon: 'users', tone: 'blue', terms: { ...base }, grades: ['예비 중1', '중1', '중2', '중3', '고1', '고2', '고3', '졸업'] },
	{ key: 'teens', name: '청소년부', range: '중1 ~ 고3 · 용어 다름', icon: 'zap', tone: 'orange', terms: { ...base, department: '청소년부', student: '청소년', teacher: '선생님', class: '소그룹', grade: '기수', head: '부서장', pastor: '담당목사', visit: '만남', newMember: '새친구' }, grades: ['1기', '2기', '3기', '4기', '5기', '6기'] },
	{ key: 'young', name: '청년부', range: '대학 · 청년', icon: 'compass', tone: 'violet', terms: { ...base, department: '청년부', student: '청년', teacher: '리더', class: '목장', grade: '구분', head: '회장', visit: '돌봄', newMember: '새친구' }, grades: ['대학 새내기', '대학생', '사회초년생'] },
	{ key: 'custom', name: '직접 설정', range: '처음부터 만들기', icon: 'sliders', tone: 'teal', terms: { ...base }, grades: [] },
	{ key: 'later', name: '나중에', range: '건너뛰고 설치', icon: 'clock', tone: 'slate', terms: { ...base }, grades: [] }
];
