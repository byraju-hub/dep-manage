<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, updateChurchName, updateDepartmentInfo } from '$lib/api';
import { session } from '$lib/session.svelte';

	let { data } = $props();
	let churchName = $state('');
	let name = $state('');
	let pastorName = $state('');
	let headName = $state('');
	let saved = $state(false);
let error = $state('');

	$effect(() => {
		churchName = data.church.name;
		name = data.dept.name;
		pastorName = data.dept.pastorName;
		headName = data.dept.headName;
	});

	async function save(e: SubmitEvent) {
		e.preventDefault();
		error = '';
		try {
			if (session.user?.isOwner) await updateChurchName(churchName);
			await updateDepartmentInfo(data.dept.id, { name: name.trim() || data.dept.name, pastorName, headName });
			await invalidateAll();
			saved = true;
		} catch (err) {
			error = err instanceof ApiError ? err.message : '저장하지 못했어요.';
		}
	}
</script>

<form class="card box" onsubmit={save}>
	<section>
		<h2>교회</h2>
		<p class="sub">모든 부서에 함께 적용됩니다. 왼쪽 위와 로그인 화면에 표시돼요.</p>
		<div class="grid one">
			<label class="field"><span>교회 이름</span><input class="input" bind:value={churchName} oninput={() => (saved = false)} disabled={!session.user?.isOwner} />{#if !session.user?.isOwner}<small class="muted">교회 관리자만 바꿀 수 있어요</small>{/if}</label>
		</div>
	</section>

	<section>
		<h2>부서</h2>
		<p class="sub">지금 보고 있는 부서에만 적용됩니다.</p>
		<div class="grid">
			<label class="field"><span>부서 이름</span><input class="input" bind:value={name} oninput={() => (saved = false)} /></label>
			<label class="field"><span>담당 {data.dept.terms.pastor}</span><input class="input" bind:value={pastorName} oninput={() => (saved = false)} /></label>
			<label class="field"><span>{data.dept.terms.head}</span><input class="input" bind:value={headName} oninput={() => (saved = false)} /></label>
		</div>
	</section>

	<div class="buttons">
		{#if error}<span class="bad" role="alert">{error}</span>{/if}
		{#if saved}<span class="ok" role="status">저장했어요</span>{/if}
		<button class="btn btn-primary" type="submit">저장</button>
	</div>
</form>

<style>
	.box { padding: 22px 26px; display: flex; flex-direction: column; gap: 22px; max-width: 760px; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13px; color: var(--muted); margin-bottom: 12px; }
	.grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
	.grid.one { grid-template-columns: minmax(0, 360px); }
	.buttons { display: flex; justify-content: flex-end; align-items: center; gap: 12px; }
	.ok { color: var(--ok-ink); font-weight: 700; }
	.bad { color: var(--danger-ink); font-weight: 700; }
	@media (max-width: 720px) { .grid { grid-template-columns: 1fr; } .grid.one { grid-template-columns: 1fr; } .box { padding: 18px 16px; } }
</style>
