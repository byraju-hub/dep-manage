<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { updateDepartmentInfo } from '$lib/api';

	let { data } = $props();
	let name = $state('');
	let pastorName = $state('');
	let headName = $state('');
	let saved = $state(false);

	$effect(() => {
		name = data.dept.name;
		pastorName = data.dept.pastorName;
		headName = data.dept.headName;
	});

	async function save(e: SubmitEvent) {
		e.preventDefault();
		await updateDepartmentInfo(data.dept.id, { name: name.trim() || data.dept.name, pastorName, headName });
		await invalidateAll();
		saved = true;
	}
</script>

<form class="card box" onsubmit={save}>
	<h2>부서 정보</h2>
	<div class="grid">
		<label class="field"><span>부서 이름</span><input class="input" bind:value={name} oninput={() => (saved = false)} /></label>
		<label class="field"><span>담당 교역자</span><input class="input" bind:value={pastorName} oninput={() => (saved = false)} /></label>
		<label class="field"><span>부장 · 부서장</span><input class="input" bind:value={headName} oninput={() => (saved = false)} /></label>
	</div>
	<div class="buttons">
		{#if saved}<span class="ok" role="status">저장했어요</span>{/if}
		<button class="btn btn-primary" type="submit">저장</button>
	</div>
</form>

<style>
	.box { padding: 22px 26px; display: flex; flex-direction: column; gap: 14px; max-width: 760px; }
	h2 { font-size: 18px; font-weight: 700; }
	.grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
	.buttons { display: flex; justify-content: flex-end; align-items: center; gap: 12px; }
	.ok { color: var(--ok-ink); font-weight: 700; }
	@media (max-width: 720px) { .grid { grid-template-columns: 1fr; } .box { padding: 18px 16px; } }
</style>
