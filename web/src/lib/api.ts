// 서버(PHP) API 와 이야기하는 곳. 화면은 이 파일의 함수만 부른다.
// 주소는 /api/v1/... 이고, 로그인은 세션 쿠키, 변경 요청에는 X-CSRF-Token 을 붙인다.
import { url } from './url';
import type {
	AttendanceStats,
	AttendanceStatus,
	ImportResult,
	PromotionPlan,
	StudentImportRow,
	TeacherImportRow,
	CalendarItem,
	Church,
	ClassRoom,
	Department,
	FontSettings,
	InviteRow,
	MeInfo,
	Meeting,
	MinistryPlan,
	PlanEvent,
	Role,
	SampleCounts,
	Student,
	StudentInput,
	Teacher,
	TeacherInput,
	Terms,
	Tone,
	UserRow,
	Visit
} from './types';

export class ApiError extends Error {
	constructor(
		public code: string,
		message: string,
		public status: number
	) {
		super(message);
	}
}

let csrf = '';
let onUnauthorized: () => void = () => {};

/** 로그인이 풀렸을 때 할 일(로그인 화면으로 보내기)을 session 쪽에서 등록한다. */
export function setUnauthorizedHandler(fn: () => void) {
	onUnauthorized = fn;
}

type Query = Record<string, string | number | null | undefined | boolean>;

function qs(q?: Query): string {
	if (!q) return '';
	const p = new URLSearchParams();
	for (const [k, v] of Object.entries(q)) if (v !== undefined && v !== null && v !== '' && v !== false) p.set(k, String(v));
	const s = p.toString();
	return s ? `?${s}` : '';
}

async function call<T>(method: string, path: string, body?: unknown, query?: Query): Promise<T> {
	const headers: Record<string, string> = { Accept: 'application/json' };
	if (body !== undefined) headers['Content-Type'] = 'application/json';
	if (method !== 'GET' && csrf) headers['X-CSRF-Token'] = csrf;

	let res: Response;
	try {
		res = await fetch(url(`/api/v1${path}`) + qs(query), {
			method,
			headers,
			body: body === undefined ? undefined : JSON.stringify(body),
			credentials: 'same-origin'
		});
	} catch {
		throw new ApiError('network', '서버에 연결하지 못했어요. 인터넷 연결을 확인한 뒤 다시 시도해 주세요.', 0);
	}

	let json: { ok?: boolean; data?: T; error?: { code?: string; message?: string } } | null = null;
	try {
		json = await res.json();
	} catch {
		// JSON 이 아닌 응답(서버 오류 화면 등)
	}
	if (!res.ok || !json?.ok) {
		const code = json?.error?.code ?? 'error';
		// 로그인/내 정보 확인 중의 401 은 정상 흐름이라 여기서 처리하지 않는다.
		if (res.status === 401 && !path.startsWith('/auth/')) onUnauthorized();
		throw new ApiError(code, json?.error?.message ?? '요청을 처리하지 못했어요. 잠시 뒤 다시 시도해 주세요.', res.status);
	}
	return json.data as T;
}

const get = <T>(path: string, query?: Query) => call<T>('GET', path, undefined, query);
const post = <T>(path: string, body?: unknown) => call<T>('POST', path, body ?? {});
const put = <T>(path: string, body?: unknown) => call<T>('PUT', path, body ?? {});
const patch = <T>(path: string, body?: unknown) => call<T>('PATCH', path, body ?? {});
const del = <T>(path: string) => call<T>('DELETE', path);

// ===== 시스템 · 로그인 =====
export const getStatus = () => get<{ installed: boolean; version: string; needsUpdate?: boolean }>('/system/status');

function remember(me: MeInfo): MeInfo {
	csrf = me.csrf;
	return me;
}

export async function login(loginId: string, password: string): Promise<MeInfo> {
	return remember(await post<MeInfo>('/auth/login', { loginId, password }));
}
export async function fetchMe(): Promise<MeInfo> {
	return remember(await get<MeInfo>('/auth/me'));
}
export async function logout(): Promise<void> {
	await post('/auth/logout');
	csrf = '';
}
export const changePassword = (current: string, newPassword: string) => post('/auth/password', { current, newPassword });
export const checkInvite = (code: string) => get<{ department: string | null; role: Role; roleLabel: string }>(`/invites/${encodeURIComponent(code)}/check`);
export const join = (v: { code: string; loginId: string; name: string; password: string }) => post<{ status: string }>('/auth/join', v);

// ===== 교회 =====
export const getChurch = () => get<Church>('/church');
export const updateChurchName = (name: string) => put('/church', { name });
export const updateFonts = (fonts: FontSettings) => put('/church', { fonts });

// ===== 부서 =====
export const listDepartments = () => get<Department[]>('/departments');

export async function getDepartment(id: number): Promise<Department> {
	const d = (await listDepartments()).find((x) => x.id === id);
	if (!d) throw new ApiError('not_found', '부서를 찾을 수 없습니다.', 404);
	return d;
}
export const createDepartment = (v: { name: string; pastorName: string; headName: string; terms: Terms; grades: string[] }) => post<Department>('/departments', v);
export const updateDepartmentInfo = (deptId: number, info: { name: string; pastorName: string; headName: string }) => put(`/d/${deptId}`, info);
export const updateTerms = (deptId: number, terms: Terms) => put(`/d/${deptId}/terms`, { terms });

// ===== 학생 · 교사 · 반 =====
export interface StudentRow extends Student {
	/** 학년 색. 이름과 아바타를 이 색으로 보여준다. */
	tone: Tone;
	gradeLabel: string;
	className: string;
	recent: boolean[]; // 최근 4주 출석 (오래된 순서)
}

export const listStudents = (deptId: number, opts: { q?: string; gradeId?: number | null; status?: 'enrolled' | 'all' | 'inactive'; classId?: number | null } = {}) =>
	get<StudentRow[]>(`/d/${deptId}/students`, { q: opts.q, grade: opts.gradeId, status: opts.status === 'enrolled' ? undefined : opts.status, class: opts.classId });

export const getStudent = (id: number) =>
	get<{
		student: StudentRow;
		tone: Tone;
		gradeLabel: string;
		className: string;
		history: Array<{ date: string; status: AttendanceStatus }>;
		visits: VisitRow[];
	}>(`/students/${id}`);

export const createStudent = (deptId: number, v: StudentInput) => post<{ id: number }>(`/d/${deptId}/students`, v);
export const updateStudent = (id: number, v: StudentInput) => put(`/students/${id}`, v);
export const deleteStudent = (id: number) => del(`/students/${id}`);

export const listClasses = (deptId: number) => get<Array<ClassRoom & { students: StudentRow[]; leaders: string[] }>>(`/d/${deptId}/classes`);
export const createClass = (deptId: number, v: { name: string; tone: Tone; year?: number }) => post<{ id: number }>(`/d/${deptId}/classes`, v);
export const updateClass = (id: number, v: { name: string; tone: Tone; teacherIds?: number[] }) => put(`/classes/${id}`, v);
export const deleteClass = (id: number) => del(`/classes/${id}`);

export type TeacherRow = Teacher & { classNames: string[] };
export const listTeachers = (deptId: number) => get<TeacherRow[]>(`/d/${deptId}/teachers`);
export const createTeacher = (deptId: number, v: TeacherInput) => post<{ id: number }>(`/d/${deptId}/teachers`, v);
export const updateTeacher = (id: number, v: TeacherInput) => put(`/teachers/${id}`, v);
export const deleteTeacher = (id: number) => del(`/teachers/${id}`);

// ===== 출석 =====
export const getAttendance = (deptId: number, date?: string) =>
	get<{
		date: string;
		classes: Array<Pick<ClassRoom, 'id' | 'deptId' | 'year' | 'name' | 'tone'>>;
		students: Array<StudentRow & { attendance: AttendanceStatus; checked: boolean }>;
	}>(`/d/${deptId}/attendance`, { date });

export const saveAttendance = (deptId: number, date: string, entries: Array<[number, AttendanceStatus]>) => put<{ saved: number }>(`/d/${deptId}/attendance`, { date, entries });

// ===== 심방 =====
export interface VisitRow extends Visit {
	targetNames: string[];
}
export const listVisits = (deptId: number, filter: 'all' | 'pending' = 'all') => get<VisitRow[]>(`/d/${deptId}/visits`, { filter: filter === 'pending' ? 'pending' : undefined });
export const searchPeople = (deptId: number, q: string) => (q.trim() ? get<Array<{ type: 'student' | 'teacher'; id: number; name: string; hint: string }>>(`/d/${deptId}/people`, { q }) : Promise.resolve([]));
export const saveVisit = (deptId: number, v: Omit<Visit, 'id' | 'deptId' | 'author' | 'followUpDone'>) => post<{ id: number }>(`/d/${deptId}/visits`, v);
export const completeFollowUp = (visitId: number) => post(`/visits/${visitId}/complete`);
export const deleteVisit = (visitId: number) => del(`/visits/${visitId}`);

// ===== 공지 · 일정 · 회의록 · 사역 계획 =====
export const listNotices = (deptId: number) => get<Array<{ id: number; deptId: number | null; title: string; body: string; date: string; pinned: boolean }>>(`/d/${deptId}/notices`);
export const createNotice = (deptId: number, v: { title: string; body: string; pinned: boolean; churchWide: boolean }) => post<{ id: number }>(`/d/${deptId}/notices`, v);
export const deleteNotice = (id: number) => del(`/notices/${id}`);

export const listEvents = (deptId: number, year: number) => get<PlanEvent[]>(`/d/${deptId}/events`, { year });
export type EventInput = Pick<PlanEvent, 'date' | 'endDate' | 'kind' | 'title' | 'prayer' | 'notes'>;
export const createEvent = (deptId: number, v: EventInput) => post<{ id: number }>(`/d/${deptId}/events`, v);
export const createEventsBulk = (deptId: number, events: EventInput[]) => post<{ saved: number }>(`/d/${deptId}/events/bulk`, { events });
export const updateEvent = (id: number, v: EventInput) => put(`/events/${id}`, v);
export const deleteEvent = (id: number) => del(`/events/${id}`);

export const listMeetings = (deptId: number) => get<Meeting[]>(`/d/${deptId}/meetings`);
export type MeetingInput = Pick<Meeting, 'date' | 'title' | 'content' | 'attendees'>;
export const createMeeting = (deptId: number, v: MeetingInput) => post<{ id: number }>(`/d/${deptId}/meetings`, v);
export const updateMeeting = (id: number, v: MeetingInput) => put(`/meetings/${id}`, v);
export const deleteMeeting = (id: number) => del(`/meetings/${id}`);

export const getMinistryPlan = (deptId: number, year: number) => get<MinistryPlan | null>(`/d/${deptId}/ministry-plans/${year}`);
export const saveMinistryPlan = (deptId: number, plan: MinistryPlan) => put(`/d/${deptId}/ministry-plans/${plan.year}`, plan);

// ===== 캘린더 · 대시보드 =====
export const getCalendar = (deptId: number, month: string) => get<CalendarItem[]>(`/d/${deptId}/calendar`, { month });

export const getDashboard = (deptId: number) =>
	get<{
		lastSunday: string;
		studentCount: number;
		studentDiff: number;
		enrolledTotal: number;
		teacherCount: number;
		teacherDiff: number;
		teacherTotal: number;
		newCount: number;
		birthdayCount: number;
		birthdayRange: string;
		trend: Array<{ date: string; count: number }>;
		absentees: Array<{ id: number; name: string; tone: Tone; weeks: number; gradeLabel: string; className: string }>;
		followUps: VisitRow[];
		notices: Array<{ id: number; deptId: number | null; title: string; body: string; date: string; pinned: boolean }>;
	}>(`/d/${deptId}/dashboard`);

// ===== 사용자 · 초대 (설정) =====
export const listUsers = () => get<UserRow[]>('/users');
export const updateUser = (id: number, v: { status?: 'active' | 'disabled'; name?: string; memberships?: Array<{ deptId: number; role: Role | null }> }) => patch(`/users/${id}`, v);
export const resetUserPassword = (id: number) => post<{ tempPassword: string }>(`/users/${id}/reset-password`);
export const listInvites = () => get<InviteRow[]>('/invites');
export const createInvite = (v: { deptId: number; role: Role; maxUses: number; days: number }) => post<{ id: number; code: string }>('/invites', v);
export const deleteInvite = (id: number) => del(`/invites/${id}`);

// ===== 샘플 데이터 (설정 → 데이터) =====
export const getSampleCounts = () => get<SampleCounts>('/system/sample');
export const addSampleData = () => post<SampleCounts>('/system/sample');
export const deleteSampleData = () => del<SampleCounts>('/system/sample');

// ===== 학년 단계 (설정 → 학년 · 반 구조) =====
export const createGradeLevel = (deptId: number, v: { label: string; tone: Tone }) => post<{ id: number }>(`/d/${deptId}/grade-levels`, v);
export const updateGradeLevel = (id: number, v: { label: string; tone: Tone }) => put(`/grade-levels/${id}`, v);
export const deleteGradeLevel = (id: number) => del(`/grade-levels/${id}`);


// ===== 사진 =====
export function photoUrl(type: 'student' | 'teacher', id: number, token: string | null | undefined, thumb = false): string | null {
	if (!token) return null;
	return url(`/api/v1/photos/${type}/${id}`) + `?v=${token}${thumb ? '&size=thumb' : ''}`;
}

async function sendFile<T>(path: string, file: File): Promise<T> {
	const fd = new FormData();
	fd.append('photo', file);
	let res: Response;
	try {
		// Content-Type 은 브라우저가 파일 경계(boundary)와 함께 정해야 해서 직접 쓰지 않는다.
		res = await fetch(url(`/api/v1${path}`), { method: 'POST', body: fd, credentials: 'same-origin', headers: csrf ? { 'X-CSRF-Token': csrf } : {} });
	} catch {
		throw new ApiError('network', '서버에 연결하지 못했어요.', 0);
	}
	const json = await res.json().catch(() => null);
	if (!res.ok || !json?.ok) {
		if (res.status === 401) onUnauthorized();
		// JSON 이 아닌 응답은 호스팅의 업로드 보안 장치가 막은 경우다.
		const blocked = json === null && res.status === 403 ? '서버의 보안 설정이 이 파일을 막았어요. 다른 사진으로 시도해 주세요.' : null;
		throw new ApiError(json?.error?.code ?? 'error', json?.error?.message ?? blocked ?? '올리지 못했어요.', res.status);
	}
	return json.data as T;
}

export const uploadPhoto = (type: 'student' | 'teacher', id: number, file: File) => sendFile<{ photo: string }>(`/photos/${type}/${id}`, file);
export const deletePhoto = (type: 'student' | 'teacher', id: number) => del(`/photos/${type}/${id}`);

// ===== 명단 가져오기 · 교사 출석 · 출석률 · 학년 승급 =====
export const importStudents = (deptId: number, items: StudentImportRow[]) => post<ImportResult>(`/d/${deptId}/students/bulk`, { items });
export const importTeachers = (deptId: number, items: TeacherImportRow[]) => post<ImportResult>(`/d/${deptId}/teachers/bulk`, { items });

export const getTeacherAttendance = (deptId: number, date?: string) =>
	get<{ date: string; teachers: Array<{ id: number; name: string; position: string; officerRole: string; photo: string | null; status: AttendanceStatus; checked: boolean }> }>(`/d/${deptId}/teacher-attendance`, { date });
export const saveTeacherAttendance = (deptId: number, date: string, entries: Array<[number, AttendanceStatus]>) => put<{ saved: number }>(`/d/${deptId}/teacher-attendance`, { date, entries });

export const getAttendanceStats = (deptId: number, opts: { weeks?: number; who?: 'students' | 'teachers' } = {}) =>
	get<AttendanceStats>(`/d/${deptId}/attendance/stats`, { weeks: opts.weeks, who: opts.who === 'teachers' ? 'teachers' : undefined });

export const getPromotions = (deptId: number) => get<PromotionPlan>(`/d/${deptId}/promotions`);
export const applyPromotions = (deptId: number, keepClass: boolean) => post<{ year: number; count: number }>(`/d/${deptId}/promotions/apply`, { confirm: 'promote', keepClass });

// ===== 백업 · 업데이트 =====
export const backupUrl = () => url('/api/v1/system/backup');
export const getMigrations = () => get<{ current: number; latest: number; pending: boolean }>('/system/migrations');
export const runMigrations = () => post<{ applied: number; current: number }>('/system/migrate');
