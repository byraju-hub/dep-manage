import { getChurch, getStatus } from '$lib/api';
import { defaultFonts } from '$lib/fonts';
import { url } from '$lib/url';

// 정적 SPA: 서버 렌더링 없이 브라우저에서만 그린다.
export const ssr = false;
export const prerender = false;

// 교회 이름과 글꼴은 모든 화면에서 쓰므로 맨 위에서 한 번 가져온다.
export const load = async () => {
	const status = await getStatus().catch(() => null);
	if (status && !status.installed) {
		// 아직 설치 전이면 설치 마법사(PHP)로 보낸다. 이동이 끝날 때까지 화면을 그리지 않는다.
		location.replace(url('/install/'));
		await new Promise(() => {});
	}
	const church = await getChurch().catch(() => ({ name: 'dep-manage', fonts: { ...defaultFonts } }));
	return { church };
};
