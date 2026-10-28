<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, createInvite, deleteInvite, resetUserPassword, updateUser } from '$lib/api';
	import { can, session } from '$lib/session.svelte';
	import { url } from '$lib/url';
	import type { Role, UserRow } from '$lib/types';

	let { data } = $props();

	const roleLabels: Record<Role, string> = { admin: '부서 관리자', teacher: '부서 교사', viewer: '열람' };
	const statusLabels = { pending: '승인 대기', active: '사용 중', disabled: '사용 중지' } as const;
	const statusTone = { pending: 'tone-orange', active: 'tone-green', disabled: 'tone-slate' } as const;

	let error = $state('');
	let tempPassword = $state<{ name: string; pw: string } | null>(null);
	let newInvite = $state<{ code: string } | null>(null);

	// 새 초대 코드 만들기
	let invDept = $state(data.dept.id);
	let invRole = $state<Role>('teacher');
	let invUses = $state(1);
	let invDays = $state(7);

	async function act(fn: () => Promise<unknown>) {
		error = '';
		try {
			await fn();
			await invalidateAll();
		} catch (e) {
			error = e instanceof ApiError ? e.message : '처리하지 못했어요.';
		}
	}

	const roleOf = (u: UserRow, deptId: number) => u.memberships.find((m) => m.deptId === deptId)?.role ?? null;

	async function setRole(u: UserRow, deptId: number, role: Role | null) {
		await act(() => updateUser(u.id, { memberships: [{ deptId, role }] }));
	}

	async function reset(u: UserRow) {
		await act(async () => {
			const r = await resetUserPassword(u.id);
			tempPassword = { name: u.name, pw: r.tempPassword };
		});
	}

	async function makeInvite() {
		await act(async () => {
			const r = await createInvite({ deptId: invDept, role: invRole, maxUses: invUses, days: invDays });
			newInvite = { code: r.code };
		});
	}

	const joinLink = (code: string) => `${location.origin}${url('/join')}?code=${code}`;
	// 부서 관리자는 부서 관리자 초대를 만들 수 없다 (서버도 막는다).
	const canInviteAdmin = $derived(!!session.user?.isOwner);
</script>

<div class="stack">
	{#if error}<p class="err" role="alert">{error}</p>{/if}

	{#if tempPassword}
		<div class="card temp" role="status">
			<b>{tempPassword.name}</b>님의 임시 비밀번호예요. 지금 한 번만 보여주니 꼭 전달해 주세요.
			<code>{tempPassword.pw}</code>
			<button class="btn" onclick={() => (tempPassword = null)}>닫기</button>
		</div>
	{/if}

	<section class="card box">
		<h2>사용자</h2>
		<p class="sub">초대 코드로 가입한 사람은 <b>승인 대기</b> 상태로 들어와요. 승인하면 로그인할 수 있어요. 역할은 <b>부서마다</b> 따로 정해요. 한 사람이 여러 부서에 다른 역할로 들어갈 수 있고, <b>소속이 아닌 부서는 화면에 나타나지도, 열리지도 않아요</b>.</p>
		<div class="scroll">
			<table>
				<caption class="sr-only">사용자 목록</caption>
				<thead><tr><th scope="col">이름</th><th scope="col">아이디</th><th scope="col">상태</th><th scope="col">부서 · 역할</th><th scope="col"><span class="sr-only">작업</span></th></tr></thead>
				<tbody>
					{#each data.users as u (u.id)}
						<tr>
							<td class="strong">{u.name}{#if u.isOwner} <span class="badge tone-violet">교회 관리자</span>{/if}</td>
							<td class="c2">{u.loginId}</td>
							<td><span class="badge {statusTone[u.status]}">{statusLabels[u.status]}</span></td>
							<td>
								{#if u.isOwner}
									<span class="muted">모든 부서 (교회 관리자)</span>
								{:else}
									{#each data.depts as d (d.id)}
										{@const cur = roleOf(u, d.id)}
										<div class="mem">
											<span class="dn">{d.name}</span>
											{#if u.id !== session.user?.id && can(d.id, 'admin')}
												<select class="mini" value={cur ?? ''} aria-label="{u.name} {d.name} 역할" onchange={(e) => setRole(u, d.id, (e.currentTarget.value || null) as Role | null)}>
													<option value="">소속 아님</option>
													{#each Object.entries(roleLabels) as [k, l] (k)}<option value={k} disabled={k === 'admin' && !canInviteAdmin}>{l}</option>{/each}
												</select>
											{:else}
												<span class="muted">{cur ? roleLabels[cur] : '소속 아님'}</span>
											{/if}
										</div>
									{/each}
								{/if}
							</td>
							<td class="act">
								{#if !u.isOwner && u.id !== session.user?.id}
									{#if u.status === 'pending'}
										<button class="btn sm ok" onclick={() => act(() => updateUser(u.id, { status: 'active' }))}>승인</button>
									{:else if u.status === 'active'}
										<button class="btn sm" onclick={() => act(() => updateUser(u.id, { status: 'disabled' }))}>사용 중지</button>
									{:else}
										<button class="btn sm" onclick={() => act(() => updateUser(u.id, { status: 'active' }))}>다시 사용</button>
									{/if}
									<button class="btn sm" onclick={() => reset(u)}>비밀번호 초기화</button>
								{/if}
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
	</section>

	<section class="card box">
		<h2>초대 코드</h2>
		<p class="sub">코드를 받은 사람이 <b>가입 화면</b>에 입력하면 이 부서에 이 역할로 가입 신청을 해요.</p>
		<form class="make" onsubmit={(e) => { e.preventDefault(); void makeInvite(); }}>
			<label class="field"><span>부서</span>
				<select class="input" bind:value={invDept}>{#each data.depts as d (d.id)}<option value={d.id}>{d.name}</option>{/each}</select>
			</label>
			<label class="field"><span>역할</span>
				<select class="input" bind:value={invRole}>{#each Object.entries(roleLabels) as [k, l] (k)}<option value={k} disabled={k === 'admin' && !canInviteAdmin}>{l}</option>{/each}</select>
			</label>
			<label class="field"><span>쓸 수 있는 횟수</span><input class="input" type="number" min="1" max="100" bind:value={invUses} /></label>
			<label class="field"><span>유효 기간(일)</span><input class="input" type="number" min="1" max="90" bind:value={invDays} /></label>
			<button class="btn btn-primary" type="submit">코드 만들기</button>
		</form>

		{#if newInvite}
			<div class="newcode" role="status">
				<div>새 초대 코드 <code>{newInvite.code}</code></div>
				<div class="muted">가입 주소: <span class="link">{joinLink(newInvite.code)}</span></div>
			</div>
		{/if}

		{#if data.invites.length}
			<table class="inv">
				<caption class="sr-only">초대 코드 목록</caption>
				<thead><tr><th scope="col">코드</th><th scope="col">부서</th><th scope="col">역할</th><th scope="col">사용</th><th scope="col">만료</th><th scope="col"><span class="sr-only">삭제</span></th></tr></thead>
				<tbody>
					{#each data.invites as i (i.id)}
						<tr>
							<td><code>{i.code}</code></td>
							<td>{i.deptName ?? '-'}</td>
							<td>{roleLabels[i.role]}</td>
							<td>{i.usedCount} / {i.maxUses}</td>
							<td class="c2">{i.expiresAt?.slice(0, 10) ?? '-'}</td>
							<td><button class="btn sm" onclick={() => act(() => deleteInvite(i.id))}>삭제</button></td>
						</tr>
					{/each}
				</tbody>
			</table>
		{/if}
	</section>
</div>

<style>
	.stack { display: flex; flex-direction: column; gap: 16px; }
	.box { padding: 20px 24px; display: flex; flex-direction: column; gap: 12px; }
	h2 { font-size: 18px; font-weight: 700; }
	.sub { font-size: 13.5px; color: var(--muted); }
	.err { color: var(--danger-ink); font-weight: 700; }
	.temp { padding: 14px 18px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; background: #fff8ec; border-color: #ffd9a8; }
	code { font-family: ui-monospace, Consolas, monospace; font-weight: 700; background: var(--field-bg); border: 1px solid var(--line); border-radius: 8px; padding: 2px 8px; letter-spacing: 0.06em; }
	.scroll { overflow-x: auto; }
	table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 640px; }
	th { text-align: left; font-size: 12.5px; color: var(--ink-2); padding: 8px 8px; font-weight: 600; }
	td { padding: 8px; border-top: 1px solid var(--line-soft); vertical-align: middle; }
	.strong { font-weight: 600; }
	.c2 { color: var(--ink-2); }
	.mem { display: flex; align-items: center; gap: 8px; margin: 2px 0; }
	.dn { min-width: 84px; font-size: 13.5px; }
	.mini { height: 30px; border: 1px solid var(--line-strong); border-radius: 8px; padding: 0 6px; background: var(--surface); }
	.x { width: 24px; height: 24px; border: 0; border-radius: 50%; background: transparent; color: var(--muted); font-size: 16px; }
	.x:hover { background: var(--field-bg); }
	.act { white-space: nowrap; text-align: right; }
	.btn.sm { height: 32px; padding: 0 12px; font-size: 13px; margin-left: 4px; }
	.btn.ok { background: var(--ok); color: #fff; border-color: transparent; }
	.make { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; align-items: end; }
	.newcode { background: var(--accent-soft); border-radius: 12px; padding: 12px 16px; display: flex; flex-direction: column; gap: 4px; }
	.link { word-break: break-all; }
	.inv { min-width: 560px; }
</style>
