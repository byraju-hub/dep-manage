// 메모리 안의 가상 데이터베이스. 바꾼 내용은 브라우저(localStorage)에 남겨서 새로고침해도 유지된다.
import { seed, type MockDB } from './seed';

const KEY = 'dep-manage-demo-v1';

// 저장하는 항목: 사용자가 화면에서 바꿀 수 있는 것들만
type Persisted = Pick<MockDB, 'departments' | 'attendance' | 'teacherAttendance' | 'visits' | 'notices'>;

function load(): MockDB {
	const base = seed();
	try {
		const raw = localStorage.getItem(KEY);
		if (raw) {
			const saved = JSON.parse(raw) as Partial<Persisted>;
			return { ...base, ...saved };
		}
	} catch {
		// 저장소를 쓸 수 없는 환경(사생활 보호 모드 등)이면 기본 데이터로 시작한다.
	}
	return base;
}

export const db: MockDB = load();

export function persist() {
	try {
		const data: Persisted = {
			departments: db.departments,
			attendance: db.attendance,
			teacherAttendance: db.teacherAttendance,
			visits: db.visits,
			notices: db.notices
		};
		localStorage.setItem(KEY, JSON.stringify(data));
	} catch {
		// 저장 실패는 무시: 데모 화면이 멈추면 안 된다.
	}
}

export function resetDemo() {
	try {
		localStorage.removeItem(KEY);
	} catch {
		// 무시
	}
	Object.assign(db, seed());
}
