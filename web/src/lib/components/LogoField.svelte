<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, deleteLogo, uploadLogo } from '../api';
	import ChurchLogo from './ChurchLogo.svelte';

	// 교회 로고 올리기·바꾸기·지우기 (교회 관리자만). 올리면 왼쪽 위, 로그인 화면, 브라우저 탭 아이콘에 함께 쓰인다.
	let { name, logo }: { name: string; logo: string | null | undefined } = $props();

	let busy = $state(false);
	let error = $state('');
	let ok = $state('');
	let input = $state<HTMLInputElement>();

	async function pick(e: Event) {
		const file = (e.target as HTMLInputElement).files?.[0];
		if (!file) return;
		error = ok = '';
		busy = true;
		try {
			await uploadLogo(file);
			await invalidateAll();
			ok = '로고를 바꿨어요.';
		} catch (err) {
			error = err instanceof ApiError ? err.message : '로고를 올리지 못했어요.';
		} finally {
			busy = false;
			if (input) input.value = '';
		}
	}

	async function remove() {
		error = ok = '';
		busy = true;
		try {
			await deleteLogo();
			await invalidateAll();
			ok = '로고를 지웠어요.';
		} catch (err) {
			error = err instanceof ApiError ? err.message : '지우지 못했어요.';
		} finally {
			busy = false;
		}
	}
</script>

<div class="box">
	<div class="preview">
		<ChurchLogo {name} {logo} size={88} />
		<div class="small" aria-hidden="true">
			<ChurchLogo {name} {logo} size={24} />
			<span>탭 아이콘</span>
		</div>
	</div>
	<div class="side">
		<div class="row">
			<label class="btn up">
				<input bind:this={input} type="file" accept="image/png,image/jpeg,image/webp" onchange={pick} disabled={busy} />
				{busy ? '처리 중…' : logo ? '로고 바꾸기' : '로고 올리기'}
			</label>
			{#if logo}<button type="button" class="btn" onclick={remove} disabled={busy}>지우기</button>{/if}
		</div>
		<p class="muted">정사각형 이미지(PNG 권장, 256×256 이상)를 올려 주세요. 정사각형이 아니면 가운데를 기준으로 잘라 맞춰요. JPG, PNG, WEBP · 5MB 이하.</p>
		<p class="muted">올린 로고는 왼쪽 위 교회 이름 옆, 로그인 화면, 브라우저 탭의 아이콘에 나와요. 탭 아이콘은 브라우저가 기억하고 있어서 바뀌는 데 시간이 걸릴 수 있어요.</p>
		{#if ok}<p class="okmsg" role="status">{ok}</p>{/if}
		{#if error}<p class="err" role="alert">{error}</p>{/if}
	</div>
</div>

<style>
	.box { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
	.preview { display: flex; flex-direction: column; align-items: center; gap: 8px; }
	.small { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); }
	.side { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 240px; }
	.row { display: flex; gap: 8px; }
	.up { position: relative; cursor: pointer; }
	.up input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
	.up:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 2px; }
	.muted { font-size: 12.5px; line-height: 1.6; }
	.okmsg { color: var(--ok-ink); font-weight: 700; font-size: 13px; }
	.err { color: var(--danger-ink); font-weight: 700; font-size: 13px; }
</style>
