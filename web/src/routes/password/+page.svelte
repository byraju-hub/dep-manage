<script lang="ts">
	import { goto } from '$app/navigation';
	import { ApiError, changePassword, listDepartments } from '$lib/api';
	import { doLogout, loadSession, session } from '$lib/session.svelte';
	import { url } from '$lib/url';

	let { data } = $props();

	let current = $state('');
	let next = $state('');
	let next2 = $state('');
	let error = $state('');
	let busy = $state(false);

	// 관리자가 비밀번호를 초기화했거나 내가 직접 바꾸러 온 경우 모두 이 화면을 쓴다.
	const forced = $derived(!!session.user?.mustChangePassword);

	async function submit(e: SubmitEvent) {
		e.preventDefault();
		error = '';
		if (next !== next2) return void (error = '새 비밀번호가 서로 일치하지 않아요.');
		busy = true;
		try {
			await changePassword(current, next);
			await loadSession();
			const depts = await listDepartments();
			await goto(depts.length ? url(`/d/${depts[0].id}`) : url('/login'));
		} catch (err) {
			error = err instanceof ApiError ? err.message : '바꾸지 못했어요. 잠시 뒤 다시 시도해 주세요.';
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>비밀번호 변경 · {data.church.name}</title></svelte:head>

<main class="wrap">
	<div class="card box">
		<h1>비밀번호를 바꿔요</h1>
		{#if forced}<p class="muted">관리자가 임시 비밀번호를 정해 주었어요. 나만 아는 비밀번호로 바꿔야 계속 쓸 수 있어요.</p>{/if}
		<form onsubmit={submit} novalidate>
			<label class="field"><span>지금 비밀번호</span><input class="input" type="password" bind:value={current} autocomplete="current-password" /></label>
			<label class="field"><span>새 비밀번호</span><input class="input" type="password" bind:value={next} autocomplete="new-password" /><small class="muted">8자 이상</small></label>
			<label class="field"><span>새 비밀번호 확인</span><input class="input" type="password" bind:value={next2} autocomplete="new-password" /></label>
			{#if error}<p class="err" role="alert">{error}</p>{/if}
			<div class="row">
				<button class="btn btn-primary" type="submit" disabled={busy}>{busy ? '처리 중…' : '바꾸기'}</button>
				<button class="btn" type="button" onclick={doLogout}>로그아웃</button>
			</div>
		</form>
	</div>
</main>

<style>
	.wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
	.box { width: 100%; max-width: 400px; padding: 28px; display: flex; flex-direction: column; gap: 14px; }
	h1 { font-size: 20px; font-weight: 700; }
	form { display: flex; flex-direction: column; gap: 12px; }
	.err { color: var(--danger-ink); font-size: 13px; font-weight: 600; }
	.row { display: flex; gap: 10px; }
	small { font-size: 12px; }
</style>
