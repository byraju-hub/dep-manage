// 데이터 모델. docs/ARCHITECTURE.md 의 스키마와 맞춘다.
// 지금은 가상 데이터(mock)로 채우지만, 나중에 PHP JSON API 응답이 같은 모양을 갖는다.

export type Tone = 'blue' | 'green' | 'violet' | 'sky' | 'pink' | 'orange' | 'teal' | 'amber' | 'slate';

/** 화면에 쓰는 용어. 교회마다 다르게 바꿀 수 있다. */
export interface Terms {
	department: string;
	student: string;
	teacher: string;
	class: string;
	grade: string;
	cohortFormat: string;
	head: string;
	pastor: string;
	visit: string;
	newMember: string;
}

export interface GradeLevel {
	id: number;
	label: string;
	tone: Tone | null;
	nextId: number | null;
	isGraduate?: boolean;
}

export interface Department {
	id: number;
	name: string;
	pastorName: string;
	headName: string;
	terms: Terms;
	gradeLevels: GradeLevel[];
}

export type StudentStatus = 'active' | 'new' | 'inactive';

export interface Guardian {
	relation: string;
	name: string;
	phone: string;
}

export interface Student {
	id: number;
	deptId: number;
	name: string;
	gender: '남' | '여';
	birthDate: string; // YYYY-MM-DD
	phone: string;
	school: string;
	gradeId: number;
	classId: number | null;
	status: StudentStatus;
	baptism: string;
	mentor: string;
	address: string;
	guardians: Guardian[];
	notes: string;
}

export interface Teacher {
	id: number;
	deptId: number;
	name: string;
	position: string; // 직분
	officerRole: string; // 임원
	birthDate: string;
	phone: string;
	status: 'active' | 'inactive';
	classIds: number[];
}

export interface ClassRoom {
	id: number;
	deptId: number;
	year: number;
	name: string;
	tone: Tone;
}

export type AttendanceStatus = 'present' | 'online' | 'absent';

/** 날짜(YYYY-MM-DD) → 학생 id → 상태. 기록이 없으면 아직 체크하지 않은 것. */
export type AttendanceMap = Record<string, Record<number, AttendanceStatus>>;

export type VisitMethod = '가정' | '전화' | '문자·카톡' | '학교' | '교회' | '온라인' | '카페';
export type VisitVisibility = 'private' | 'pastors' | 'staff';

export interface VisitTarget {
	type: 'student' | 'teacher';
	id: number;
}

export interface Visit {
	id: number;
	deptId: number;
	date: string;
	targets: VisitTarget[];
	method: VisitMethod;
	companions: string;
	title: string;
	content: string;
	followUp: string;
	followUpDue: string | null;
	followUpDone: boolean;
	visibility: VisitVisibility;
	author: string;
}

export type EventKind = 'ministry' | 'birthday' | 'visit' | 'due' | 'meeting';

export interface MinistryEvent {
	id: number;
	deptId: number;
	date: string;
	kind: EventKind;
	title: string;
	prayer?: string;
}

export interface Notice {
	id: number;
	deptId: number | null; // null = 교회 전체
	title: string;
	body: string;
	date: string;
	pinned: boolean;
}

export interface CalendarItem {
	date: string;
	kind: EventKind;
	label: string;
}

/** 글꼴을 따로 정할 수 있는 세 곳 */
export type FontRole = 'title' | 'menu' | 'body';
export type FontId = 'noto-sans' | 'nanum-gothic' | 'gowun-dodum' | 'black-han-sans' | 'do-hyeon' | 'jua' | 'system';
export type FontSettings = Record<FontRole, FontId>;

/** 교회 전체에 적용되는 설정 */
export interface Church {
	name: string;
	fonts: FontSettings;
}

export interface SessionUser {
	id: number;
	name: string;
	roleLabel: string;
}
