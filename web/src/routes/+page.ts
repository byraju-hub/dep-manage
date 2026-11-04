import { redirect } from '@sveltejs/kit';
import { getDashboard, listDepartments, listNotices } from '$lib/api';
import { loadSession, session } from '$lib/session.svelte';
import { url } from '$lib/url';

// 첫 화면: 로그인하지 않았으면 로그인으로, 부서가 하나뿐이면 그 부서로 바로,
// 부서가 둘 이상이면(또는 ?home=1) 교회 홈(전체 공지와 부서별 요약)을 보여준다.
export const load = async ({ url: u }) => {
	if (!session.loaded) await loadSession();
	if (!session.user) redirect(307, url('/login'));
	if (session.user.mustChangePassword) redirect(307, url('/password'));
	const depts = await listDepartments();
	if (!depts.length) redirect(307, url('/login?nodept=1'));
	if (depts.length === 1 && u.searchParams.get('home') !== '1') redirect(307, url(`/d/${depts[0].id}`));

	// 부서별 요약. 열람 역할은 일부 숫자가 비어 있을 수 있다.
	const summaries = await Promise.all(
		depts.map(async (d) => {
			const s = await getDashboard(d.id).catch(() => null);
			return { dept: d, summary: s };
		})
	);
	const notices = (await listNotices(depts[0].id).catch(() => [])).filter((n) => n.deptId === null).slice(0, 5);
	return { depts, summaries, notices };
};
