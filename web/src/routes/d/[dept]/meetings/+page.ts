import { listMeetings } from '$lib/api';

export const load = async ({ params }) => ({ meetings: await listMeetings(Number(params.dept)) });
