import { redirect } from '@sveltejs/kit';
import { listDepartments } from '$lib/api';
import { loadSession, session } from '$lib/session.svelte';
import { url } from '$lib/url';

// 첫 화면: 로그인했으면 첫 부서로, 아니면 로그인 화면으로 보낸다.
export const load = async () => {
	if (!session.loaded) await loadSession();
	if (!session.user) redirect(307, url('/login'));
	if (session.user.mustChangePassword) redirect(307, url('/password'));
	const depts = await listDepartments();
	redirect(307, depts.length ? url(`/d/${depts[0].id}`) : url('/login?nodept=1'));
};
