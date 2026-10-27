import { getDashboard } from '$lib/api';

export const load = async ({ params }) => {
	return { dash: await getDashboard(Number(params.dept)) };
};
