import { error } from '@sveltejs/kit';
import { getStudent, listClasses } from '$lib/api';

export const load = async ({ params }) => {
	try {
		const [detail, classes] = await Promise.all([getStudent(Number(params.id)), listClasses(Number(params.dept))]);
		return { student: detail.student, classes };
	} catch {
		error(404, '학생을 찾을 수 없습니다.');
	}
};
