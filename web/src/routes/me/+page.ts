import { redirect } from '@sveltejs/kit';
import { listDepartments } from '$lib/api';
import { loadSession, session } from '$lib/session.svelte';
import { url } from '$lib/url';

export const load = async () => {
	if (!session.loaded) await loadSession();
	if (!session.user) redirect(307, url('/login'));
	return { depts: await listDepartments() };
};
