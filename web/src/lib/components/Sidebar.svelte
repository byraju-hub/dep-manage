<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { buildNav, isActive, type NavItem } from '../nav';
	import { url } from '../url';
	import { can, session, doLogout } from '../session.svelte';
	import { terms } from '../terms.svelte';
	import type { Department } from '../types';
	import ChurchLogo from './ChurchLogo.svelte';
import Icon from './Icon.svelte';
	import Tile from './Tile.svelte';

	let { dept, depts, pending, churchName, churchLogo = null }: { dept: Department; depts: Department[]; pending: number; churchName: string; churchLogo?: string | null } = $props();

	const nav = $derived(buildNav(terms, pending));
	let open = $state(false);

	const href = (item: NavItem) => url(`/d/${dept.id}${item.href}`);
	const active = (item: NavItem) => isActive(page.url.pathname, dept.id, item);

	function switchTo(id: number) {
		open = false;
		goto(url(`/d/${id}`));
	}

	const signOut = doLogout;
</script>

{#snippet link(item: NavItem)}
	<a href={href(item)} class="item" class:on={active(item)} aria-current={active(item) ? 'page' : undefined}>
		<Tile icon={item.icon} tone={item.tone} size={30} iconSize={18} />
		<span class="label">{item.label}</span>
		{#if item.badge}<span class="count">{item.badge}</span>{/if}
	</a>
{/snippet}

<aside class="side">
	<a class="brand" href={url(`/d/${dept.id}`)} aria-label="대시보드로 이동">
		<ChurchLogo name={churchName} logo={churchLogo} size={38} />
		<div>
			<div class="church">{churchName}</div>
			<div class="sub">{dept.name} 관리 프로그램</div>
		</div>
	</a>

	<div class="switcher">
		<button class="switch-btn" aria-haspopup="listbox" aria-expanded={open} aria-label="부서 전환" onclick={() => (open = !open)}>
			<span>
				<span class="sw-label">부서</span>
				<span class="sw-name">{dept.name}</span>
			</span>
			<Icon name="chevronsUpDown" size={18} />
		</button>
		{#if open}
			<ul class="menu" role="listbox" aria-label="부서 목록">
				{#each depts as d (d.id)}
					<li role="option" aria-selected={d.id === dept.id}>
						<button class="menu-item" class:cur={d.id === dept.id} onclick={() => switchTo(d.id)}>{d.name}</button>
					</li>
				{/each}
			</ul>
		{/if}
	</div>

	<nav aria-label="주 메뉴">
		{#each nav.main as item (item.key)}{@render link(item)}{/each}
		<div class="group">계획 · 소통</div>
		{#each nav.plan as item (item.key)}{@render link(item)}{/each}
	</nav>

	<div class="bottom">
		{@render link(nav.manual)}
		{#if can(dept.id, 'admin')}{@render link(nav.settings)}{/if}
		<div class="user">
			<span class="me" aria-hidden="true">{session.user?.name.slice(0, 1) ?? '?'}</span>
			<div class="who">
				<div class="name">{session.user?.name}</div>
				<div class="role">{session.user?.roleLabel}</div>
			</div>
			<a class="out" href={url('/password')} aria-label="비밀번호 변경" title="비밀번호 변경"><Icon name="lock" size={18} /></a>
			<button class="out" aria-label="로그아웃" title="로그아웃" onclick={signOut}><Icon name="logout" size={18} /></button>
		</div>
		<p class="copy">ⓒ {new Date().getFullYear()} {churchName}. All Rights Reserved.</p>
	</div>
</aside>

<style>
	.side {
		width: 248px;
		flex-shrink: 0;
		font-family: var(--font-menu);
		position: sticky;
		top: 0;
		height: 100vh;
		overflow-y: auto;
		background: var(--surface);
		border-right: 1px solid var(--line);
		padding: 20px 14px;
		display: flex;
		flex-direction: column;
		gap: 16px;
	}
	.brand { display: flex; align-items: center; gap: 10px; padding: 0 6px; color: inherit; text-decoration: none; }
	.logo {
		width: 38px; height: 38px; border-radius: 12px; background: var(--accent); color: #fff;
		display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 17px;
	}
	.church { font-family: var(--font-title); font-size: 21px; font-weight: 700; line-height: 1.2; overflow-wrap: anywhere; }
	.sub { font-size: 12px; color: var(--muted); }
	.copy { margin: 4px 6px 0; font-size: 11px; line-height: 1.4; color: var(--muted); overflow-wrap: anywhere; }

	.switcher { position: relative; }
	.switch-btn {
		width: 100%; display: flex; align-items: center; justify-content: space-between;
		padding: 9px 12px; border: 1px solid var(--line-strong); border-radius: var(--radius-sm);
		background: var(--field-bg); text-align: left; color: var(--ink);
	}
	.sw-label { display: block; font-size: 11px; color: var(--muted); line-height: 1.2; }
	.sw-name { display: block; font-size: 15px; font-weight: 600; line-height: 1.4; }
	.menu {
		position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 20; margin: 0; padding: 6px;
		list-style: none; background: var(--surface); border: 1px solid var(--line-strong);
		border-radius: var(--radius-sm); box-shadow: 0 8px 24px rgba(23, 32, 42, 0.12);
	}
	.menu-item {
		width: 100%; text-align: left; padding: 9px 10px; border: 0; border-radius: 8px; background: transparent; font-weight: 500;
	}
	.menu-item:hover { background: var(--field-bg); }
	.menu-item.cur { background: var(--accent-soft); font-weight: 700; }

	nav { display: flex; flex-direction: column; gap: 2px; }
	.group { font-size: 11.5px; font-weight: 600; color: var(--muted); padding: 14px 10px 4px; letter-spacing: 0.02em; }
	.item {
		display: flex; align-items: center; gap: 10px; padding: 5px 10px; min-height: 42px;
		border-radius: var(--radius-sm); color: var(--ink-2); font-weight: 500;
	}
	.item:hover { background: var(--field-bg); }
	.item.on { background: var(--accent-soft); color: var(--ink); font-weight: 700; }
	.label { flex-grow: 1; }
	.count {
		min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; background: var(--danger); color: #fff;
		font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;
	}

	.bottom { margin-top: auto; display: flex; flex-direction: column; gap: 6px; }
	.user { display: flex; align-items: center; gap: 10px; padding: 10px; border-top: 1px solid var(--line); }
	.me {
		width: 34px; height: 34px; border-radius: 50%; background: #ffebd0; color: #a04a07;
		display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;
	}
	.who { line-height: 1.3; flex-grow: 1; min-width: 0; }
	.name { font-size: 13.5px; font-weight: 600; }
	.role { font-size: 12px; color: var(--muted); }
	.out { border: 0; background: transparent; color: var(--muted); padding: 8px; border-radius: 8px; display: flex; }
	.out:hover { background: var(--field-bg); color: var(--ink); }
</style>
