import { getChurch } from '$lib/api';

// 정적 SPA: 서버 렌더링 없이 브라우저에서만 그린다.
export const ssr = false;
export const prerender = false;

// 교회 이름과 글꼴은 모든 화면에서 쓰므로 맨 위에서 한 번 가져온다.
export const load = async () => ({ church: await getChurch() });
