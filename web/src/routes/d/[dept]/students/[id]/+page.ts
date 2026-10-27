import { error } from '@sveltejs/kit';
import { getStudent } from '$lib/api';

export const load = async ({ params }) => {
	try {
		return { detail: await getStudent(Number(params.id)) };
	} catch {
		error(404, '학생을 찾을 수 없습니다.');
	}
};
