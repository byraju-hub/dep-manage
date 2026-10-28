import { getMinistryPlan } from '$lib/api';
import { today } from '$lib/dates';

export const load = async ({ params, url }) => {
	const year = Number(url.searchParams.get('year')) || Number(today().slice(0, 4));
	return { year, plan: await getMinistryPlan(Number(params.dept), year) };
};
