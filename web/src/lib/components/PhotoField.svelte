<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, deletePhoto, photoUrl, uploadPhoto } from '../api';
	import type { Tone } from '../types';
	import Avatar from './Avatar.svelte';

	// 사진 올리기·바꾸기·지우기. 올린 사진은 서버가 알아서 줄여서 저장한다.
	let { type, id, name, token, tone = 'blue' }: { type: 'student' | 'teacher'; id: number; name: string; token: string | null | undefined; tone?: Tone } = $props();

	let current = $state<string | null>(null);
	$effect(() => {
		current = token ?? null;
	});
	let busy = $state(false);
	let error = $state('');
	let input = $state<HTMLInputElement>();

	async function pick(e: Event) {
		const file = (e.target as HTMLInputElement).files?.[0];
		if (!file) return;
		error = '';
		busy = true;
		try {
			current = (await uploadPhoto(type, id, file)).photo;
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '사진을 올리지 못했어요.';
		} finally {
			busy = false;
			if (input) input.value = '';
		}
	}

	async function remove() {
		error = '';
		busy = true;
		try {
			await deletePhoto(type, id);
			current = null;
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '사진을 지우지 못했어요.';
		} finally {
			busy = false;
		}
	}
</script>

<div class="box">
	<Avatar {name} {tone} size={96} src={photoUrl(type, id, current)} />
	<div class="side">
		<div class="row">
			<label class="btn up">
				<input bind:this={input} type="file" accept="image/jpeg,image/png,image/webp" onchange={pick} disabled={busy} />
				{busy ? '처리 중…' : current ? '사진 바꾸기' : '사진 올리기'}
			</label>
			{#if current}<button type="button" class="btn" onclick={remove} disabled={busy}>지우기</button>{/if}
		</div>
		<p class="muted">JPG, PNG, WEBP · 5MB 이하. 올린 사진은 자동으로 알맞은 크기로 줄여서 저장해요.</p>
		{#if error}<p class="err" role="alert">{error}</p>{/if}
	</div>
</div>

<style>
	.box { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
	.side { display: flex; flex-direction: column; gap: 6px; }
	.row { display: flex; gap: 8px; }
	.up { position: relative; cursor: pointer; }
	.up input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
	.up:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 2px; }
	.muted { font-size: 12.5px; }
	.err { color: var(--danger-ink); font-weight: 700; font-size: 13px; }
</style>
