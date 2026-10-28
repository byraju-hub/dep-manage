<script lang="ts">
	import { listClasses, type StudentRow, type TeacherRow } from '../api';
	import { downloadStudentList, downloadStudentTemplate, downloadTeacherList, downloadTeacherTemplate } from '../excel';
	import { can } from '../session.svelte';
	import { terms } from '../terms.svelte';
	import type { Department } from '../types';
	import { url } from '../url';
	import Icon from './Icon.svelte';

	let {
		kind,
		dept,
		students = [],
		teachers = []
	}: { kind: 'students' | 'teachers'; dept: Department; students?: StudentRow[]; teachers?: TeacherRow[] } = $props();

	let open = $state(false);
	let busy = $state(false);
	let error = $state('');
	const label = $derived(kind === 'students' ? terms.student : terms.teacher);

	async function run(fn: () => Promise<void>) {
		busy = true;
		error = '';
		try {
			await fn();
			open = false;
		} catch {
			error = '엑셀 파일을 만들지 못했어요.';
		} finally {
			busy = false;
		}
	}

	const classNames = async () => (await listClasses(dept.id)).map((c) => ({ id: c.id, name: c.name }));

	const exportList = () =>
		run(async () => {
			if (kind === 'students') {
				await downloadStudentList(
					dept,
					await classNames(),
					students.map((s) => ({
						name: s.name, gender: s.gender, birthDate: s.birthDate, phone: s.phone, school: s.school, gradeLabel: s.gradeLabel, className: s.className,
						status: s.status, baptism: s.baptism, mentor: s.mentor, address: s.address, guardians: s.guardians, notes: s.notes
					}))
				);
			} else {
				await downloadTeacherList(
					dept,
					teachers.map((t) => ({ name: t.name, position: t.position, officerRole: t.officerRole, birthDate: t.birthDate, phone: t.phone, status: t.status, classNames: t.classNames, notes: t.notes ?? '' }))
				);
			}
		});

	const template = () => run(async () => (kind === 'students' ? downloadStudentTemplate(dept, await classNames()) : downloadTeacherTemplate(dept, await classNames())));
</script>

<div class="wrap">
	<button class="btn" aria-haspopup="menu" aria-expanded={open} onclick={() => (open = !open)}><span class="g"><Icon name="download" size={18} /></span>엑셀</button>
	{#if open}
		<div class="pop" role="menu">
			<button role="menuitem" onclick={exportList} disabled={busy}>현재 {label} 명단 내려받기</button>
			{#if can(dept.id, 'admin')}
				<button role="menuitem" onclick={template} disabled={busy}>{label} 양식 내려받기</button>
				<a role="menuitem" href={url(`/d/${dept.id}/${kind}/import`)}>{label} 명단 엑셀로 올리기</a>
			{/if}
			{#if error}<p class="err" role="alert">{error}</p>{/if}
		</div>
	{/if}
</div>

<style>
	.wrap { position: relative; }
	.g { color: var(--ok-ink); display: inline-flex; }
	.pop {
		position: absolute; right: 0; top: calc(100% + 6px); z-index: 20; min-width: 230px; padding: 6px;
		background: var(--surface); border: 1px solid var(--line-strong); border-radius: var(--radius-sm); box-shadow: 0 8px 24px rgba(23, 32, 42, 0.12);
		display: flex; flex-direction: column;
	}
	.pop button, .pop a { text-align: left; padding: 10px 12px; border: 0; border-radius: 8px; background: transparent; font-weight: 500; color: var(--ink); }
	.pop button:hover, .pop a:hover { background: var(--field-bg); }
	.pop button:disabled { opacity: 0.6; }
	.err { color: var(--danger-ink); font-size: 13px; padding: 4px 12px; }
</style>
