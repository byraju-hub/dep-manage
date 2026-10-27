import adapter from '@sveltejs/adapter-static';
import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

export default defineConfig({
	plugins: [
		sveltekit({
			compilerOptions: {
				// Force runes mode for the project, except for libraries. Can be removed in svelte 6.
				runes: ({ filename }) =>
					filename.split(/[/\\]/).includes('node_modules') ? undefined : true
			},

			// 정적 SPA로 빌드한다. 모든 경로는 index.html로 넘어가고, 데이터는 PHP JSON API에서 받는다.
			adapter: adapter({ fallback: 'index.html' }),

			// SvelteKit 3 은 $lib 를 없앴지만, 익숙한 이름을 그대로 쓰기 위해 별칭을 둔다.
			alias: { $lib: 'src/lib' },

			// 서브폴더에 설치하는 경우 BASE_PATH=/church 처럼 지정하고 빌드한다.
			paths: { base: process.env.BASE_PATH ?? '' }
		})
	],
	server: {
		// 개발 중 PHP 서버(php -S localhost:8000 -t server)로 /api 요청을 넘긴다.
		proxy: { '/api': 'http://localhost:8000' }
	}
});
