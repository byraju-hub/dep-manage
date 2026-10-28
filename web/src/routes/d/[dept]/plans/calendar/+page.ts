import { listEvents } from '$lib/api';
import { today } from '$lib/dates';

export const load = async ({ params, url }) => {
	const year = Number(url.searchParams.get('year')) || Number(today().slice(0, 4));
	return { year, events: await listEvents(Number(params.dept), year) };
};
