import type { Terms, Tone } from './types';
import { url } from './url';

export interface NavItem {
	key: string;
	label: string;
	icon: string;
	tone: Tone;
	href: string; // /d/:dept 뒤에 붙는 경로
	badge?: number;
	/** 모바일 하단 탭에 바로 보이는 항목 */
	tab?: boolean;
}

/** 사이드바 메뉴. 이름은 부서의 용어 설정을 따른다. */
export function buildNav(terms: Terms, pendingVisits: number): { main: NavItem[]; plan: NavItem[]; settings: NavItem } {
	return {
		main: [
			{ key: 'home', label: '대시보드', icon: 'home', tone: 'blue', href: '', tab: true },
			{ key: 'students', label: `${terms.student} 명단`, icon: 'users', tone: 'green', href: '/students', tab: true },
			{ key: 'teachers', label: `${terms.teacher} 명단`, icon: 'user', tone: 'violet', href: '/teachers' },
			{ key: 'attendance', label: '출석 체크', icon: 'check', tone: 'sky', href: '/attendance', tab: true },
			{ key: 'visits', label: terms.visit, icon: 'heart', tone: 'pink', href: '/visits', badge: pendingVisits || undefined, tab: true }
		],
		plan: [
			{ key: 'calendar', label: '연간 계획', icon: 'calendar', tone: 'orange', href: '/plans/calendar' },
			{ key: 'ministry', label: '사역 계획', icon: 'flag', tone: 'teal', href: '/plans/ministry' },
			{ key: 'notices', label: '공지사항', icon: 'megaphone', tone: 'amber', href: '/notices' },
			{ key: 'meetings', label: '회의록', icon: 'file', tone: 'slate', href: '/meetings' }
		],
		settings: { key: 'settings', label: '설정', icon: 'sliders', tone: 'slate', href: '/settings' }
	};
}

/** 현재 주소가 이 메뉴에 해당하는지. 대시보드는 정확히 일치할 때만. */
export function isActive(pathname: string, deptId: number, item: NavItem): boolean {
	const root = url(`/d/${deptId}`);
	const target = root + item.href;
	if (item.href === '') return pathname === root || pathname === root + '/';
	return pathname === target || pathname.startsWith(target + '/');
}
