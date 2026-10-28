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
	/** 사진 토큰(없으면 null). 주소를 만들 때 쓴다. */
	photo?: string | null;
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
	photo?: string | null;
	notes?: string;
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

export type Role = 'admin' | 'teacher' | 'viewer';

export interface SessionUser {
	id: number;
	name: string;
	loginId: string;
	isOwner: boolean;
	mustChangePassword: boolean;
	roleLabel: string;
}

export interface Membership {
	deptId: number;
	role: Role;
}

/** GET /auth/me 와 로그인 응답 */
export interface MeInfo {
	user: SessionUser;
	memberships: Membership[];
	csrf: string;
}

export interface UserRow {
	id: number;
	loginId: string;
	name: string;
	isOwner: boolean;
	status: 'pending' | 'active' | 'disabled';
	lastLoginAt: string | null;
	createdAt: string;
	memberships: Array<{ deptId: number; deptName: string; role: Role }>;
}

export interface InviteRow {
	id: number;
	code: string;
	deptId: number | null;
	deptName: string | null;
	role: Role;
	expiresAt: string | null;
	maxUses: number;
	usedCount: number;
}

export interface PlanEvent {
	id: number;
	deptId: number;
	date: string;
	endDate: string | null;
	kind: EventKind;
	title: string;
	prayer: string;
	notes: string;
}

export interface Meeting {
	id: number;
	deptId: number;
	date: string;
	title: string;
	content: string;
	attendees: string;
}

export interface MinistryPlanItem {
	id?: number;
	area: string;
	title: string;
	description: string;
	period: string;
	owner: string;
	budget: number;
	status: 'todo' | 'doing' | 'done';
}

export interface MinistryPlan {
	year: number;
	theme: string;
	themeVerse: string;
	vision: string;
	goals: string;
	status: 'draft' | 'final';
	items: MinistryPlanItem[];
}

/** 학생을 새로 만들거나 고칠 때 보내는 값 */
export interface StudentInput {
	name: string;
	gender: string;
	birthDate: string;
	phone: string;
	school: string;
	gradeId: number;
	classId: number | null;
	status: StudentStatus;
	baptism: string;
	mentor: string;
	address: string;
	notes: string;
	guardians: Guardian[];
}

export interface TeacherInput {
	name: string;
	position: string;
	officerRole: string;
	birthDate: string;
	phone: string;
	status: 'active' | 'inactive';
	notes: string;
	classIds: number[];
}

export interface SampleCounts {
	departments: number;
	students: number;
	teachers: number;
	visits: number;
	events: number;
	notices: number;
	meetings: number;
}

/** 엑셀에서 읽은 한 줄 (몇 번째 줄인지 함께 보낸다) */
export type StudentImportRow = StudentInput & { row: number };
export type TeacherImportRow = TeacherInput & { row: number };

export interface ImportResult {
	created: number;
	skipped: Array<{ row: number; name: string }>;
	errors: Array<{ row: number; name: string; message: string }>;
}

export interface AttendanceStats {
	who: 'students' | 'teachers';
	weeks: number;
	from: string;
	to: string;
	checkedWeeks: number;
	overall: { rate: number | null; present: number; online: number; absent: number };
	people: Array<{ id: number; name: string; tone: Tone; gradeLabel: string; className: string; present: number; online: number; absent: number; rate: number | null }>;
	classes: Array<{ id: number; name: string; tone: Tone; members: number; rate: number | null }>;
	byDate: Array<{ date: string; attended: number; recorded: number }>;
}

export interface PromotionChange {
	id: number;
	name: string;
	fromLabel: string;
	toLabel: string;
	toTone: Tone;
	graduate: boolean;
	enroll: boolean;
}

export interface PromotionPlan {
	year: number;
	month: number;
	applied: { at: string; count: number } | null;
	changes: PromotionChange[];
}
