import { resolve } from '$app/paths';

/**
 * 앱 안의 주소를 만든다. 서브폴더에 설치된 경우(BASE_PATH) 앞에 그 경로를 붙여 준다.
 * resolve()는 라우트 이름 모양의 문자열만 타입으로 허용하지만, 동적으로 만든 경로도 동작하므로 여기서 한 번만 우회한다.
 */
export function url(path: string): string {
	return (resolve as (p: string) => string)(path);
}
