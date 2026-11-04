import { listNewcomers } from '$lib/api';

export const load = async ({ params }) => ({ newcomers: await listNewcomers(Number(params.dept)) });
