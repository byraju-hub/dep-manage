// 로그인 상태. 지금은 데모라서 아무 아이디/비밀번호로 들어갈 수 있고, 브라우저 탭을 닫으면 사라진다.
// PHP API가 붙으면 GET /api/v1/auth/me 의 응답으로 바꾼다.
import type { SessionUser } from './types';

const KEY = 'dep-manage-session';

function read(): SessionUser | null {
	try {
		const raw = sessionStorage.getItem(KEY);
		return raw ? (JSON.parse(raw) as SessionUser) : null;
	} catch {
		return null;
	}
}

export const session = $state<{ user: SessionUser | null }>({ user: read() });

export function login(loginId: string) {
	const user: SessionUser = { id: 1, name: loginId === 'admin' ? '김선생' : loginId, roleLabel: '부장 · 관리자' };
	session.user = user;
	try {
		sessionStorage.setItem(KEY, JSON.stringify(user));
	} catch {
		// 무시
	}
}

export function logout() {
	session.user = null;
	try {
		sessionStorage.removeItem(KEY);
	} catch {
		// 무시
	}
}
