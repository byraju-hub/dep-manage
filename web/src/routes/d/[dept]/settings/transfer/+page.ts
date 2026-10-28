import { listStudents } from '$lib/api';

export const load = async ({ params }) => ({ students: await listStudents(Number(params.dept), { status: 'all' }) });
