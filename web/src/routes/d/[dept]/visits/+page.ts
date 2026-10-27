import { listVisits } from '$lib/api';

export const load = async ({ params, url }) => {
	const filter = url.searchParams.get('filter') === 'pending' ? 'pending' : 'all';
	// 학생 상세에서 "심방 기록"을 눌러 넘어온 경우 대상을 미리 채운다.
	const target = Number(url.searchParams.get('student')) || null;
	return { filter, target, visits: await listVisits(Number(params.dept), filter) };
};
