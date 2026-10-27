import { getAttendance } from '$lib/api';
import { sundayOf, today } from '$lib/dates';

export const load = async ({ params, url }) => {
	const date = url.searchParams.get('date') ?? sundayOf(today());
	const cls = Number(url.searchParams.get('class')) || null;
	return { date, cls, att: await getAttendance(Number(params.dept), date) };
};
