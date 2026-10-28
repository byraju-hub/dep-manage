// 엑셀 양식 만들기, 명단 내려받기, 채워서 올린 파일 읽기.
// 엑셀 처리 라이브러리는 용량이 커서 이 기능을 쓸 때만 불러온다. 파일은 브라우저 안에서만 읽고 만들며,
// 읽은 내용은 줄 단위로 서버(API)에 보내서 서버가 다시 검사한다.
import { lunarCell, parseLunarCell } from './lunar';
import type { ClassRoom, CustomField, CustomValues, Department, StudentImportRow, TeacherImportRow, Terms } from './types';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Any = any;

async function lib(): Promise<Any> {
	const m: Any = await import('exceljs');
	return m.default ?? m;
}

export function downloadBlob(blob: Blob, filename: string) {
	const a = document.createElement('a');
	a.href = URL.createObjectURL(blob);
	a.download = filename;
	document.body.appendChild(a);
	a.click();
	a.remove();
	setTimeout(() => URL.revokeObjectURL(a.href), 10_000);
}

const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

async function saveWorkbook(wb: Any, filename: string) {
	const buf: ArrayBuffer = await wb.xlsx.writeBuffer();
	downloadBlob(new Blob([buf], { type: XLSX_MIME }), filename);
}

const today = () => new Date().toISOString().slice(0, 10);
const safe = (s: string) => s.replace(/[\\/:*?"<>|]/g, '_');

// ===== 열 정의 =====

interface Col {
	key: string;
	header: string;
	width: number;
	required?: boolean;
	aliases: string[];
}

/** 추가 항목을 엑셀 열로. 기본 열과 이름이 겹치면 제목 뒤에 (추가)를 붙인다. */
function customColumns(fields: CustomField[], base: Col[]): Col[] {
	const used = new Set(base.flatMap((c) => c.aliases.map(norm)));
	return fields.map((f) => {
		const header = used.has(norm(f.label)) ? `${f.label} (추가)` : f.label;
		return { key: 'c' + f.id, header, width: f.type === 'longtext' ? 28 : 16, aliases: [header, f.label] };
	});
}

const BOOL_YES = ['예', 'y', 'yes', 'true', 'o', '○', '1', 'v', '✓'];
const BOOL_NO = ['아니오', '아니요', 'n', 'no', 'false', 'x', '0', '×'];

/** 추가 항목 값 → 엑셀 칸에 적을 글 */
function customToCell(f: CustomField, values: CustomValues | undefined): string {
	const v = values?.[f.id];
	if (v === undefined || v === null || v === '') return '';
	if (f.type === 'bool') return v === true ? '예' : '아니오';
	if (f.type === 'lunar') return lunarCell(String(v));
	return String(v);
}

function customCells(fields: CustomField[], values: CustomValues | undefined): Record<string, string> {
	return Object.fromEntries(fields.map((f) => ['c' + f.id, customToCell(f, values)]));
}

/** 추가 항목 열의 선택 목록 */
function customLists(fields: CustomField[]): Record<string, string[]> {
	const out: Record<string, string[]> = {};
	for (const f of fields) {
		if (f.type === 'bool') out['c' + f.id] = ['예', '아니오'];
		else if (f.type === 'select') out['c' + f.id] = f.options;
	}
	return out;
}

const customTextKeys = (fields: CustomField[]) => fields.filter((f) => f.type === 'date' || f.type === 'lunar').map((f) => 'c' + f.id);

/** 양식의 '작성 안내' 시트에 넣을 추가 항목 설명 */
function customGuide(fields: CustomField[]): string[] {
	if (!fields.length) return [];
	const out = ['', '추가 항목 (이 부서에서 만든 칸이에요)'];
	for (const f of fields) {
		const how =
			f.type === 'bool' ? '예 / 아니오 중에서 골라요'
			: f.type === 'select' ? f.options.join(', ') + ' 중에서 골라요'
			: f.type === 'date' ? '2010-05-03 처럼 적어요'
			: f.type === 'lunar' ? '음력 날짜를 2012-04-13 처럼 적고, 윤달이면 뒤에 "윤"을 붙여요'
			: f.type === 'number' ? '숫자로 적어요'
			: '자유롭게 적어요';
		out.push(`- ${f.label}: ${how}`);
	}
	return out;
}

/** 엑셀 칸의 글 → 추가 항목 값들. 문제가 있으면 message 를 돌려준다. */
function parseCustom(fields: CustomField[], v: Record<string, string>): { custom: CustomValues; message: string } {
	const custom: CustomValues = {};
	for (const f of fields) {
		const raw = (v['c' + f.id] ?? '').trim();
		if (!raw) continue;
		const bad = (hint: string) => ({ custom, message: `${f.label} "${raw}"을(를) 읽을 수 없어요. ${hint}` });
		if (f.type === 'bool') {
			const n = norm(raw);
			if (BOOL_YES.includes(n)) custom[f.id] = true;
			else if (BOOL_NO.includes(n)) custom[f.id] = false;
			else return bad('(예, 아니오)');
		} else if (f.type === 'select') {
			const hit = f.options.find((o) => norm(o) === norm(raw));
			if (!hit) return bad(`(${f.options.join(', ')} 중에서)`);
			custom[f.id] = hit;
		} else if (f.type === 'date') {
			const d = parseDate(raw);
			if (!d) return bad('2010-05-03 처럼 적어 주세요.');
			custom[f.id] = d;
		} else if (f.type === 'lunar') {
			const l = parseLunarCell(raw);
			if (!l) return bad('음력 날짜를 2012-04-13 처럼 적고, 윤달이면 뒤에 "윤"을 붙여 주세요.');
			custom[f.id] = l;
		} else if (f.type === 'number') {
			const n = Number(raw.replace(/,/g, ''));
			if (!Number.isFinite(n)) return bad('숫자로 적어 주세요.');
			custom[f.id] = n;
		} else custom[f.id] = raw;
	}
	return { custom, message: '' };
}

function studentColumns(t: Terms, fields: CustomField[] = []): Col[] {
	const base = studentColumnsBase(t);
	return [...base, ...customColumns(fields, base)];
}

function studentColumnsBase(t: Terms): Col[] {
	return [
		{ key: 'name', header: '이름', width: 12, required: true, aliases: ['이름', '성명'] },
		{ key: 'gender', header: '성별', width: 8, aliases: ['성별'] },
		{ key: 'birthDate', header: '생년월일', width: 13, aliases: ['생년월일', '생일'] },
		{ key: 'phone', header: '연락처', width: 15, aliases: ['연락처', '전화', '전화번호', '휴대폰'] },
		{ key: 'school', header: '학교', width: 12, aliases: ['학교'] },
		{ key: 'grade', header: t.grade, width: 11, required: true, aliases: [t.grade, '학년', '기수', '구분'] },
		{ key: 'class', header: t.class, width: 12, aliases: [t.class, '반', '소그룹', '목장'] },
		{ key: 'status', header: '상태', width: 10, aliases: ['상태'] },
		{ key: 'baptism', header: '세례', width: 8, aliases: ['세례'] },
		{ key: 'mentor', header: '인도자', width: 10, aliases: ['인도자'] },
		{ key: 'address', header: '주소', width: 28, aliases: ['주소'] },
		{ key: 'g1rel', header: '보호자1 관계', width: 12, aliases: ['보호자1관계'] },
		{ key: 'g1name', header: '보호자1 이름', width: 12, aliases: ['보호자1이름'] },
		{ key: 'g1phone', header: '보호자1 연락처', width: 15, aliases: ['보호자1연락처'] },
		{ key: 'g2rel', header: '보호자2 관계', width: 12, aliases: ['보호자2관계'] },
		{ key: 'g2name', header: '보호자2 이름', width: 12, aliases: ['보호자2이름'] },
		{ key: 'g2phone', header: '보호자2 연락처', width: 15, aliases: ['보호자2연락처'] },
		{ key: 'notes', header: '비고', width: 24, aliases: ['비고', '메모'] }
	];
}

function teacherColumns(t: Terms, fields: CustomField[] = []): Col[] {
	const base = teacherColumnsBase(t);
	return [...base, ...customColumns(fields, base)];
}

function teacherColumnsBase(t: Terms): Col[] {
	return [
		{ key: 'name', header: '이름', width: 12, required: true, aliases: ['이름', '성명'] },
		{ key: 'position', header: '직분', width: 10, aliases: ['직분'] },
		{ key: 'officerRole', header: '임원', width: 10, aliases: ['임원'] },
		{ key: 'birthDate', header: '생년월일', width: 13, aliases: ['생년월일', '생일'] },
		{ key: 'phone', header: '연락처', width: 15, aliases: ['연락처', '전화', '전화번호', '휴대폰'] },
		{ key: 'status', header: '상태', width: 10, aliases: ['상태'] },
		{ key: 'classes', header: `담당 ${t.class}`, width: 18, aliases: [`담당${t.class}`, '담당반', '담당'] },
		{ key: 'notes', header: '비고', width: 24, aliases: ['비고', '메모'] }
	];
}

const norm = (s: unknown) => String(s ?? '').replace(/[\s*]/g, '').toLowerCase();

// ===== 통합문서 만들기 =====

const HEAD_FILL = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE3ECFF' } };
const MAX_ROWS = 500;

interface BuildOpts {
	sheetName: string;
	title: string;
	cols: Col[];
	rows: Array<Record<string, string>>;
	examples: Array<Record<string, string>>;
	/** 열 key → 선택 목록(드롭다운)에 쓸 값들 */
	lists: Record<string, string[]>;
	guide: string[];
	/** 문자로 두어야 하는 열(앞자리 0이 사라지지 않게) */
	textKeys: string[];
}

async function buildWorkbook(o: BuildOpts) {
	const ExcelJS = await lib();
	const wb = new ExcelJS.Workbook();
	wb.creator = 'dep-manage';
	const ws = wb.addWorksheet(o.sheetName, { views: [{ state: 'frozen', ySplit: 1 }] });
	ws.columns = o.cols.map((c) => ({ header: c.header + (c.required ? ' *' : ''), key: c.key, width: c.width }));
	const head = ws.getRow(1);
	head.height = 22;
	head.eachCell((cell: Any) => {
		cell.font = { bold: true, color: { argb: 'FF1D4ED8' } };
		cell.fill = HEAD_FILL;
		cell.alignment = { vertical: 'middle' };
	});

	for (const key of o.textKeys) ws.getColumn(key).numFmt = '@';

	for (const ex of o.examples) {
		const r = ws.addRow(ex);
		r.eachCell((cell: Any) => (cell.font = { color: { argb: 'FF98A2B3' }, italic: true }));
	}
	for (const row of o.rows) ws.addRow(row);

	// 선택 목록: 값은 숨긴 시트에 두고, 칸에서는 그 범위를 가리킨다. (목록이 길어도 되고, 쉼표가 들어 있어도 된다.)
	const listSheet = wb.addWorksheet('목록', { state: 'hidden' });
	let col = 1;
	for (const c of o.cols) {
		const values = o.lists[c.key];
		if (!values?.length) continue;
		values.forEach((v, i) => (listSheet.getCell(i + 1, col).value = v));
		const letter = listSheet.getColumn(col).letter;
		const ci = o.cols.findIndex((x) => x.key === c.key) + 1;
		const last = Math.max(o.rows.length + o.examples.length + 1, MAX_ROWS + 1);
		for (let r = 2; r <= last; r++) {
			ws.getCell(r, ci).dataValidation = {
				type: 'list',
				allowBlank: true,
				formulae: [`'목록'!$${letter}$1:$${letter}$${values.length}`],
				showErrorMessage: false
			};
		}
		col++;
	}

	const guide = wb.addWorksheet('작성 안내');
	guide.getColumn(1).width = 100;
	guide.getCell(1, 1).value = o.title;
	guide.getCell(1, 1).font = { bold: true, size: 14 };
	o.guide.forEach((line, i) => (guide.getCell(i + 3, 1).value = line));
	return wb;
}

function guideCommon(): string[] {
	return [
		'1. 맨 위 제목 줄의 이름은 바꾸지 마세요. 맨 위 줄 아래부터 한 줄에 한 사람씩 적어요.',
		'2. 제목에 * 가 붙은 칸은 꼭 적어야 해요.',
		'3. 회색 기울임 글씨로 적힌 "예)" 줄은 예시예요. 지우거나 그대로 둬도 올리지 않아요.',
		'4. 칸을 눌렀을 때 화살표(▼)가 보이면 목록에서 고를 수 있어요. 목록에 없는 말은 올릴 때 알려 드려요.',
		'5. 생년월일은 2010-05-03 처럼 적어요. (2010.5.3, 20100503 도 읽어요.)',
		'6. 연락처는 010-1234-5678 처럼 적거나 숫자만 적어도 돼요.',
		`7. 한 번에 ${MAX_ROWS}명까지 올릴 수 있어요. 이미 있는 사람(이름과 생년월일이 같은 사람)은 건너뛰어요.`
	];
}

// ===== 학생 =====

const STUDENT_STATUS = (t: Terms) => ['재적', t.newMember, '비활동'];
const BAPTISM = ['없음', '유아', '입교', '학습', '세례'];

function studentLists(dept: Department, classes: Array<Pick<ClassRoom, 'name'>>) {
	return {
		gender: ['남', '여'],
		grade: dept.gradeLevels.map((g) => g.label),
		class: classes.map((c) => c.name),
		status: STUDENT_STATUS(dept.terms),
		baptism: BAPTISM
	};
}

const statusLabel = (s: string, t: Terms) => (s === 'new' ? t.newMember : s === 'inactive' ? '비활동' : '재적');

export interface StudentSheetRow {
	name: string;
	gender: string;
	birthDate: string;
	phone: string;
	school: string;
	gradeLabel: string;
	className: string;
	status: string;
	baptism: string;
	mentor: string;
	address: string;
	guardians: Array<{ relation: string; name: string; phone: string }>;
	notes: string;
	custom?: CustomValues;
}

function studentRowToCells(r: StudentSheetRow, t: Terms): Record<string, string> {
	const g = (i: number) => r.guardians[i] ?? { relation: '', name: '', phone: '' };
	return {
		name: r.name, gender: r.gender, birthDate: r.birthDate, phone: r.phone, school: r.school, grade: r.gradeLabel, class: r.className,
		status: statusLabel(r.status, t), baptism: r.baptism === '-' ? '없음' : r.baptism, mentor: r.mentor, address: r.address,
		g1rel: g(0).relation, g1name: g(0).name, g1phone: g(0).phone, g2rel: g(1).relation, g2name: g(1).name, g2phone: g(1).phone, notes: r.notes
	};
}

export async function downloadStudentTemplate(dept: Department, classes: Array<Pick<ClassRoom, 'name'>>, fields: CustomField[] = []) {
	const t = dept.terms;
	const firstGrade = dept.gradeLevels.find((g) => !g.isGraduate)?.label ?? '';
	const wb = await buildWorkbook({
		sheetName: t.student,
		title: `${dept.name} ${t.student} 명단 양식`,
		cols: studentColumns(t, fields),
		rows: [],
		examples: [
			studentRowToCells({ name: '예) 김하준', gender: '남', birthDate: '2012-05-03', phone: '010-1234-5678', school: '은혜중', gradeLabel: firstGrade, className: classes[0]?.name ?? '', status: 'active', baptism: '-', mentor: '', address: '', guardians: [{ relation: '모', name: '이미선', phone: '010-2222-3333' }], notes: '' }, t)
		],
		lists: { ...studentLists(dept, classes), ...customLists(fields) },
		guide: [
			...guideCommon(),
			`8. ${t.grade}은(는) 이 부서에서 쓰는 이름 그대로 적어요: ${dept.gradeLevels.map((g) => g.label).join(', ')}`,
			`9. ${t.class}은(는) 이미 만들어 둔 이름만 쓸 수 있어요${classes.length ? `: ${classes.map((c) => c.name).join(', ')}` : ' (아직 없어요. 비워 두세요.)'}`,
			`10. 상태는 재적, ${t.newMember}, 비활동 중에서 골라요. 비우면 재적이에요.`,
			'11. 보호자는 두 명까지 적을 수 있어요. (관계 예: 부, 모, 조부모)',
			...customGuide(fields)
		],
		textKeys: ['phone', 'g1phone', 'g2phone', 'birthDate', ...customTextKeys(fields)]
	});
	await saveWorkbook(wb, safe(`${dept.name}_${t.student}_양식.xlsx`));
}

export async function downloadStudentList(dept: Department, classes: Array<Pick<ClassRoom, 'name'>>, rows: StudentSheetRow[], fields: CustomField[] = []) {
	const t = dept.terms;
	const wb = await buildWorkbook({
		sheetName: t.student,
		title: `${dept.name} ${t.student} 명단 (${today()})`,
		cols: studentColumns(t, fields),
		rows: rows.map((r) => ({ ...studentRowToCells(r, t), ...customCells(fields, r.custom) })),
		examples: [],
		lists: { ...studentLists(dept, classes), ...customLists(fields) },
		guide: ['이 파일은 현재 명단을 내려받은 것이에요.', '고친 뒤 같은 양식으로 다시 올릴 수도 있어요. (이미 있는 사람은 건너뛰고, 새로 적은 사람만 추가돼요.)', ...guideCommon().slice(0, 0)],
		textKeys: ['phone', 'g1phone', 'g2phone', 'birthDate', ...customTextKeys(fields)]
	});
	await saveWorkbook(wb, safe(`${dept.name}_${t.student}_명단_${today()}.xlsx`));
}

// ===== 교사 =====

export interface TeacherSheetRow {
	name: string;
	position: string;
	officerRole: string;
	birthDate: string;
	phone: string;
	status: string;
	classNames: string[];
	notes: string;
	custom?: CustomValues;
}

const teacherRowToCells = (r: TeacherSheetRow): Record<string, string> => ({
	name: r.name, position: r.position, officerRole: r.officerRole, birthDate: r.birthDate, phone: r.phone,
	status: r.status === 'inactive' ? '쉬는 중' : '활동', classes: r.classNames.join(', '), notes: r.notes
});

export async function downloadTeacherTemplate(dept: Department, classes: Array<Pick<ClassRoom, 'name'>>, fields: CustomField[] = []) {
	const t = dept.terms;
	const wb = await buildWorkbook({
		sheetName: t.teacher,
		title: `${dept.name} ${t.teacher} 명단 양식`,
		cols: teacherColumns(t, fields),
		rows: [],
		examples: [teacherRowToCells({ name: '예) 박은지', position: '집사', officerRole: t.head, birthDate: '1985-03-12', phone: '010-5555-6666', status: 'active', classNames: classes[0] ? [classes[0].name] : [], notes: '' })],
		lists: { status: ['활동', '쉬는 중'], ...customLists(fields) },
		guide: [
			...guideCommon(),
			`8. 담당 ${t.class}은(는) 이미 만들어 둔 이름만 쓸 수 있고, 여러 개면 쉼표로 나눠요${classes.length ? `: ${classes.map((c) => c.name).join(', ')}` : ' (아직 없어요. 비워 두세요.)'}`,
			'9. 상태는 활동, 쉬는 중 중에서 골라요. 비우면 활동이에요.',
			...customGuide(fields)
		],
		textKeys: ['phone', 'birthDate', ...customTextKeys(fields)]
	});
	await saveWorkbook(wb, safe(`${dept.name}_${t.teacher}_양식.xlsx`));
}

export async function downloadTeacherList(dept: Department, rows: TeacherSheetRow[], fields: CustomField[] = []) {
	const t = dept.terms;
	const wb = await buildWorkbook({
		sheetName: t.teacher,
		title: `${dept.name} ${t.teacher} 명단 (${today()})`,
		cols: teacherColumns(t, fields),
		rows: rows.map((r) => ({ ...teacherRowToCells(r), ...customCells(fields, r.custom) })),
		examples: [],
		lists: { status: ['활동', '쉬는 중'], ...customLists(fields) },
		guide: ['이 파일은 현재 명단을 내려받은 것이에요.'],
		textKeys: ['phone', 'birthDate', ...customTextKeys(fields)]
	});
	await saveWorkbook(wb, safe(`${dept.name}_${t.teacher}_명단_${today()}.xlsx`));
}

// ===== 출석률 =====

export async function downloadStats(title: string, headers: string[], rows: Array<Array<string | number | null>>, filename: string) {
	const ExcelJS = await lib();
	const wb = new ExcelJS.Workbook();
	wb.creator = 'dep-manage';
	const ws = wb.addWorksheet('출석률');
	ws.addRow([title]).font = { bold: true, size: 13 };
	ws.addRow([]);
	const h = ws.addRow(headers);
	h.eachCell((c: Any) => {
		c.font = { bold: true, color: { argb: 'FF1D4ED8' } };
		c.fill = HEAD_FILL;
	});
	rows.forEach((r) => ws.addRow(r));
	ws.columns.forEach((c: Any, i: number) => (c.width = i === 0 ? 14 : 12));
	await saveWorkbook(wb, safe(filename));
}

// ===== 올린 파일 읽기 =====

export interface ParseProblem {
	row: number;
	name: string;
	message: string;
}

/** 칸의 값을 글자로. (날짜는 UTC 기준으로 YYYY-MM-DD) */
function cellText(cell: Any): string {
	const v = cell?.value;
	if (v === null || v === undefined) return '';
	if (v instanceof Date) return v.toISOString().slice(0, 10);
	if (typeof v === 'object') {
		if ('richText' in v) return (v.richText as Any[]).map((x) => x.text).join('').trim();
		if ('result' in v) return v.result instanceof Date ? v.result.toISOString().slice(0, 10) : String(v.result ?? '').trim();
		if ('text' in v) return String(v.text ?? '').trim();
		return '';
	}
	return String(v).trim();
}

/** 2010-05-03, 2010.5.3, 2010/5/3, 20100503 → '2010-05-03'. 비어 있으면 '', 읽을 수 없으면 null. */
function parseDate(s: string): string | null {
	if (!s) return '';
	// 엑셀이 날짜를 일련번호(예: 41032 = 2012-05-03)로 주는 경우. 1900년 윤년 오류를 반영해 1899-12-30 이 0일이다.
	if (/^\d{5}$/.test(s)) {
		const n = Number(s);
		if (n < 1 || n > 80000) return null;
		return new Date(Date.UTC(1899, 11, 30) + n * 86400000).toISOString().slice(0, 10);
	}
	let m = s.match(/^(\d{4})[-./\s](\d{1,2})[-./\s](\d{1,2})\.?$/) ?? s.match(/^(\d{4})(\d{2})(\d{2})$/);
	if (!m) m = s.match(/^(\d{4})-(\d{2})-(\d{2})T/);
	if (!m) return null;
	const [y, mo, d] = [Number(m[1]), Number(m[2]), Number(m[3])];
	const dt = new Date(Date.UTC(y, mo - 1, d));
	if (dt.getUTCFullYear() !== y || dt.getUTCMonth() !== mo - 1 || dt.getUTCDate() !== d) return null;
	return `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

/** 엑셀이 숫자로 바꿔 앞의 0이 사라진 휴대폰 번호(1012345678)를 되돌린다. */
function fixPhone(s: string): string {
	return /^1\d{9}$/.test(s) ? '0' + s : s;
}

async function readSheet(file: File, sheetName: string, cols: Col[]) {
	const ExcelJS = await lib();
	const wb = new ExcelJS.Workbook();
	await wb.xlsx.load(await file.arrayBuffer());
	const ws = wb.getWorksheet(sheetName) ?? wb.worksheets.find((s: Any) => s.state !== 'hidden' && s.name !== '작성 안내') ?? wb.worksheets[0];
	if (!ws) throw new Error('엑셀 파일에서 시트를 찾지 못했어요.');

	// 맨 위 줄의 제목으로 어느 칸이 무엇인지 알아낸다. (칸 순서가 바뀌어도, 비슷한 제목이어도 읽는다.)
	const index = new Map<string, number>();
	ws.getRow(1).eachCell((cell: Any, colNumber: number) => {
		const h = norm(cellText(cell));
		const col = cols.find((c) => c.aliases.some((a) => norm(a) === h));
		if (col && !index.has(col.key)) index.set(col.key, colNumber);
	});
	const missing = cols.filter((c) => c.required && !index.has(c.key)).map((c) => c.header);
	if (missing.length) throw new Error(`맨 위 줄에서 "${missing.join('", "')}" 칸을 찾지 못했어요. 내려받은 양식의 제목을 그대로 두었는지 확인해 주세요.`);

	const rows: Array<{ row: number; v: Record<string, string> }> = [];
	for (let r = 2; r <= ws.rowCount; r++) {
		const row = ws.getRow(r);
		const v: Record<string, string> = {};
		let any = false;
		for (const c of cols) {
			const n = index.get(c.key);
			v[c.key] = n ? cellText(row.getCell(n)) : '';
			if (v[c.key]) any = true;
		}
		if (!any) continue; // 빈 줄
		if (v.name.startsWith('예)')) continue; // 예시 줄
		rows.push({ row: r, v });
	}
	return rows;
}

const find = <T extends { name: string }>(list: T[], name: string) => list.find((x) => norm(x.name) === norm(name));

export async function readStudentFile(file: File, dept: Department, classes: Array<{ id: number; name: string }>, fields: CustomField[] = []) {
	const t = dept.terms;
	const cols = studentColumns(t, fields);
	const raw = await readSheet(file, t.student, cols);
	const out: StudentImportRow[] = [];
	const problems: ParseProblem[] = [];

	for (const { row, v } of raw) {
		const fail = (message: string) => problems.push({ row, name: v.name, message });
		if (!v.name) {
			fail('이름이 비어 있어요.');
			continue;
		}
		const grade = dept.gradeLevels.find((g) => norm(g.label) === norm(v.grade));
		if (!grade) {
			fail(`${t.grade} "${v.grade || '(비어 있음)'}"을(를) 찾을 수 없어요. (${dept.gradeLevels.map((g) => g.label).join(', ')} 중에서 적어 주세요.)`);
			continue;
		}
		let classId: number | null = null;
		if (v.class) {
			const c = find(classes, v.class);
			if (!c) {
				fail(`${t.class} "${v.class}"을(를) 찾을 수 없어요. ${classes.length ? `(${classes.map((x) => x.name).join(', ')})` : '(아직 만든 ' + t.class + '이(가) 없어요. 비워 주세요.)'}`);
				continue;
			}
			classId = c.id;
		}
		const birth = parseDate(v.birthDate);
		if (birth === null) {
			fail(`생년월일 "${v.birthDate}"을(를) 읽을 수 없어요. 2010-05-03 처럼 적어 주세요.`);
			continue;
		}
		const sv = norm(v.status);
		let status: 'active' | 'new' | 'inactive' = 'active';
		if (sv) {
			if (['재적', 'active'].includes(sv)) status = 'active';
			else if ([norm(t.newMember), '새가족', '새친구', 'new'].includes(sv)) status = 'new';
			else if (['비활동', 'inactive'].includes(sv)) status = 'inactive';
			else {
				fail(`상태 "${v.status}"을(를) 알 수 없어요. (재적, ${t.newMember}, 비활동)`);
				continue;
			}
		}
		const bv = norm(v.baptism);
		let baptism = '-';
		if (bv && bv !== '없음' && bv !== '-') {
			const hit = BAPTISM.find((b) => norm(b) === bv);
			if (!hit) {
				fail(`세례 "${v.baptism}"을(를) 알 수 없어요. (${BAPTISM.join(', ')})`);
				continue;
			}
			baptism = hit;
		}
		const gv = norm(v.gender);
		const gender = ['남', '남자', 'm', 'male'].includes(gv) ? '남' : ['여', '여자', 'f', 'female'].includes(gv) ? '여' : '';
		if (gv && !gender) {
			fail(`성별 "${v.gender}"을(를) 알 수 없어요. (남, 여)`);
			continue;
		}
		const cu = parseCustom(fields, v);
		if (cu.message) {
			fail(cu.message);
			continue;
		}
		const guardians = [
			{ relation: v.g1rel, name: v.g1name, phone: fixPhone(v.g1phone) },
			{ relation: v.g2rel, name: v.g2name, phone: fixPhone(v.g2phone) }
		].filter((g) => g.name);
		out.push({
			row, name: v.name, gender, birthDate: birth, phone: fixPhone(v.phone), school: v.school, gradeId: grade.id, classId, status,
			baptism, mentor: v.mentor, address: v.address, notes: v.notes, guardians, custom: cu.custom
		});
	}
	return { rows: out, problems, total: raw.length };
}

export async function readTeacherFile(file: File, dept: Department, classes: Array<{ id: number; name: string }>, fields: CustomField[] = []) {
	const t = dept.terms;
	const cols = teacherColumns(t, fields);
	const raw = await readSheet(file, t.teacher, cols);
	const out: TeacherImportRow[] = [];
	const problems: ParseProblem[] = [];

	for (const { row, v } of raw) {
		const fail = (message: string) => problems.push({ row, name: v.name, message });
		if (!v.name) {
			fail('이름이 비어 있어요.');
			continue;
		}
		const birth = parseDate(v.birthDate);
		if (birth === null) {
			fail(`생년월일 "${v.birthDate}"을(를) 읽을 수 없어요. 1985-03-12 처럼 적어 주세요.`);
			continue;
		}
		const sv = norm(v.status);
		let status: 'active' | 'inactive' = 'active';
		if (sv) {
			if (['활동', 'active'].includes(sv)) status = 'active';
			else if (['쉬는중', '쉼', '비활동', 'inactive'].includes(sv)) status = 'inactive';
			else {
				fail(`상태 "${v.status}"을(를) 알 수 없어요. (활동, 쉬는 중)`);
				continue;
			}
		}
		const classIds: number[] = [];
		let bad = '';
		for (const part of v.classes.split(/[,/·、]/).map((x) => x.trim()).filter(Boolean)) {
			const c = find(classes, part);
			if (c) classIds.push(c.id);
			else bad = part;
		}
		if (bad) {
			fail(`담당 ${t.class} "${bad}"을(를) 찾을 수 없어요. ${classes.length ? `(${classes.map((x) => x.name).join(', ')})` : ''}`);
			continue;
		}
		const cu = parseCustom(fields, v);
		if (cu.message) {
			fail(cu.message);
			continue;
		}
		out.push({ row, name: v.name, position: v.position, officerRole: v.officerRole, birthDate: birth, phone: fixPhone(v.phone), status, notes: v.notes, classIds, custom: cu.custom });
	}
	return { rows: out, problems, total: raw.length };
}
