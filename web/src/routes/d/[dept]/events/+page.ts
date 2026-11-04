import { getParticipants, listEvents } from '$lib/api';

export const load = async ({ params, url }) => {
	const deptId = Number(params.dept);
	const year = Number(url.searchParams.get('year')) || new Date().getFullYear();
	const events = await listEvents(deptId, year);
	const pick = Number(url.searchParams.get('event')) || null;
	const selected = events.find((e) => e.id === pick) ?? null;
	return { year, events, selected, detail: selected ? await getParticipants(selected.id) : null };
};
