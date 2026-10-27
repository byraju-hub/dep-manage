import { listTeachers } from '$lib/api';

export const load = async ({ params }) => ({ teachers: await listTeachers(Number(params.dept)) });
