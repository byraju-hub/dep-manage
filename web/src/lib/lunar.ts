// 음력 ↔ 양력 변환. 브라우저에 들어 있는 달력 기능(Intl, 중국 음양력)을 쓰기 때문에 따로 표를 두지 않는다.
// 한국 음력과 계산 방식이 같고, 아주 드물게(자정 부근 합삭이 걸리는 해) 하루 다를 수 있다.

export interface Lunar {
	y: number;
	m: number;
	d: number;
	leap: boolean;
}

const fmt = new Intl.DateTimeFormat('en-u-ca-chinese', { timeZone: 'UTC', year: 'numeric', month: 'numeric', day: 'numeric' });

const pad = (n: number) => String(n).padStart(2, '0');
const toIso = (ms: number) => new Date(ms).toISOString().slice(0, 10);
const DAY = 86400000;

/** '2012-05-03' → 음력 2012년 4월 13일. 날짜를 읽을 수 없으면 null. */
export function solarToLunar(iso: string): Lunar | null {
	const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
	if (!m) return null;
	const ms = Date.UTC(+m[1], +m[2] - 1, +m[3]);
	if (Number.isNaN(ms)) return null;
	const parts = fmt.formatToParts(new Date(ms));
	const get = (t: string) => parts.find((p) => p.type === t)?.value ?? '';
	const month = get('month');
	return { y: Number(get('relatedYear')), m: parseInt(month, 10), d: Number(get('day')), leap: month.includes('bis') };
}

const cache = new Map<number, Map<string, string>>();

/** 음력 한 해의 모든 날짜 → 양력. (음력 해는 양력 1월 초부터 다음 해 2월 말 사이에 걸친다.) */
function yearTable(y: number): Map<string, string> {
	let t = cache.get(y);
	if (t) return t;
	t = new Map();
	const start = Date.UTC(y, 0, 1);
	for (let i = 0; i < 430; i++) {
		const iso = toIso(start + i * DAY);
		const l = solarToLunar(iso);
		if (l && l.y === y) t.set(`${l.leap ? 'L' : ''}${l.m}-${l.d}`, iso);
	}
	cache.set(y, t);
	return t;
}

/** 음력 날짜 → 양력 'YYYY-MM-DD'. 그 해에 없는 날(윤달이 없는 해, 29일까지인 달의 30일)이면 null. */
export function lunarToSolar(y: number, m: number, d: number, leap = false): string | null {
	return yearTable(y).get(`${leap ? 'L' : ''}${m}-${d}`) ?? null;
}

/** 해마다 돌아오는 음력 생일이 그 해 양력으로 언제인지. 윤달 생일인데 그 해에 윤달이 없으면 평달로 센다. */
export function lunarBirthdayIn(year: number, m: number, d: number, leap: boolean): string | null {
	return lunarToSolar(year, m, d, leap) ?? (leap ? lunarToSolar(year, m, d, false) : null) ?? (d === 30 ? lunarToSolar(year, m, 29, leap) : null);
}

/** 저장 형식: 'YYYY-MM-DD'(음력 생년월일), 윤달이면 끝에 'L'. */
export function parseLunarValue(v: string): Lunar | null {
	const m = v.trim().match(/^(\d{4})-(\d{2})-(\d{2})(L?)$/);
	if (!m) return null;
	return { y: +m[1], m: +m[2], d: +m[3], leap: m[4] === 'L' };
}

export const formatLunarValue = (l: Lunar) => `${l.y}-${pad(l.m)}-${pad(l.d)}${l.leap ? 'L' : ''}`;

/** 화면에 보일 글: 음력 4월 13일 · 윤달 */
export const lunarText = (l: Lunar, withYear = false) => `음력 ${withYear ? l.y + '년 ' : ''}${l.leap ? '윤' : ''}${l.m}월 ${l.d}일`;

/** 엑셀 칸에 적는 글: 2012-04-13 (윤달은 2012-04-13 윤) */
export const lunarCell = (v: string) => {
	const l = parseLunarValue(v);
	return l ? `${l.y}-${pad(l.m)}-${pad(l.d)}${l.leap ? ' 윤' : ''}` : v;
};

/** 엑셀 칸의 글 → 저장 형식. 읽을 수 없으면 null. */
export function parseLunarCell(s: string): string | null {
	const m = s.trim().match(/^(\d{4})[-./\s](\d{1,2})[-./\s](\d{1,2})\.?\s*(윤|L|leap)?$/i);
	if (!m) return null;
	const [y, mo, d] = [+m[1], +m[2], +m[3]];
	if (mo < 1 || mo > 12 || d < 1 || d > 30) return null;
	return formatLunarValue({ y, m: mo, d, leap: !!m[4] });
}
