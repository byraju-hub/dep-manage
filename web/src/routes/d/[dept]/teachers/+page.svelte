<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, deleteTeacher, photoUrl } from '$lib/api';
	import { confirmDeletePerson } from '$lib/confirm';
import { dotted } from '$lib/dates';
	import Avatar from '$lib/components/Avatar.svelte';
	import ExcelMenu from '$lib/components/ExcelMenu.svelte';
import Icon from '$lib/components/Icon.svelte';
	import PhoneLink from '$lib/components/PhoneLink.svelte';
	import { can } from '$lib/session.svelte';
import { terms } from '$lib/terms.svelte';
import { url } from '$lib/url';

	let { data } = $props();

	async function removeTeacher(id: number, name: string) {
		if (!confirmDeletePerson(name, terms.teacher)) return;
		try {
			await deleteTeacher(id);
			await invalidateAll();
		} catch (e) {
			alert(e instanceof ApiError ? e.message : '삭제하지 못했어요.');
		}
	}
</script>

<div class="page">
	<header class="page-head">
		<div>
			<h1>{terms.teacher} 명단</h1>
			<p class="sub">활동 {data.teachers.filter((t) => t.status === 'active').length}명</p>
		</div>
		<div class="actions">
			<ExcelMenu kind="teachers" dept={data.dept} teachers={data.teachers} fields={data.fields.filter((f) => f.entity === 'teacher')} />
			{#if can(data.dept.id, 'admin')}
				<a class="btn btn-primary" href={url(`/d/${data.dept.id}/teachers/new`)}><Icon name="plus" size={18} stroke={2.2} />{terms.teacher} 추가</a>
			{/if}
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
						<th scope="col" class="end"><span class="sr-only">수정·삭제</span></th>
					</tr>
				</thead>
				<tbody>
					{#each data.teachers as t, i (t.id)}
						<tr>
							<td class="first"><a class="who" href={url(`/d/${data.dept.id}/teachers/${t.id}`)}><Avatar name={t.name} index={i + 2} src={photoUrl('teacher', t.id, t.photo, true)} /><span class="strong">{t.name}</span></a></td>
							<td class="c2">{t.position}</td>
							<td>{#if t.officerRole}<span class="badge badge-sq tone-violet">{t.officerRole}</span>{:else}<span class="muted">-</span>{/if}</td>
							<td>{#if t.classNames.length}{#each t.classNames as c (c)}<span class="badge badge-sq tone-blue cls">{c}</span>{/each}{:else}<span class="muted">-</span>{/if}</td>
							<td class="c2">{dotted(t.birthDate)}</td>
							<td class="c2 nowrap"><PhoneLink phone={t.phone} name={t.name} /></td>
							<td class="end">
								{#if can(data.dept.id, 'admin')}
									<span class="acts">
										<a class="ico" href={url(`/d/${data.dept.id}/teachers/${t.id}`)} aria-label="{t.name} 수정" title="수정"><Icon name="edit" size={17} /></a>
										<button class="ico del" onclick={() => removeTeacher(t.id, t.name)} aria-label="{t.name} 삭제" title="삭제"><Icon name="trash" size={17} /></button>
									</span>
								{/if}
							</td>
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
	.end { padding-right: 16px; text-align: right; white-space: nowrap; }
	.acts { display: inline-flex; gap: 4px; }
	.ico { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line-strong); border-radius: 10px; background: var(--surface); color: var(--ink-2); }
	.ico:hover { background: var(--field-bg); }
	.ico.del { color: var(--danger-ink); }
</style>
