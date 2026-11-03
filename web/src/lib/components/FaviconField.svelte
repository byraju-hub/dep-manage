<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, deleteFavicon, faviconUrl, uploadFavicon } from '../api';

	// 브라우저 탭 아이콘 올리기·바꾸기·지우기 (교회 관리자만). 안 올리면 교회 로고를, 로고도 없으면 기본 아이콘을 쓴다.
	let { favicon }: { favicon: string | null | undefined } = $props();

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
			await uploadFavicon(file);
			await invalidateAll();
			ok = '탭 아이콘을 바꿨어요.';
		} catch (err) {
			error = err instanceof ApiError ? err.message : '탭 아이콘을 올리지 못했어요.';
		} finally {
			busy = false;
			if (input) input.value = '';
		}
	}

	async function remove() {
		error = ok = '';
		busy = true;
		try {
			await deleteFavicon();
			await invalidateAll();
			ok = '탭 아이콘을 지웠어요. 이제 교회 로고를 써요.';
		} catch (err) {
			error = err instanceof ApiError ? err.message : '지우지 못했어요.';
		} finally {
			busy = false;
		}
	}
</script>

<div class="box">
	<div class="preview" aria-hidden="true">
		{#if favicon}<img src={faviconUrl(favicon)} alt="" width="40" height="40" />{:else}<span class="none">없음</span>{/if}
	</div>
	<div class="side">
		<div class="row">
			<label class="btn up">
				<input bind:this={input} type="file" accept="image/png,image/jpeg,image/webp" onchange={pick} disabled={busy} />
				{busy ? '처리 중…' : favicon ? '탭 아이콘 바꾸기' : '탭 아이콘 올리기'}
			</label>
			{#if favicon}<button type="button" class="btn" onclick={remove} disabled={busy}>지우기</button>{/if}
		</div>
		<p class="muted">브라우저 탭과 휴대폰 홈 화면에 나오는 작은 그림이에요. 정사각형 이미지(PNG 권장, 128×128 이상)를 올려 주세요. 올리지 않으면 교회 로고를 써요. 브라우저가 기억하고 있어서 바뀌는 데 시간이 걸릴 수 있어요.</p>
		{#if ok}<p class="okmsg" role="status">{ok}</p>{/if}
		{#if error}<p class="err" role="alert">{error}</p>{/if}
	</div>
</div>

<style>
	.box { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
	.preview { width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; border: 1px dashed var(--line); border-radius: 14px; }
	.preview img { border-radius: 8px; }
	.none { font-size: 12px; color: var(--muted); }
	.side { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 240px; }
	.row { display: flex; gap: 8px; }
	.up { position: relative; cursor: pointer; }
	.up input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
	.up:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 2px; }
	.muted { font-size: 12.5px; line-height: 1.6; }
	.okmsg { color: var(--ok-ink); font-weight: 700; font-size: 13px; }
	.err { color: var(--danger-ink); font-weight: 700; font-size: 13px; }
</style>
