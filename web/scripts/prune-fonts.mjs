// 빌드 뒤에 글꼴 파일을 줄인다. (npm run build 가 자동으로 부른다)
//
// fontsource 패키지는 같은 글꼴을 woff2 와 woff 두 가지로, 그리고 한글이 아닌 언어(키릴, 그리스, 베트남어 등) 조각까지
// 함께 담고 있다. 이 프로그램은 한국어 화면이고 모든 최신 브라우저가 woff2 를 지원하므로,
// woff 와 한글·영문(라틴)이 아닌 조각은 버려서 업로드할 파일 수와 용량을 줄인다.
import { readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const dir = new URL('../build/_app/immutable/assets/', import.meta.url);
const root = dir.pathname.replace(/^\/([A-Za-z]:)/, '$1');

const files = readdirSync(root);
const css = files.filter((f) => f.endsWith('.css'));

// 버릴 글꼴 조각: 이름에 이 낱말이 들어 있는 woff2
const dropSubsets = /-(cyrillic|cyrillic-ext|greek|greek-ext|vietnamese|latin-ext)-/;

let removed = 0;
let bytes = 0;
const drop = (name) => {
	try {
		bytes += readFileSync(join(root, name)).length;
		rmSync(join(root, name));
		removed++;
	} catch {
		// 이미 없으면 무시
	}
};

for (const name of css) {
	const path = join(root, name);
	let text = readFileSync(path, 'utf8');
	const before = text.length;

	// 1) woff 대체 주소 제거: src:url(a.woff2) format("woff2"),url(a.woff) format("woff")  →  src:url(a.woff2) format("woff2")
	// (작은 글꼴은 파일이 아니라 data: 주소로 CSS 안에 들어가 있기도 하다)
	text = text.replace(/,\s*url\((?:data:font\/woff;base64,[^)]*|[^)]*?\.woff)\)\s*format\(["']woff["']\)/g, '');

	// 2) 버릴 조각의 @font-face 규칙 통째로 제거
	text = text.replace(/@font-face\{[^}]*\}/g, (rule) => (dropSubsets.test(rule) ? '' : rule));

	if (text.length !== before) writeFileSync(path, text);
}

for (const name of files) {
	if (name.endsWith('.woff')) drop(name);
	else if (name.endsWith('.woff2') && dropSubsets.test(name)) drop(name);
}

console.log(`글꼴 정리: 파일 ${removed}개 삭제 (${(bytes / 1024 / 1024).toFixed(1)}MB)`);
