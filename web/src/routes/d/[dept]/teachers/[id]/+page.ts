import { error } from '@sveltejs/kit';
import { listClasses, listTeachers } from '$lib/api';

export const load = async ({ params }) => {
	const [teachers, classes] = await Promise.all([listTeachers(Number(params.dept)), listClasses(Number(params.dept))]);
	const teacher = teachers.find((t) => t.id === Number(params.id));
	if (!teacher) error(404, '교사를 찾을 수 없습니다.');
	return { teacher, classes };
};
