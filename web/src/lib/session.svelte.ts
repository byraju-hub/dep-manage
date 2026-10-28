// 로그인한 사람과 그 사람의 부서별 역할. 서버(세션 쿠키)가 진짜 기준이고, 여기는 화면에 보여주려고 들고 있는 사본이다.
import { goto } from '$app/navigation';
import * as api from './api';
import type { MeInfo, Membership, Role, SessionUser } from './types';
import { url } from './url';

export const session = $state<{ user: SessionUser | null; memberships: Membership[]; loaded: boolean }>({
	user: null,
	memberships: [],
	loaded: false
});

function apply(me: MeInfo) {
	session.user = me.user;
	session.memberships = me.memberships;
	session.loaded = true;
}

function clear() {
	session.user = null;
	session.memberships = [];
	session.loaded = true;
}

/** 서버에 지금 로그인되어 있는지 물어본다. */
export async function loadSession(): Promise<boolean> {
	try {
		apply(await api.fetchMe());
		return true;
	} catch {
		clear();
		return false;
	}
}

export async function doLogin(loginId: string, password: string): Promise<void> {
	apply(await api.login(loginId, password));
}

export async function doLogout(): Promise<void> {
	try {
		await api.logout();
	} catch {
		// 이미 로그아웃된 상태여도 화면은 로그인으로 보낸다.
	}
	clear();
	await goto(url('/login'));
}

// 쓰는 도중에 로그인이 풀리면 로그인 화면으로 보낸다.
api.setUnauthorizedHandler(() => {
	clear();
	void goto(url('/login'));
});

const RANK: Record<string, number> = { viewer: 1, teacher: 2, admin: 3, owner: 4 };

/** 그 부서에서의 내 역할. owner(교회 관리자)는 모든 부서에서 owner. */
export function roleIn(deptId: number): 'owner' | Role | null {
	if (session.user?.isOwner) return 'owner';
	return session.memberships.find((m) => m.deptId === deptId)?.role ?? null;
}

/** 그 부서에서 이 역할 이상인지. 버튼을 보여줄지 정하는 데만 쓰고, 진짜 권한 확인은 서버가 한다. */
export function can(deptId: number, min: Role | 'owner'): boolean {
	const r = roleIn(deptId);
	return r !== null && RANK[r] >= RANK[min];
}
