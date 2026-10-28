import { listClasses } from '$lib/api';

export const load = async ({ params }) => ({ classes: await listClasses(Number(params.dept)) });
