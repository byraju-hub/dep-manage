import { redirect } from '@sveltejs/kit';
import { session } from '$lib/session.svelte';
import { url } from '$lib/url';

// 첫 화면: 로그인했으면 첫 부서로, 아니면 로그인 화면으로 보낸다.
export const load = () => {
	redirect(307, session.user ? url('/d/1') : url('/login'));
};
