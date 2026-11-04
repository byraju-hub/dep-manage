import { listPrayers, listStudents, listTeachers } from '$lib/api';

export const load = async ({ params, url }) => {
	const deptId = Number(params.dept);
	const status = (url.searchParams.get('status') as 'praying' | 'answered' | 'all') ?? 'praying';
	const [prayers, students, teachers] = await Promise.all([
		listPrayers(deptId, status),
		listStudents(deptId, { q: '', gradeId: null, status: 'enrolled' }),
		listTeachers(deptId)
	]);
	return {
		status,
		prayers,
		people: [
			...students.map((s) => ({ type: 'student' as const, id: s.id, name: s.name })),
			...teachers.map((t) => ({ type: 'teacher' as const, id: t.id, name: t.name }))
		]
	};
};
