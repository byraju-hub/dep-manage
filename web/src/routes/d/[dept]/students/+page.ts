import { listClasses, listStudents } from '$lib/api';

export const load = async ({ params, url }) => {
	const deptId = Number(params.dept);
	const view = url.searchParams.get('view') ?? 'basic';
	const q = url.searchParams.get('q') ?? '';
	const grade = Number(url.searchParams.get('grade')) || null;
	const status = (url.searchParams.get('status') as 'enrolled' | 'all' | 'inactive') ?? 'enrolled';

	return {
		view,
		q,
		grade,
		status,
		students: await listStudents(deptId, { q, gradeId: grade, status }),
		classes: view === 'class' ? await listClasses(deptId) : []
	};
};
