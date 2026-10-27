// 가상 데이터 생성기. 실제 교회의 정보는 하나도 들어 있지 않다.
// 날짜는 오늘을 기준으로 상대적으로 만들어서 언제 열어도 화면이 비어 보이지 않는다.
import { addDays, fromISO, sundayOf, today, toISO } from '../dates';
import type {
	AttendanceMap,
	AttendanceStatus,
	ClassRoom,
	Department,
	GradeLevel,
	MinistryEvent,
	Notice,
	Student,
	Teacher,
	Terms,
	Visit
} from '../types';

export interface MockDB {
	departments: Department[];
	students: Student[];
	teachers: Teacher[];
	classes: ClassRoom[];
	attendance: AttendanceMap; // 학생
	teacherAttendance: AttendanceMap;
	visits: Visit[];
	events: MinistryEvent[];
	notices: Notice[];
}

// 같은 순서로 같은 값이 나오는 난수 (mulberry32)
function rng(seed: number) {
	let a = seed >>> 0;
	return () => {
		a = (a + 0x6d2b79f5) >>> 0;
		let t = a;
		t = Math.imul(t ^ (t >>> 15), t | 1);
		t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
		return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
	};
}

const SURNAMES = ['김', '이', '박', '최', '정', '강', '조', '윤', '장', '임', '한', '오', '서', '신', '권', '황', '안', '송', '류', '홍', '배', '문', '양', '손', '백'];
const GIVEN = [
	'하준', '서윤', '도윤', '예린', '시우', '지안', '민성', '하은', '우진', '채원', '서아', '현우', '지민', '서진', '하늘', '태윤',
	'민재', '도현', '유나', '지호', '예은', '길현', '유진', '도하', '가은', '서우', '지우', '재원', '하린', '승민', '서현', '민호',
	'세린', '수빈', '지유', '세영', '준서', '아름', '다은', '건우', '소윤', '이준', '하영', '주원', '시현', '나윤', '은우', '라희',
	'태오', '유빈', '지환', '소율', '재이', '다온', '선우', '예준', '아인', '리안', '해윤', '도영'
];
const SCHOOLS = ['은혜중', '새빛중', '한빛중', '대성고', '푸른고', '샘물중'];
const POSITIONS = ['집사', '권사', '청년', '집사', '집사', '권사'];
const OFFICERS = ['부장', '총무', '서기', '회계', '찬양', '', '', '', '', '', '', ''];

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

function grades(labels: Array<[string, GradeLevel['tone']]>): GradeLevel[] {
	return labels.map(([label, tone], i) => ({
		id: i + 1,
		label,
		tone,
		nextId: i < labels.length - 1 ? i + 2 : null,
		isGraduate: label === '졸업'
	}));
}

export function seed(): MockDB {
	const rand = rng(20261025);
	const pick = <T>(arr: T[]): T => arr[Math.floor(rand() * arr.length)];
	const todayISO = today();
	const lastSunday = sundayOf(todayISO);

	// ===== 부서 =====
	const dept1: Department = {
		id: 1,
		name: '중고등부',
		pastorName: '김은혜 목사',
		headName: '박성실 집사',
		terms: { ...DEFAULT_TERMS },
		gradeLevels: grades([
			['예비 중1', null], ['중1', 'blue'], ['중2', 'green'], ['중3', 'orange'],
			['고1', 'pink'], ['고2', 'violet'], ['고3', 'teal'], ['졸업', 'slate']
		])
	};
	const dept2: Department = {
		id: 2,
		name: '청년부',
		pastorName: '이소망 목사',
		headName: '정다윗 청년',
		terms: { ...DEFAULT_TERMS, department: '청년부', student: '청년', teacher: '리더', class: '목장', head: '회장', visit: '돌봄', newMember: '새친구' },
		gradeLevels: grades([['대학 새내기', 'sky'], ['대학생', 'blue'], ['사회초년생', 'green'], ['졸업', 'slate']])
	};

	// ===== 반 =====
	const year = new Date().getFullYear();
	const classes: ClassRoom[] = [
		{ id: 1, deptId: 1, year, name: '중1 A반', tone: 'blue' },
		{ id: 2, deptId: 1, year, name: '중1 B반', tone: 'green' },
		{ id: 3, deptId: 1, year, name: '중2', tone: 'orange' },
		{ id: 4, deptId: 1, year, name: '중3', tone: 'pink' },
		{ id: 5, deptId: 1, year, name: '고등 A반', tone: 'violet' },
		{ id: 6, deptId: 1, year, name: '고등 B반', tone: 'teal' },
		{ id: 7, deptId: 2, year, name: '새싹 목장', tone: 'green' },
		{ id: 8, deptId: 2, year, name: '바다 목장', tone: 'sky' }
	];
	const classFor = (gradeId: number): number | null => {
		if (gradeId === 2) return rand() < 0.5 ? 1 : 2;
		if (gradeId === 3) return 3;
		if (gradeId === 4) return 4;
		if (gradeId >= 5 && gradeId <= 7) return rand() < 0.5 ? 5 : 6;
		return null;
	};

	// ===== 학생 =====
	const usedNames = new Set<string>();
	const makeName = () => {
		for (let i = 0; i < 200; i++) {
			const n = pick(SURNAMES) + pick(GIVEN);
			if (!usedNames.has(n)) {
				usedNames.add(n);
				return n;
			}
		}
		return pick(SURNAMES) + pick(GIVEN);
	};
	const birthFor = (gradeLabel: string) => {
		const base: Record<string, number> = { 중1: 13, 중2: 14, 중3: 15, 고1: 16, 고2: 17, 고3: 18, '예비 중1': 12 };
		const y = year - (base[gradeLabel] ?? 20) - 0;
		return `${y}-${String(1 + Math.floor(rand() * 12)).padStart(2, '0')}-${String(1 + Math.floor(rand() * 28)).padStart(2, '0')}`;
	};

	const students: Student[] = [];
	let sid = 1;
	const addStudent = (deptId: number, gradeId: number, gradeLabel: string, status: Student['status'], overrides: Partial<Student> = {}) => {
		const name = overrides.name ?? makeName();
		const gender = rand() < 0.5 ? '남' : '여';
		const parentSurname = name[0];
		students.push({
			id: sid++,
			deptId,
			name,
			gender,
			birthDate: birthFor(gradeLabel),
			phone: `010-${String(1000 + Math.floor(rand() * 8999))}-${String(1000 + Math.floor(rand() * 8999))}`,
			school: pick(SCHOOLS),
			gradeId,
			classId: deptId === 1 ? classFor(gradeId) : gradeId === 4 ? null : pick([7, 8]),
			status,
			baptism: pick(['-', '유아', '입교', '학습', '세례']),
			mentor: status === 'new' ? pick(['이상현', '박은지', '한소영', '김민석']) : '',
			address: '',
			guardians: [
				{ relation: '모', name: parentSurname + pick(['미선', '은정', '현주', '영숙', '지혜']), phone: `010-${String(1000 + Math.floor(rand() * 8999))}-${String(1000 + Math.floor(rand() * 8999))}` },
				{ relation: '부', name: parentSurname + pick(['성수', '진호', '영훈', '기석', '동현']), phone: `010-${String(1000 + Math.floor(rand() * 8999))}-${String(1000 + Math.floor(rand() * 8999))}` }
			],
			notes: '',
			...overrides
		});
	};

	// 중고등부: 재적 43, 새가족 11, 비활동 6
	const dist: Array<[number, string, number, number]> = [
		// gradeId, label, 재적, 새가족
		[2, '중1', 10, 5], [3, '중2', 6, 2], [4, '중3', 7, 1], [5, '고1', 8, 2], [6, '고2', 7, 1], [7, '고3', 5, 0]
	];
	for (const [gid, label, act, nw] of dist) {
		for (let i = 0; i < act; i++) addStudent(1, gid, label, 'active');
		for (let i = 0; i < nw; i++) addStudent(1, gid, label, 'new');
	}
	for (let i = 0; i < 4; i++) addStudent(1, 8, '졸업', 'inactive', { classId: null });
	for (let i = 0; i < 2; i++) addStudent(1, 1, '예비 중1', 'inactive', { classId: null });
	// 청년부 12명
	for (let i = 0; i < 12; i++) addStudent(2, 1 + (i % 3), '대학', i % 6 === 5 ? 'new' : 'active');

	// 이야기가 되도록 몇 명의 이름과 생일을 고정한다 (심방/결석 화면에서 눈에 띄는 이름).
	const named: Array<[number, string]> = [[1, '김하준'], [2, '박서윤'], [3, '정시우'], [4, '오세린'], [5, '이서아'], [6, '최민재'], [7, '윤도현'], [8, '한지우'], [9, '정하윤'], [10, '강지안']];
	for (const [id, nm] of named) {
		const s = students.find((x) => x.id === id)!;
		usedNames.delete(s.name);
		s.name = nm;
		usedNames.add(nm);
	}
	// 이번 주에 생일인 학생이 몇 명 있도록 한다
	[[1, 1], [2, 2], [10, 4], [20, 6]].forEach(([id, plus]) => {
		const s = students.find((x) => x.id === id)!;
		const bd = fromISO(addDays(todayISO, plus));
		const y = fromISO(s.birthDate).getFullYear();
		s.birthDate = toISO(new Date(y, bd.getMonth(), bd.getDate()));
	});

	// ===== 교사 =====
	const teachers: Teacher[] = [];
	const teacherDefs: Array<[string, number[]]> = [
		['이상현', [1]], ['박은지', [1]], ['한소영', [2]], ['김민석', [3]], ['정은혜', [4]], ['박성훈', [5]],
		['이지안', [5]], ['최영진', [6]], ['오하나', []], ['장믿음', []], ['서기쁨', [2]], ['류사랑', []]
	];
	teacherDefs.forEach(([name, classIds], i) => {
		teachers.push({
			id: i + 1,
			deptId: 1,
			name,
			position: POSITIONS[i % POSITIONS.length],
			officerRole: OFFICERS[i % OFFICERS.length],
			birthDate: `${1975 + (i % 20)}-${String(1 + ((i * 5) % 12)).padStart(2, '0')}-${String(3 + ((i * 7) % 24)).padStart(2, '0')}`,
			phone: `010-${String(2000 + i * 137)}-${String(5000 + i * 211)}`,
			status: 'active',
			classIds
		});
	});
	['정다윗', '이은총', '박소망', '김평안'].forEach((name, i) => {
		teachers.push({ id: 13 + i, deptId: 2, name, position: '청년', officerRole: i === 0 ? '회장' : '', birthDate: `${1996 + i}-0${3 + i}-1${i}`, phone: `010-3${i}00-4${i}00`, status: 'active', classIds: i < 2 ? [7] : [8] });
	});

	// ===== 출석 (최근 12주) =====
	const attendance: AttendanceMap = {};
	const teacherAttendance: AttendanceMap = {};
	const longAbsent = new Map<number, number>([[6, 4], [8, 3], [4, 3]]); // 학생 id → 최근 연속 결석 주수
	for (let w = 0; w < 12; w++) {
		const date = addDays(lastSunday, -7 * w);
		attendance[date] = {};
		teacherAttendance[date] = {};
		for (const s of students) {
			if (s.status === 'inactive') continue;
			const streak = longAbsent.get(s.id);
			if (streak !== undefined) {
				attendance[date][s.id] = w < streak ? 'absent' : 'present';
				continue;
			}
			// 가끔 결석하는 학생, 매주 오는 학생이 섞이도록 학생마다 출석 성향을 다르게 한다.
			const habit = 0.55 + ((s.id * 37) % 40) / 100; // 0.55 ~ 0.94
			const r = rand();
			const st: AttendanceStatus = r < habit ? 'present' : r < habit + 0.06 ? 'online' : 'absent';
			attendance[date][s.id] = st;
		}
		for (const t of teachers) {
			if (t.deptId !== 1) continue;
			teacherAttendance[date][t.id] = rand() < 0.88 ? 'present' : 'absent';
		}
	}
	// 지난 주일 교사는 12명 중 10명 출석으로 맞춘다
	const lastT = teacherAttendance[lastSunday];
	let presentT = Object.values(lastT).filter((v) => v === 'present').length;
	for (const t of teachers.filter((x) => x.deptId === 1)) {
		if (presentT === 10) break;
		if (presentT > 10 && lastT[t.id] === 'present') { lastT[t.id] = 'absent'; presentT--; }
		else if (presentT < 10 && lastT[t.id] === 'absent') { lastT[t.id] = 'present'; presentT++; }
	}

	// ===== 심방 =====
	const visits: Visit[] = [
		{ id: 1, deptId: 1, date: addDays(todayISO, -1), targets: [{ type: 'student', id: 9 }], method: '가정', companions: '이상현, 박은지', title: '어머니와 진로 상담', content: '시험 기간이라 많이 지쳐 있었음. 어머니는 예배 시간에 맞춰 데려다주는 것이 어렵다고 하심. 다음 달에 진로 상담을 이어가기로 함.', followUp: '진로 상담 일정 잡기', followUpDue: addDays(todayISO, 5), followUpDone: false, visibility: 'pastors', author: '김선생' },
		{ id: 2, deptId: 1, date: addDays(todayISO, -4), targets: [{ type: 'student', id: 7 }], method: '전화', companions: '', title: '시험 기간 안부', content: '시험 끝나고 연락하기로 함.', followUp: '시험 끝난 뒤 연락하기', followUpDue: addDays(todayISO, 8), followUpDone: false, visibility: 'pastors', author: '김선생' },
		{ id: 3, deptId: 1, date: addDays(todayISO, -7), targets: [{ type: 'student', id: 6 }], method: '문자·카톡', companions: '', title: '연속 결석 확인', content: '카톡 확인했으나 답이 없음.', followUp: '', followUpDue: null, followUpDone: false, visibility: 'pastors', author: '이상현' },
        { id: 4, deptId: 1, date: addDays(todayISO, -14), targets: [{ type: 'student', id: 3 }, { type: 'student', id: 1 }], method: '교회', companions: '박은지', title: '새가족 정착 면담', content: '예배 후 함께 점심을 먹으며 이야기 나눔.', followUp: '', followUpDue: null, followUpDone: false, visibility: 'staff', author: '박은지' },
		{ id: 5, deptId: 1, date: addDays(todayISO, -21), targets: [{ type: 'teacher', id: 2 }], method: '카페', companions: '', title: '교사 격려 모임', content: '', followUp: '', followUpDue: null, followUpDone: false, visibility: 'private', author: '김선생' },
		{ id: 6, deptId: 1, date: addDays(todayISO, -28), targets: [{ type: 'student', id: 4 }], method: '가정', companions: '한소영', title: '이사 후 안부 방문', content: '', followUp: '', followUpDue: null, followUpDone: true, visibility: 'pastors', author: '한소영' }
	];

	// ===== 일정 =====
	const events: MinistryEvent[] = [];
	let eid = 1;
	// 주일 공과: 앞뒤 3개월
	for (let w = -14; w <= 14; w++) {
		events.push({ id: eid++, deptId: 1, date: addDays(lastSunday, w * 7), kind: 'ministry', title: '주일 공과', prayer: w % 2 ? '이상현 선생' : '박은지 선생' });
	}
	const nextSunday = addDays(lastSunday, 7);
	events.push({ id: eid++, deptId: 1, date: addDays(nextSunday, 7), kind: 'ministry', title: '추수감사 예배' });
	events.push({ id: eid++, deptId: 1, date: addDays(nextSunday, 14), kind: 'meeting', title: '교사회의' });
	events.push({ id: eid++, deptId: 1, date: addDays(todayISO, 6), kind: 'meeting', title: '교사회의' });
	events.push({ id: eid++, deptId: 1, date: addDays(todayISO, 6), kind: 'due', title: '수련회 신청' });
	events.push({ id: eid++, deptId: 1, date: addDays(nextSunday, 21), kind: 'ministry', title: '겨울 수련회 준비' });

	// ===== 공지 =====
	const notices: Notice[] = [
		{ id: 1, deptId: null, title: '11월 학부모 간담회 안내', body: '11월 셋째 주일 예배 후 본당에서 진행합니다.', date: addDays(todayISO, -3), pinned: false },
		{ id: 2, deptId: 1, title: '겨울 수련회 장소 투표', body: '다음 주일까지 투표해 주세요.', date: addDays(todayISO, -7), pinned: true },
		{ id: 3, deptId: 1, title: '찬양팀 연습 시간 변경', body: '연습은 예배 30분 전에 시작합니다.', date: addDays(todayISO, -14), pinned: false }
	];

	return {
		departments: [dept1, dept2],
		students,
		teachers,
		classes,
		attendance,
		teacherAttendance,
		visits,
		events,
		notices
	};
}
