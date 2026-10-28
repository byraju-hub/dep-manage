<script lang="ts">
	import { goto, invalidateAll } from '$app/navigation';
	import { ApiError, addSampleData, backupUrl, deleteSampleData, runMigrations } from '$lib/api';
import { session } from '$lib/session.svelte';
	import { url } from '$lib/url';

	let { data } = $props();

	let updating = $state(false);
	let updateMsg = $state('');

	async function update() {
		updating = true;
		error = updateMsg = '';
		try {
			const r = await runMigrations();
			updateMsg = r.applied ? `업데이트를 적용했어요. (${r.applied}단계)` : '이미 최신이에요.';
			await invalidateAll();
			location.reload();
		} catch (e) {
			error = e instanceof ApiError ? e.message : '업데이트하지 못했어요.';
		} finally {
			updating = false;
		}
	}

	let confirming = $state(false);
	let busy = $state(false);
	let message = $state('');
	let error = $state('');

	const total = $derived(data.counts ? Object.values(data.counts).reduce((a, b) => a + b, 0) : 0);
	const labels: Record<string, string> = { departments: '부서', students: '학생', teachers: '교사', visits: '심방 기록', events: '일정', notices: '공지', meetings: '회의록' };

	async function run(action: () => Promise<unknown>, done: string) {
		busy = true;
		error = message = '';
		try {
			await action();
			message = done;
			confirming = false;
			await invalidateAll();
			// 지금 보던 부서가 샘플 부서였다면 사라졌을 수 있으니 처음 화면으로 돌아간다.
			await goto(url('/'), { invalidateAll: true });
		} catch (e) {
			error = e instanceof ApiError ? e.message : '처리하지 못했어요.';
		} finally {
			busy = false;
		}
	}
</script>

{#if session.user?.isOwner}
	<section class="card box">
		<h2>백업</h2>
		<p class="sub">데이터베이스 전체와 학생·교사 사진을 zip 파일 하나로 내려받아요. 새 버전으로 올리기 전이나 정기적으로 받아 두세요. 파일 안의 <b>backup.sql</b>은 phpMyAdmin의 가져오기로 복원할 수 있어요.</p>
		<p class="warnbox">이 파일에는 개인정보와 비밀번호 해시가 들어 있어요. 안전한 곳에만 보관하세요.</p>
		<div><a class="btn btn-primary" href={backupUrl()} download>백업 파일 내려받기</a></div>
	</section>

	<section class="card box">
		<h2>업데이트</h2>
		{#if data.migrations?.pending}
			<p class="warnbox">새 버전의 데이터베이스 변경이 아직 적용되지 않았어요. (현재 {data.migrations.current} → 최신 {data.migrations.latest})</p>
			<div><button class="btn btn-primary" onclick={update} disabled={updating}>{updating ? '적용 중…' : '업데이트 적용'}</button></div>
		{:else}
			<p class="sub">데이터베이스가 최신 상태예요. (버전 {data.migrations?.current})</p>
		{/if}
		{#if updateMsg}<p class="okmsg" role="status">{updateMsg}</p>{/if}
	</section>
{/if}

<section class="card box">
	<h2>샘플 데이터</h2>
	{#if !data.counts}
		<p class="sub">샘플 데이터는 교회 관리자만 관리할 수 있어요.</p>
	{:else}
		<p class="sub">체험을 위해 넣어 둔 가상의 학생·교사·출석·심방 자료예요. 아래 버튼은 <b>샘플로 표시된 자료만</b> 지우고, 직접 입력한 자료는 그대로 둡니다.</p>

		{#if total > 0}
			<ul class="counts">
				{#each Object.entries(data.counts) as [k, n] (k)}
					{#if n > 0}<li><span class="n">{n}</span>{labels[k]}</li>{/if}
				{/each}
			</ul>

			{#if !confirming}
				<div><button class="btn danger" onclick={() => (confirming = true)}>샘플 데이터 삭제</button></div>
			{:else}
				<div class="confirm" role="alertdialog" aria-label="샘플 데이터 삭제 확인">
					<p><b>샘플 데이터 {total}건을 삭제할까요?</b><br />되돌릴 수 없어요. 직접 입력한 자료는 지워지지 않아요.</p>
					<div class="row">
						<button class="btn" onclick={() => (confirming = false)} disabled={busy}>취소</button>
						<button class="btn danger solid" onclick={() => run(deleteSampleData, '샘플 데이터를 삭제했어요.')} disabled={busy}>{busy ? '삭제 중…' : '삭제하기'}</button>
					</div>
				</div>
			{/if}
		{:else}
			<p class="none">지금은 샘플 데이터가 없어요.</p>
			<div><button class="btn" onclick={() => run(addSampleData, '샘플 데이터를 넣었어요.')} disabled={busy}>{busy ? '넣는 중…' : '샘플 데이터 다시 넣기'}</button></div>
		{/if}

		{#if message}<p class="okmsg" role="status">{message}</p>{/if}
		{#if error}<p class="err" role="alert">{error}</p>{/if}
	{/if}
</section>

<style>
	.box { padding: 22px 26px; display: flex; flex-direction: column; gap: 14px; max-width: 760px; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13.5px; color: var(--muted); }
	.counts { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; }
	.counts li { background: var(--field-bg); border: 1px solid var(--line); border-radius: 999px; padding: 4px 12px; font-size: 13px; color: var(--ink-2); }
	.n { font-weight: 700; color: var(--ink); margin-right: 4px; }
	.none { color: var(--muted); }
	.btn.danger { color: var(--danger-ink); border-color: #f5b8c7; }
	.btn.danger.solid { background: var(--danger); border-color: transparent; color: #fff; }
	.confirm { background: #fff5f7; border: 1px solid #f5b8c7; border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
	.row { display: flex; gap: 10px; }
	.warnbox { background: #fff8ec; border: 1px solid #ffd9a8; color: #7a4a06; border-radius: 12px; padding: 10px 14px; font-size: 13.5px; }
	.okmsg { color: var(--ok-ink); font-weight: 700; }
	.err { color: var(--danger-ink); font-weight: 700; }
	button:disabled { opacity: 0.6; }
</style>
