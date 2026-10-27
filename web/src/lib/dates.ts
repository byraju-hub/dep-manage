// 날짜 도우미. 날짜는 모두 'YYYY-MM-DD' 문자열로 주고받고, 시간대 문제를 피하려고 로컬 날짜로만 계산한다.

const pad = (n: number) => String(n).padStart(2, '0');

export function toISO(d: Date): string {
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function fromISO(s: string): Date {
	const [y, m, d] = s.split('-').map(Number);
	return new Date(y, m - 1, d);
}

export function today(): string {
	return toISO(new Date());
}

export function addDays(iso: string, n: number): string {
	const d = fromISO(iso);
	d.setDate(d.getDate() + n);
	return toISO(d);
}

/** 그 날짜가 속한 주의 일요일(일요일이면 그대로). */
export function sundayOf(iso: string): string {
	const d = fromISO(iso);
	return addDays(iso, -d.getDay());
}

export function weekday(iso: string): number {
	return fromISO(iso).getDay();
}

const WEEKDAYS = ['일', '월', '화', '수', '목', '금', '토'];
export const weekdayLabels = WEEKDAYS;

/** 10/25 */
export function shortMD(iso: string): string {
	const d = fromISO(iso);
	return `${d.getMonth() + 1}/${d.getDate()}`;
}

/** 10월 25일 주일 */
export function longMD(iso: string): string {
	const d = fromISO(iso);
	const wd = d.getDay() === 0 ? '주일' : `${WEEKDAYS[d.getDay()]}요일`;
	return `${d.getMonth() + 1}월 ${d.getDate()}일 ${wd}`;
}

/** 2026년 10월 25일 주일 */
export function fullDate(iso: string): string {
	return `${fromISO(iso).getFullYear()}년 ${longMD(iso)}`;
}

/** 2026. 10. 25 */
export function dotted(iso: string): string {
	const d = fromISO(iso);
	return `${d.getFullYear()}. ${d.getMonth() + 1}. ${d.getDate()}`;
}

export function monthKey(iso: string): string {
	return iso.slice(0, 7);
}

/** 생일이 기준일부터 며칠 뒤인지(올해 이미 지났으면 내년). 오늘이면 0. */
export function daysUntilBirthday(birthISO: string, fromDate: string): number {
	const base = fromISO(fromDate);
	const b = fromISO(birthISO);
	let next = new Date(base.getFullYear(), b.getMonth(), b.getDate());
	if (next < base) next = new Date(base.getFullYear() + 1, b.getMonth(), b.getDate());
	return Math.round((next.getTime() - base.getTime()) / 86400000);
}

export function age(birthISO: string, onDate: string = today()): number {
	const b = fromISO(birthISO);
	const t = fromISO(onDate);
	let a = t.getFullYear() - b.getFullYear();
	if (t.getMonth() < b.getMonth() || (t.getMonth() === b.getMonth() && t.getDate() < b.getDate())) a--;
	return a;
}
