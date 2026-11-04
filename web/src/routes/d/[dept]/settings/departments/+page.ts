import { listAllDepartments } from '$lib/api';

// 보관한 부서까지 보이는 목록은 교회 관리자만 받을 수 있다. 아니면 빈 목록(화면이 안내 문구를 보여준다).
export const load = async () => ({ all: await listAllDepartments().catch(() => []) });
