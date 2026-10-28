<script lang="ts">
	import { page } from '$app/state';
	import { ApiError, checkInvite, join } from '$lib/api';
	import { url } from '$lib/url';

	let { data } = $props();

	let code = $state(page.url.searchParams.get('code') ?? '');
	let info = $state<{ department: string | null; roleLabel: string } | null>(null);
	let name = $state('');
	let loginId = $state('');
	let password = $state('');
	let password2 = $state('');
	let error = $state('');
	let done = $state(false);
	let busy = $state(false);

	// 코드를 다 입력하면 어느 부서에 어떤 역할로 초대된 것인지 보여준다.
	let timer: ReturnType<typeof setTimeout>;
	$effect(() => {
		const c = code.trim();
		clearTimeout(timer);
		info = null;
		if (c.length < 8) return;
		timer = setTimeout(async () => {
			try {
				info = await checkInvite(c.toUpperCase());
				error = '';
			} catch (e) {
				error = e instanceof ApiError ? e.message : '';
			}
		}, 300);
	});

	async function submit(e: SubmitEvent) {
		e.preventDefault();
		error = '';
		if (!code.trim() || !name.trim() || !loginId.trim() || !password) return void (error = '모든 칸을 입력해 주세요.');
		if (password !== password2) return void (error = '비밀번호가 서로 일치하지 않아요.');
		busy = true;
		try {
			await join({ code: code.trim(), loginId: loginId.trim(), name: name.trim(), password });
			done = true;
		} catch (err) {
			error = err instanceof ApiError ? err.message : '가입하지 못했어요. 잠시 뒤 다시 시도해 주세요.';
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>가입 · {data.church.name}</title></svelte:head>

<main class="wrap">
	<div class="card box">
		<div class="brand">
			<div class="logo" aria-hidden="true">{data.church.name.slice(0, 1)}</div>
			<div>
				<h1>{data.church.name}</h1>
				<p class="muted">초대 코드로 가입하기</p>
			</div>
		</div>

		{#if done}
			<div class="ok" role="status">
				<strong>가입 신청이 끝났어요.</strong>
				<p>관리자가 승인하면 로그인할 수 있어요. 승인되면 방금 만든 아이디와 비밀번호로 들어오세요.</p>
			</div>
			<a class="btn btn-primary btn-lg btn-block" href={url('/login')}>로그인 화면으로</a>
		{:else}
			<form onsubmit={submit} novalidate>
				<label class="field">
					<span>초대 코드</span>
					<input class="input big mono" bind:value={code} autocapitalize="characters" autocomplete="off" />
				</label>
				{#if info}
					<p class="invite">{info.department ?? '교회'} · <b>{info.roleLabel}</b> 으로 초대되었어요.</p>
				{/if}
				<label class="field"><span>이름</span><input class="input big" bind:value={name} autocomplete="name" /></label>
				<label class="field"><span>아이디</span><input class="input big" bind:value={loginId} autocomplete="username" /><small class="muted">영문, 숫자, - _ . 으로 3자 이상</small></label>
				<label class="field"><span>비밀번호</span><input class="input big" type="password" bind:value={password} autocomplete="new-password" /><small class="muted">8자 이상</small></label>
				<label class="field"><span>비밀번호 확인</span><input class="input big" type="password" bind:value={password2} autocomplete="new-password" /></label>
				{#if error}<p class="err" role="alert">{error}</p>{/if}
				<button class="btn btn-primary btn-lg btn-block" type="submit" disabled={busy}>{busy ? '처리 중…' : '가입 신청'}</button>
			</form>
			<div class="foot"><a href={url('/login')} class="link">이미 계정이 있어요</a></div>
		{/if}
	</div>
</main>

<style>
	.wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
	.box { width: 100%; max-width: 420px; padding: 28px 28px 22px; border-radius: 22px; box-shadow: 0 8px 24px rgba(37, 99, 235, 0.1); display: flex; flex-direction: column; gap: 18px; }
	.brand { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; }
	.logo { width: 52px; height: 52px; border-radius: 15px; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 22px; }
	h1 { font-size: 20px; font-weight: 700; line-height: 1.3; }
	form { display: flex; flex-direction: column; gap: 12px; }
	.big { height: 44px; font-size: 15px; }
	.mono { letter-spacing: 0.08em; text-transform: uppercase; }
	.invite { background: var(--accent-soft); color: var(--ink); border-radius: 10px; padding: 8px 12px; font-size: 13.5px; }
	.err { color: var(--danger-ink); font-size: 13px; font-weight: 600; }
	.ok { background: #ddf5e8; color: #0f6b40; border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 4px; }
	.foot { text-align: center; }
	.link { color: var(--accent); font-weight: 700; padding: 6px 8px; }
	small { font-size: 12px; }
	button:disabled { opacity: 0.6; }
</style>
