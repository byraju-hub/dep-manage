import { ApiError, listInvites, listUsers } from '$lib/api';
import type { UserRow } from '$lib/types';

// 사용자 관리는 교회 관리자와 부서 관리자만 한다. (서버가 다시 확인한다.)
// 체험 계정처럼 서버가 막으면 오류 화면 대신 안내 문구를 보여준다.
export const load = async () => {
	try {
		return { users: await listUsers(), invites: await listInvites(), blocked: '' };
	} catch (e) {
		if (e instanceof ApiError && e.status === 403) {
			return { users: [] as UserRow[], invites: [] as Awaited<ReturnType<typeof listInvites>>, blocked: e.message };
		}
		throw e;
	}
};
