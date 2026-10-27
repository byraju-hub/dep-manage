// 데이터 접근 계층. 화면은 이 파일의 함수만 부른다.
// 지금은 mock/db.ts 의 가상 데이터를 돌려주고, 나중에 같은 함수 모양으로 fetch('/api/v1/...')를 부르도록 바꾼다.
import { addDays, daysUntilBirthday, fromISO, monthKey, shortMD, sundayOf, today, toISO } from './dates';
import { db, persist, resetDemo } from './mock/db';
import type {
	AttendanceStatus,
	CalendarItem,
	ClassRoom,
	Department,
	MinistryEvent,
	Notice,
	Student,
	Teacher,
	Terms,
	Visit
} from './types';

const tick = () => Promise.resolve();

// ===== 부서 =====
export async function listDepartments(): Promise<Department[]> {
	await tick();
	return db.departments;
}

export async function getDepartment(id: number): Promise<Department> {
	await tick();
	const d = db.departments.find((x) => x.id === id);
	if (!d) throw new Error('부서를 찾을 수 없습니다.');
	return d;
}

export async function updateTerms(deptId: number, terms: Terms): Promise<void> {
	const d = await getDepartment(deptId);
	d.terms = { ...terms };
	d.name = terms.department;
	persist();
}

export async function updateDepartmentInfo(deptId: number, info: { name: string; pastorName: string; headName: string }): Promise<void> {
	const d = await getDepartment(deptId);
	d.name = info.name;
	d.terms = { ...d.terms, department: info.name };
	d.pastorName = info.pastorName;
	d.headName = info.headName;
	persist();
}

// ===== 학생 · 교사 · 반 =====
export interface StudentRow extends Student {
	gradeLabel: string;
	className: string;
	recent: boolean[]; // 최근 4주 출석 (오래된 순서)
}

export async function listStudents(
	deptId: number,
	opts: { q?: string; gradeId?: number | null; status?: 'enrolled' | 'all' | 'inactive' } = {}
): Promise<StudentRow[]> {
	await tick();
	const dept = await getDepartment(deptId);
	const q = (opts.q ?? '').trim();
	const status = opts.status ?? 'enrolled';
	const sundays = recentSundays(4).reverse();
	let rows = db.students.filter((s) => s.deptId === deptId);
	if (status === 'enrolled') rows = rows.filter((s) => s.status !== 'inactive');
	if (status === 'inactive') rows = rows.filter((s) => s.status === 'inactive');
	if (opts.gradeId) rows = rows.filter((s) => s.gradeId === opts.gradeId);
	if (q) rows = rows.filter((s) => s.name.includes(q) || s.school.includes(q));
	// 학년 → 반 → 이름(가나다) 순
	rows = [...rows].sort(
		(a, b) => a.gradeId - b.gradeId || (a.classId ?? 99) - (b.classId ?? 99) || a.name.localeCompare(b.name, 'ko')
	);
	return rows.map((s) => ({
		...s,
		gradeLabel: dept.gradeLevels.find((g) => g.id === s.gradeId)?.label ?? '',
		className: db.classes.find((c) => c.id === s.classId)?.name ?? '',
		recent: sundays.map((d) => (db.attendance[d]?.[s.id] ?? 'absent') !== 'absent')
	}));
}

export async function getStudent(id: number) {
	await tick();
	const s = db.students.find((x) => x.id === id);
	if (!s) throw new Error('학생을 찾을 수 없습니다.');
	const dept = await getDepartment(s.deptId);
	const sundays = recentSundays(12);
	return {
		student: s,
		gradeLabel: dept.gradeLevels.find((g) => g.id === s.gradeId)?.label ?? '',
		className: db.classes.find((c) => c.id === s.classId)?.name ?? '',
		history: sundays.map((d) => ({ date: d, status: (db.attendance[d]?.[s.id] ?? 'absent') as AttendanceStatus })),
		visits: db.visits.filter((v) => v.targets.some((t) => t.type === 'student' && t.id === id))
	};
}

export async function listClasses(deptId: number): Promise<Array<ClassRoom & { students: StudentRow[]; leaders: string[] }>> {
	await tick();
	const students = await listStudents(deptId);
	return db.classes
		.filter((c) => c.deptId === deptId)
		.map((c) => ({
			...c,
			students: students.filter((s) => s.classId === c.id),
			leaders: db.teachers.filter((t) => t.classIds.includes(c.id)).map((t) => t.name)
		}));
}

export async function listTeachers(deptId: number): Promise<Array<Teacher & { classNames: string[] }>> {
	await tick();
	return db.teachers
		.filter((t) => t.deptId === deptId)
		.map((t) => ({ ...t, classNames: t.classIds.map((id) => db.classes.find((c) => c.id === id)?.name ?? '') }));
}

// ===== 출석 =====
/** 오늘 이전(또는 오늘)의 일요일을 최근 순으로 n개. */
export function recentSundays(n: number): string[] {
	const last = sundayOf(today());
	return Array.from({ length: n }, (_, i) => addDays(last, -7 * i));
}

export async function getAttendance(deptId: number, date: string) {
	await tick();
	const students = await listStudents(deptId);
	const map = db.attendance[date] ?? {};
	return {
		date,
		classes: await listClasses(deptId),
		students: students.map((s) => ({ ...s, status: (map[s.id] ?? 'present') as AttendanceStatus, checked: s.id in map }))
	};
}

export async function setAttendance(date: string, studentId: number, status: AttendanceStatus): Promise<void> {
	await tick();
	db.attendance[date] ??= {};
	db.attendance[date][studentId] = status;
	persist();
}

/** 한꺼번에 저장: 아직 체크하지 않은 학생은 '출석'으로 기록한다. */
export async function saveAttendance(date: string, entries: Array<[number, AttendanceStatus]>): Promise<void> {
	await tick();
	db.attendance[date] ??= {};
	for (const [id, st] of entries) db.attendance[date][id] = st;
	persist();
}

// ===== 심방 =====
export interface VisitRow extends Visit {
	targetNames: string[];
}

function targetName(t: { type: 'student' | 'teacher'; id: number }): string {
	const list = t.type === 'student' ? db.students : db.teachers;
	return list.find((x) => x.id === t.id)?.name ?? '(삭제됨)';
}

export async function listVisits(deptId: number, filter: 'all' | 'pending' = 'all'): Promise<VisitRow[]> {
	await tick();
	let rows = db.visits.filter((v) => v.deptId === deptId);
	if (filter === 'pending') rows = rows.filter((v) => v.followUp && !v.followUpDone);
	return [...rows]
		.sort((a, b) => b.date.localeCompare(a.date) || b.id - a.id)
		.map((v) => ({ ...v, targetNames: v.targets.map(targetName) }));
}

export async function searchPeople(deptId: number, q: string) {
	await tick();
	const k = q.trim();
	if (!k) return [];
	return [
		...db.students.filter((s) => s.deptId === deptId && s.name.includes(k)).map((s) => ({ type: 'student' as const, id: s.id, name: s.name, hint: '학생' })),
		...db.teachers.filter((t) => t.deptId === deptId && t.name.includes(k)).map((t) => ({ type: 'teacher' as const, id: t.id, name: t.name, hint: '교사' }))
	].slice(0, 8);
}

export async function saveVisit(v: Omit<Visit, 'id'>): Promise<Visit> {
	await tick();
	const visit: Visit = { ...v, id: Math.max(0, ...db.visits.map((x) => x.id)) + 1 };
	db.visits.push(visit);
	persist();
	return visit;
}

export async function completeFollowUp(visitId: number): Promise<void> {
	await tick();
	const v = db.visits.find((x) => x.id === visitId);
	if (v) v.followUpDone = true;
	persist();
}

// ===== 공지 =====
export async function listNotices(deptId: number): Promise<Notice[]> {
	await tick();
	return db.notices
		.filter((n) => n.deptId === null || n.deptId === deptId)
		.sort((a, b) => Number(b.pinned) - Number(a.pinned) || b.date.localeCompare(a.date));
}

// ===== 사역 캘린더 =====
export async function getCalendar(deptId: number, month: string): Promise<CalendarItem[]> {
	await tick();
	const items: CalendarItem[] = [];
	const [y, m] = month.split('-').map(Number);
	const dept = await getDepartment(deptId);

	for (const e of db.events as MinistryEvent[]) {
		if (e.deptId === deptId && monthKey(e.date) === month) items.push({ date: e.date, kind: e.kind, label: e.title });
	}
	const people = [
		...db.students.filter((s) => s.deptId === deptId && s.status !== 'inactive'),
		...db.teachers.filter((t) => t.deptId === deptId)
	];
	for (const p of people) {
		const b = fromISO(p.birthDate);
		if (b.getMonth() + 1 === m) {
			items.push({ date: toISO(new Date(y, m - 1, b.getDate())), kind: 'birthday', label: `${p.name.slice(1)} 생일` });
		}
	}
	for (const v of db.visits.filter((x) => x.deptId === deptId)) {
		if (monthKey(v.date) === month) {
			const first = targetName(v.targets[0]);
			items.push({ date: v.date, kind: 'visit', label: `${first.slice(1)} ${dept.terms.visit}` });
		}
		if (v.followUp && !v.followUpDone && v.followUpDue && monthKey(v.followUpDue) === month) {
			items.push({ date: v.followUpDue, kind: 'due', label: `${targetName(v.targets[0]).slice(1)} 후속` });
		}
	}
	return items;
}

// ===== 대시보드 =====
export async function getDashboard(deptId: number) {
	await tick();
	const dept = await getDepartment(deptId);
	const sundays = recentSundays(12); // 최근 순
	const [last, prev] = sundays;
	const enrolled = db.students.filter((s) => s.deptId === deptId && s.status !== 'inactive');
	const count = (date: string) =>
		enrolled.filter((s) => (db.attendance[date]?.[s.id] ?? 'absent') !== 'absent').length;
	const teachers = db.teachers.filter((t) => t.deptId === deptId && t.status === 'active');
	const tCount = (date: string) => teachers.filter((t) => (db.teacherAttendance[date]?.[t.id] ?? 'absent') !== 'absent').length;

	const todayISO = today();
	const birthdays = [
		...enrolled.map((s) => ({ name: s.name, birth: s.birthDate })),
		...teachers.map((t) => ({ name: t.name, birth: t.birthDate }))
	].filter((p) => daysUntilBirthday(p.birth, todayISO) <= 6);

	// 연속 결석 주수 (가장 최근 주일부터 거슬러 올라가며 센다)
	const absentees = enrolled
		.map((s) => {
			let weeks = 0;
			for (const d of sundays) {
				if ((db.attendance[d]?.[s.id] ?? 'absent') === 'absent') weeks++;
				else break;
			}
			return { student: s, weeks };
		})
		.filter((x) => x.weeks >= 3)
		.sort((a, b) => b.weeks - a.weeks)
		.map((x) => ({
			id: x.student.id,
			name: x.student.name,
			weeks: x.weeks,
			gradeLabel: dept.gradeLevels.find((g) => g.id === x.student.gradeId)?.label ?? '',
			className: db.classes.find((c) => c.id === x.student.classId)?.name ?? ''
		}));

	return {
		lastSunday: last,
		studentCount: count(last),
		studentDiff: count(last) - count(prev),
		enrolledTotal: enrolled.length,
		teacherCount: tCount(last),
		teacherDiff: tCount(last) - tCount(prev),
		teacherTotal: teachers.length,
		newCount: db.students.filter((s) => s.deptId === deptId && s.status === 'new').length,
		birthdayCount: birthdays.length,
		birthdayRange: `${shortMD(todayISO)} ~ ${shortMD(addDays(todayISO, 6))}`,
		trend: [...sundays].reverse().map((d) => ({ date: d, count: count(d) })),
		absentees,
		followUps: (await listVisits(deptId, 'pending')).sort((a, b) => (a.followUpDue ?? '').localeCompare(b.followUpDue ?? '')),
		notices: await listNotices(deptId)
	};
}

export { resetDemo };
