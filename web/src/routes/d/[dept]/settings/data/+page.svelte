<script lang="ts">
	import { goto } from '$app/navigation';
	import { resetDemo } from '$lib/api';
	import { url } from '$lib/url';

	async function reset() {
		resetDemo();
		location.href = url('/d/1');
	}
</script>

<section class="card box">
	<h2>데모 데이터</h2>
	<p class="sub">지금 보이는 모든 이름과 자료는 가상입니다. 바꾼 내용(출석, 심방, 용어)은 이 브라우저에만 저장됩니다.</p>
	<div><button class="btn" onclick={reset}>처음 상태로 되돌리기</button></div>
</section>

<style>
	.box { padding: 22px 26px; display: flex; flex-direction: column; gap: 12px; max-width: 760px; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13.5px; color: var(--muted); }
</style>
