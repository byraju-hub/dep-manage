import { listCustomFields } from '$lib/api';

export const load = async ({ params }) => ({ fields: await listCustomFields(Number(params.dept)) });
