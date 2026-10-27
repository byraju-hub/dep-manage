// 선택할 수 있는 글꼴과, 타이틀 · 메뉴 · 내용 세 곳에 적용하는 도우미.
// 글꼴 파일은 앱에 포함되어 있고(외부 글꼴 서비스를 쓰지 않는다), 선택된 글꼴만 불러온다.
import type { FontId, FontRole, FontSettings } from './types';

export interface FontDef {
	id: FontId;
	label: string;
	/** 어떤 느낌인지 */
	note: string;
	/** CSS font-family 값 */
	stack: string;
	/** 글꼴 파일(CSS)을 불러오는 함수. 시스템 글꼴은 불러올 것이 없다. */
	load?: () => Promise<unknown>;
	/** 굵은 글자(700)가 따로 있는지. 없으면 굵게 해도 그대로 보인다. */
	hasBold: boolean;
}

const FALLBACK = "'Malgun Gothic', 'Apple SD Gothic Neo', system-ui, sans-serif";

export const fonts: FontDef[] = [
	{ id: 'noto-sans', label: 'Noto Sans KR', note: '무난하고 깔끔한 고딕 (기본)', stack: `'Noto Sans KR Variable', ${FALLBACK}`, load: () => import('@fontsource-variable/noto-sans-kr'), hasBold: true },
	{ id: 'nanum-gothic', label: '나눔고딕', note: '익숙하고 친근한 고딕', stack: `'Nanum Gothic', ${FALLBACK}`, load: () => Promise.all([import('@fontsource/nanum-gothic/400.css'), import('@fontsource/nanum-gothic/700.css')]), hasBold: true },
	{ id: 'gowun-dodum', label: '고운돋움', note: '둥글고 부드러운 고딕', stack: `'Gowun Dodum', ${FALLBACK}`, load: () => import('@fontsource/gowun-dodum/400.css'), hasBold: false },
	{ id: 'black-han-sans', label: '검은고딕', note: '굵고 힘 있는 제목용', stack: `'Black Han Sans', ${FALLBACK}`, load: () => import('@fontsource/black-han-sans/400.css'), hasBold: false },
	{ id: 'do-hyeon', label: '도현', note: '또렷한 제목용', stack: `'Do Hyeon', ${FALLBACK}`, load: () => import('@fontsource/do-hyeon/400.css'), hasBold: false },
	{ id: 'jua', label: '주아', note: '귀엽고 둥근 글씨 (어린이 부서)', stack: `'Jua', ${FALLBACK}`, load: () => import('@fontsource/jua/400.css'), hasBold: false },
	{ id: 'system', label: '기기 기본 글꼴', note: '맑은 고딕 · 애플 SD 산돌고딕 (파일을 받지 않음)', stack: FALLBACK, hasBold: true }
];

export const defaultFonts: FontSettings = { title: 'noto-sans', menu: 'noto-sans', body: 'noto-sans' };

export const roleLabels: Record<FontRole, { label: string; where: string }> = {
	title: { label: '타이틀', where: '왼쪽 위 교회 이름, 각 화면의 제목' },
	menu: { label: '메뉴', where: '왼쪽 메뉴 (모바일은 아래 탭)' },
	body: { label: '내용', where: '표, 목록, 버튼, 입력 칸 등 나머지 전부' }
};

export function fontDef(id: FontId): FontDef {
	return fonts.find((f) => f.id === id) ?? fonts[0];
}

const loaded = new Set<FontId>();

/** 글꼴 파일을 한 번만 불러온다. */
export async function ensureLoaded(id: FontId): Promise<void> {
	if (loaded.has(id)) return;
	loaded.add(id);
	try {
		await fontDef(id).load?.();
	} catch {
		loaded.delete(id); // 다음에 다시 시도
	}
}

/** 세 곳의 글꼴을 화면 전체에 적용한다. */
export function applyFonts(settings: FontSettings): void {
	const root = document.documentElement;
	for (const role of ['title', 'menu', 'body'] as FontRole[]) {
		root.style.setProperty(`--font-${role}`, fontDef(settings[role]).stack);
		void ensureLoaded(settings[role]);
	}
}
