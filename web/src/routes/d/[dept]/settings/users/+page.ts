import { listInvites, listUsers } from '$lib/api';

// 사용자 관리는 교회 관리자와 부서 관리자만 한다. (서버가 다시 확인한다.)
export const load = async () => ({ users: await listUsers(), invites: await listInvites() });
