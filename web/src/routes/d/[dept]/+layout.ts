import { error, redirect } from '@sveltejs/kit';
import { listDepartments, listVisits } from '$lib/api';
import { session } from '$lib/session.svelte';
import { setTerms } from '$lib/terms.svelte';
import { url } from '$lib/url';

// 부서 화면은 로그인이 필요하다. 부서를 찾아 용어를 적용하고, 사이드바에 필요한 값을 함께 가져온다.
export const load = async ({ params }) => {
	if (!session.user) redirect(307, url('/login'));

	const depts = await listDepartments();
	const dept = depts.find((d) => d.id === Number(params.dept));
	if (!dept) error(404, '부서를 찾을 수 없습니다.');

	setTerms(dept.terms);
	const pending = (await listVisits(dept.id, 'pending')).length;
	return { dept, depts, pending };
};
