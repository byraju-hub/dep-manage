import { getAttendanceStats } from '$lib/api';

export const load = async ({ params, url }) => {
	const who = url.searchParams.get('who') === 'teachers' ? 'teachers' : 'students';
	const weeks = [4, 8, 12, 26, 52].includes(Number(url.searchParams.get('weeks'))) ? Number(url.searchParams.get('weeks')) : 12;
	return { who, weeks, stats: await getAttendanceStats(Number(params.dept), { who, weeks }) };
};
