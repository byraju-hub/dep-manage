import type { EventKind, Tone, VisitMethod } from './types';

/** 아바타 색은 이 순서로 돌아가며 쓴다. */
export const avatarTones: Tone[] = ['blue', 'green', 'orange', 'pink', 'violet', 'teal'];

export function avatarTone(i: number): Tone {
	return avatarTones[Math.abs(i) % avatarTones.length];
}

/** 사역 캘린더의 일정 종류 5가지. 색만으로 구분하지 않도록 이름도 함께 보여준다. */
export const eventKinds: Record<EventKind, { label: string; tone: Tone }> = {
	ministry: { label: '사역 일정', tone: 'blue' },
	birthday: { label: '생일', tone: 'pink' },
	visit: { label: '심방', tone: 'green' },
	due: { label: '마감 · 후속', tone: 'orange' },
	meeting: { label: '회의', tone: 'violet' }
};

export const visitMethods: VisitMethod[] = ['가정', '전화', '문자·카톡', '학교', '교회', '온라인', '카페'];

export const methodTone: Record<VisitMethod, Tone> = {
	가정: 'green',
	전화: 'blue',
	'문자·카톡': 'amber',
	학교: 'violet',
	교회: 'teal',
	온라인: 'sky',
	카페: 'orange'
};

/** 출석 상태 색. 초록/보라/빨강은 흰 글자와 4.5:1 이상 대비를 갖는다. */
export const attendanceColor = {
	present: '#0f8a55',
	online: '#7c3aed',
	absent: '#e11d48'
} as const;
