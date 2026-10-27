<script lang="ts">
	import { goto } from '$app/navigation';
	import { login } from '$lib/session.svelte';
	import { url } from '$lib/url';

	let { data } = $props();
	let loginId = $state('');
	let password = $state('');
	let error = $state('');

	async function submit(e: SubmitEvent) {
		e.preventDefault();
		if (!loginId.trim() || !password) {
			error = '아이디와 비밀번호를 입력해 주세요.';
			return;
		}
		login(loginId.trim());
		await goto(url('/d/1'));
	}
</script>

<svelte:head><title>로그인 · dep-manage</title></svelte:head>

<main class="wrap">
	<span class="deco d1" aria-hidden="true"></span>
	<span class="deco d2" aria-hidden="true"></span>
	<span class="deco d3" aria-hidden="true"></span>
	<span class="deco d4" aria-hidden="true"></span>
	<span class="deco d5" aria-hidden="true"></span>
	<span class="deco d6" aria-hidden="true"></span>

	<div class="card box">
		<div class="brand">
			<div class="logo" aria-hidden="true">{data.church.name.slice(0, 1)}</div>
			<div>
				<h1>{data.church.name}</h1>
				<p class="muted">부서 관리 시스템</p>
			</div>
		</div>

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
			<button class="btn btn-primary btn-lg btn-block" type="submit">로그인</button>
		</form>

		<div class="foot">
			<a href={url('/install')} class="link">설치 화면 미리보기</a>
			<p class="muted small">가입 후 관리자가 승인하면 사용할 수 있어요.<br />비밀번호를 5번 틀리면 15분 동안 잠겨요.</p>
			<p class="demo">데모 화면입니다. 아무 아이디·비밀번호로 들어갈 수 있고, 모든 이름과 자료는 가상입니다.</p>
		</div>
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
	.demo { font-size: 12.5px; color: #a04a07; background: #fff8ec; border: 1px solid #ffd9a8; border-radius: 10px; padding: 8px 10px; }

	.deco { position: absolute; display: block; }
	.d1 { left: -40px; top: -30px; width: 150px; height: 150px; border-radius: 50%; background: #daf1fc; }
	.d2 { left: 70px; top: 70px; width: 46px; height: 46px; border-radius: 14px; background: #ffe3ee; }
	.d3 { right: -30px; top: 50px; width: 120px; height: 120px; border-radius: 36px; background: #ffebd0; }
	.d4 { left: -20px; bottom: 60px; width: 110px; height: 110px; border-radius: 34px; background: #ece5ff; }
	.d5 { right: -50px; bottom: -40px; width: 170px; height: 170px; border-radius: 50%; background: #d9f5f1; }
	.d6 { right: 110px; bottom: 30px; width: 44px; height: 44px; border-radius: 14px; background: #e3ecff; }
</style>
