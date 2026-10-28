import { getAttendance, getTeacherAttendance } from '$lib/api';
import { sundayOf, today } from '$lib/dates';

export const load = async ({ params, url }) => {
	const deptId = Number(params.dept);
	const who = url.searchParams.get('who') === 'teachers' ? 'teachers' : 'students';
	const date = url.searchParams.get('date') ?? sundayOf(today());
	const cls = Number(url.searchParams.get('class')) || null;
	if (who === 'teachers') return { who, date, cls: null, att: null, tatt: await getTeacherAttendance(deptId, date) };
	return { who, date, cls, att: await getAttendance(deptId, date), tatt: null };
};
