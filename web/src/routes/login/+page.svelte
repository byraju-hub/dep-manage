<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { ApiError, listDepartments } from '$lib/api';
import ChurchLogo from '$lib/components/ChurchLogo.svelte';
	import { doLogin, doLogout, session } from '$lib/session.svelte';
	import { url } from '$lib/url';

	let { data } = $props();
	let loginId = $state('');
	let password = $state('');
	let error = $state('');
	let busy = $state(false);

	// 로그인은 했는데 속한 부서가 없는 경우
	const noDept = $derived(page.url.searchParams.get('nodept') === '1' && !!session.user);

	async function submit(e: SubmitEvent) {
		e.preventDefault();
		await signIn(loginId, password);
	}

	// 소개 페이지의 '데모 사이트 구경하기'(?demo=1)로 들어오면 체험 계정으로 바로 로그인한다.
	onMount(() => {
		if (page.url.searchParams.get('demo') === '1' && data.church.demoAccount && !session.user) {
			loginId = 'demo';
			void signIn('demo', '1234');
		}
	});

	async function signIn(id: string, pw: string) {
		error = '';
		if (!id.trim() || !pw) {
			error = '아이디와 비밀번호를 입력해 주세요.';
			return;
		}
		busy = true;
		try {
			await doLogin(id.trim(), pw);
			password = '';
			if (session.user?.mustChangePassword) return void (await goto(url('/password')));
			const depts = await listDepartments();
			await goto(depts.length ? url(`/d/${depts[0].id}`) : url('/login?nodept=1'));
		} catch (err) {
			error = err instanceof ApiError ? err.message : '로그인하지 못했어요. 잠시 뒤 다시 시도해 주세요.';
		} finally {
			busy = false;
		}
	}
</script>

<svelte:head><title>로그인 · {data.church.name}</title></svelte:head>

<main class="wrap">
	<span class="deco d1" aria-hidden="true"></span>
	<span class="deco d2" aria-hidden="true"></span>
	<span class="deco d3" aria-hidden="true"></span>
	<span class="deco d4" aria-hidden="true"></span>
	<span class="deco d5" aria-hidden="true"></span>
	<span class="deco d6" aria-hidden="true"></span>

	<div class="card box">
		<div class="brand">
			<ChurchLogo name={data.church.name} logo={data.church.logo} size={56} />
			<div>
				<h1>{data.church.name}</h1>
				<p class="muted">부서 관리 시스템</p>
			</div>
		</div>

		{#if noDept}
			<div class="notice" role="status">
				<p>로그인은 되었지만 아직 속한 부서가 없어요. 관리자에게 부서 배정을 요청해 주세요.</p>
				<button class="btn" onclick={doLogout}>로그아웃</button>
			</div>
		{:else}
			<form onsubmit={submit} novalidate>
				<label class="field">
					<span>아이디</span>
					<input class="input big" type="text" autocomplete="username" bind:value={loginId} />
				</label>
				<label class="field">
					<span>비밀번호</span>
					<input class="input big" type="password" autocomplete="current-password" bind:value={password} />
				</label>
				{#if error}<p class="err" role="alert">{error}</p>{/if}
				<button class="btn btn-primary btn-lg btn-block" type="submit" disabled={busy}>{busy ? '확인 중…' : '로그인'}</button>
			</form>

			<div class="foot">
				<a href={url('/join')} class="link">{data.church.openSignup ? '가입 신청하기' : '초대 코드로 가입하기'}</a>
				{#if data.church.demoAccount}<p class="demo">체험해 보기 · 아이디 <b>demo</b> / 비밀번호 <b>1234</b> (보기만 할 수 있어요)</p>{/if}
				<p class="muted small">가입 후 관리자가 승인하면 사용할 수 있어요.<br />비밀번호를 5번 틀리면 15분 동안 잠겨요.</p>
			</div>
		{/if}
	</div>
</main>

<style>
	.wrap { position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; overflow: hidden; }
	.box { position: relative; width: 100%; max-width: 400px; padding: 32px 30px 26px; border-radius: 22px; box-shadow: 0 8px 24px rgba(37, 99, 235, 0.1); display: flex; flex-direction: column; gap: 20px; }
	.brand { display: flex; flex-direction: column; align-items: center; gap: 10px; text-align: center; }
	.logo { width: 56px; height: 56px; border-radius: 16px; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 24px; }
	h1 { font-size: 21px; font-weight: 700; line-height: 1.3; }
	form { display: flex; flex-direction: column; gap: 14px; }
	.big { height: 46px; font-size: 15px; padding: 0 14px; }
	.err { color: var(--danger-ink); font-size: 13px; font-weight: 600; }
	.foot { display: flex; flex-direction: column; align-items: center; gap: 10px; padding-top: 6px; border-top: 1px solid var(--line-soft); text-align: center; }
	.link { color: var(--accent); font-weight: 700; padding: 6px 8px; }
	.small { font-size: 12.5px; line-height: 1.6; }
	.notice { background: #fff8ec; border: 1px solid #ffd9a8; border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
	button:disabled { opacity: 0.6; cursor: default; }

	.deco { position: absolute; display: block; }
	.d1 { left: -40px; top: -30px; width: 150px; height: 150px; border-radius: 50%; background: #daf1fc; }
	.d2 { left: 70px; top: 70px; width: 46px; height: 46px; border-radius: 14px; background: #ffe3ee; }
	.d3 { right: -30px; top: 50px; width: 120px; height: 120px; border-radius: 36px; background: #ffebd0; }
	.d4 { left: -20px; bottom: 60px; width: 110px; height: 110px; border-radius: 34px; background: #ece5ff; }
	.d5 { right: -50px; bottom: -40px; width: 170px; height: 170px; border-radius: 50%; background: #d9f5f1; }
	.d6 { right: 110px; bottom: 30px; width: 44px; height: 44px; border-radius: 14px; background: #e3ecff; }
	.demo { background: var(--accent-soft); border-radius: 10px; padding: 8px 12px; font-size: 13.5px; text-align: center; }
</style>
