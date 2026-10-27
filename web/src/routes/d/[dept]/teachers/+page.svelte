<script lang="ts">
	import { dotted } from '$lib/dates';
	import Avatar from '$lib/components/Avatar.svelte';
	import Icon from '$lib/components/Icon.svelte';
	import { terms } from '$lib/terms.svelte';

	let { data } = $props();
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>{terms.teacher} 명단</h1>
			<p class="sub">활동 {data.teachers.filter((t) => t.status === 'active').length}명</p>
		</div>
		<div class="actions">
			<button class="btn btn-primary"><Icon name="plus" size={18} stroke={2.2} />{terms.teacher} 추가</button>
		</div>
	</header>

	<div class="card tablecard">
		<div class="scroll">
			<table>
				<caption class="sr-only">{terms.teacher} 명단</caption>
				<thead>
					<tr>
						<th scope="col" class="first">이름</th>
						<th scope="col">직분</th>
						<th scope="col">임원</th>
						<th scope="col">담당 {terms.class}</th>
						<th scope="col">생년월일</th>
						<th scope="col">연락처</th>
					</tr>
				</thead>
				<tbody>
					{#each data.teachers as t, i (t.id)}
						<tr>
							<td class="first"><span class="who"><Avatar name={t.name} index={i + 2} /><span class="strong">{t.name}</span></span></td>
							<td class="c2">{t.position}</td>
							<td>{#if t.officerRole}<span class="badge badge-sq tone-violet">{t.officerRole}</span>{:else}<span class="muted">-</span>{/if}</td>
							<td>{#if t.classNames.length}{#each t.classNames as c (c)}<span class="badge badge-sq tone-blue cls">{c}</span>{/each}{:else}<span class="muted">-</span>{/if}</td>
							<td class="c2">{dotted(t.birthDate)}</td>
							<td class="c2 nowrap">{t.phone}</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
	</div>
</div>

<style>
	.sub { font-size: 13.5px; color: var(--muted); }
	.tablecard { overflow: hidden; }
	.scroll { overflow-x: auto; }
	table { width: 100%; min-width: 760px; border-collapse: collapse; font-size: 14px; }
	thead tr { background: var(--ground); text-align: left; color: var(--ink-2); font-size: 12.5px; }
	th { padding: 12px 10px; font-weight: 600; white-space: nowrap; }
	td { padding: 8px 10px; border-top: 1px solid var(--line-soft); }
	.first { padding-left: 20px; }
	.who { display: flex; align-items: center; gap: 12px; }
	.strong { font-weight: 600; }
	.c2 { color: var(--ink-2); }
	.nowrap { white-space: nowrap; }
	.cls { margin-right: 4px; }
</style>
