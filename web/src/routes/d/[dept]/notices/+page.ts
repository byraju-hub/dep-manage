import { listNotices } from '$lib/api';

export const load = async ({ params }) => ({ notices: await listNotices(Number(params.dept)) });
