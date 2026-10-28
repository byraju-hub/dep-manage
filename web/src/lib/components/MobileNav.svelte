<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { buildNav, isActive, type NavItem } from '../nav';
	import { url } from '../url';
	import { can, doLogout } from '../session.svelte';
	import { terms } from '../terms.svelte';
	import type { Department } from '../types';
	import Icon from './Icon.svelte';
	import Tile from './Tile.svelte';

	let { dept, depts, pending, churchName }: { dept: Department; depts: Department[]; pending: number; churchName: string } = $props();

	const nav = $derived(buildNav(terms, pending));
	const all = $derived([...nav.main, ...nav.plan, ...(can(dept.id, 'admin') ? [nav.settings] : [])]);
	const tabs = $derived(nav.main.filter((n) => n.tab));
	let sheet = $state(false);

	const href = (item: NavItem) => url(`/d/${dept.id}${item.href}`);
	const active = (item: NavItem) => isActive(page.url.pathname, dept.id, item);
	const moreActive = $derived(all.some((i) => !i.tab && active(i)));

	async function go(path: string) {
		sheet = false;
		await goto(path);
	}
	async function signOut() {
		sheet = false;
		await doLogout();
	}
</script>

<header class="top">
	<div>
		<div class="church">{churchName}</div>
		<div class="dept">{dept.name}</div>
	</div>
</header>

<nav class="tabs" aria-label="주 메뉴">
	{#each tabs as item (item.key)}
		<a href={href(item)} class="tab" class:on={active(item)} aria-current={active(item) ? 'page' : undefined}>
			<span class="ico"><Icon name={item.icon} size={22} stroke={1.9} />{#if item.badge}<i class="dot"></i>{/if}</span>
			<span>{item.key === 'students' ? '명단' : item.label}</span>
		</a>
	{/each}
	<button class="tab" class:on={moreActive || sheet} onclick={() => (sheet = true)} aria-haspopup="dialog">
		<span class="ico"><Icon name="menu" size={22} stroke={1.9} /></span>
		<span>더보기</span>
	</button>
</nav>

{#if sheet}
	<div class="scrim" role="presentation" onclick={() => (sheet = false)}></div>
	<div class="sheet" role="dialog" aria-modal="true" aria-label="더보기">
		<div class="grab"></div>
		<div class="sec">부서 전환</div>
		<div class="chips">
			{#each depts as d (d.id)}
				<button class="chip" class:on={d.id === dept.id} onclick={() => go(url(`/d/${d.id}`))}>{d.name}</button>
			{/each}
		</div>
		<div class="sec">메뉴</div>
		<div class="grid">
			{#each all as item (item.key)}
				<a href={href(item)} class="cell" onclick={() => (sheet = false)}>
					<Tile icon={item.icon} tone={item.tone} size={40} iconSize={22} />
					<span>{item.label}</span>
				</a>
			{/each}
			<button class="cell" onclick={signOut}>
				<Tile icon="logout" tone="slate" size={40} iconSize={22} />
				<span>로그아웃</span>
			</button>
		</div>
	</div>
{/if}

<style>
	.top {
		display: flex; align-items: center; justify-content: space-between;
		padding: 12px 16px; background: var(--surface); border-bottom: 1px solid var(--line);
		position: sticky; top: 0; z-index: 10;
	}
	.church { font-size: 12px; color: var(--muted); line-height: 1.2; }
	.dept { font-family: var(--font-title); font-size: 18px; font-weight: 700; line-height: 1.35; }

	.tabs, .sheet { font-family: var(--font-menu); }
	.tabs {
		position: fixed; left: 0; right: 0; bottom: 0; z-index: 30;
		display: grid; grid-template-columns: repeat(5, 1fr);
		background: var(--surface); border-top: 1px solid var(--line);
		padding: 6px 4px calc(10px + env(safe-area-inset-bottom));
	}
	.tab {
		display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 6px 0; min-height: 44px;
		font-size: 11.5px; font-weight: 600; color: var(--muted); background: transparent; border: 0;
	}
	.tab.on { color: var(--accent); }
	.ico { position: relative; display: flex; }
	.dot { position: absolute; right: -4px; top: -2px; width: 9px; height: 9px; border-radius: 50%; background: var(--danger); border: 2px solid var(--surface); }

	.scrim { position: fixed; inset: 0; background: rgba(23, 32, 42, 0.4); z-index: 40; }
	.sheet {
		position: fixed; left: 0; right: 0; bottom: 0; z-index: 50; background: var(--surface);
		border-radius: 20px 20px 0 0; padding: 10px 16px calc(20px + env(safe-area-inset-bottom)); max-height: 80vh; overflow-y: auto;
	}
	.grab { width: 40px; height: 4px; border-radius: 2px; background: var(--line-strong); margin: 0 auto 12px; }
	.sec { font-size: 12.5px; font-weight: 700; color: var(--muted); margin: 8px 0; }
	.chips { display: flex; gap: 8px; flex-wrap: wrap; }
	.chip {
		height: 40px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--line-strong);
		background: var(--surface); font-weight: 600; color: var(--ink-2);
	}
	.chip.on { background: var(--accent); border-color: transparent; color: #fff; }
	.grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
	.cell {
		display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 12px 4px;
		border-radius: var(--radius-sm); border: 0; background: transparent; font-size: 13px; font-weight: 600; color: var(--ink-2);
	}
	.cell:hover { background: var(--field-bg); }
</style>
