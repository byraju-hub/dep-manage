<script lang="ts">
	import { invalidateAll } from '$app/navigation';
	import { ApiError, importStudents, importTeachers } from '../api';
	import { downloadStudentTemplate, downloadTeacherTemplate, readStudentFile, readTeacherFile, type ParseProblem } from '../excel';
	import { terms } from '../terms.svelte';
	import type { Department, ImportResult, StudentImportRow, TeacherImportRow } from '../types';
	import { url } from '../url';
	import Icon from './Icon.svelte';

	let {
		kind,
		dept,
		classes
	}: { kind: 'students' | 'teachers'; dept: Department; classes: Array<{ id: number; name: string }> } = $props();

	const label = $derived(kind === 'students' ? terms.student : terms.teacher);
	const listHref = $derived(url(`/d/${dept.id}/${kind}`));

	let parsed = $state<{ rows: Array<StudentImportRow | TeacherImportRow>; problems: ParseProblem[]; total: number } | null>(null);
	let fileName = $state('');
	let reading = $state(false);
	let uploading = $state(false);
	let error = $state('');
	let result = $state<ImportResult | null>(null);

	async function pick(e: Event) {
		const file = (e.target as HTMLInputElement).files?.[0];
		if (!file) return;
		error = '';
		result = null;
		parsed = null;
		fileName = file.name;
		reading = true;
		try {
			parsed = kind === 'students' ? await readStudentFile(file, dept, classes) : await readTeacherFile(file, dept, classes);
		} catch (err) {
			error = err instanceof Error ? err.message : '파일을 읽지 못했어요.';
		} finally {
			reading = false;
		}
	}

	async function upload() {
		if (!parsed?.rows.length) return;
		uploading = true;
		error = '';
		try {
			result = kind === 'students' ? await importStudents(dept.id, parsed.rows as StudentImportRow[]) : await importTeachers(dept.id, parsed.rows as TeacherImportRow[]);
			parsed = null;
			await invalidateAll();
		} catch (err) {
			error = err instanceof ApiError ? err.message : '올리지 못했어요.';
		} finally {
			uploading = false;
		}
	}

	function template() {
		void (kind === 'students' ? downloadStudentTemplate(dept, classes) : downloadTeacherTemplate(dept, classes));
	}

	const gradeName = (id: number) => dept.gradeLevels.find((g) => g.id === id)?.label ?? '';
</script>

<div class="stack">
	<section class="card step">
		<h2><span class="n">1</span> 양식 받기</h2>
		<p class="muted">엑셀 양식을 내려받아 {label} 정보를 한 줄에 한 사람씩 채워 주세요. 이 부서의 {kind === 'students' ? `${terms.grade}, ${terms.class}` : terms.class} 이름이 목록으로 들어 있어요.</p>
		<div><button class="btn" onclick={template}><Icon name="download" size={18} />{label} 양식 내려받기</button></div>
	</section>

	<section class="card step">
		<h2><span class="n">2</span> 채운 파일 고르기</h2>
		<label class="file">
			<input type="file" accept=".xlsx" onchange={pick} />
			<span class="btn"><Icon name="plus" size={18} />엑셀 파일 고르기 (.xlsx)</span>
			{#if fileName}<span class="muted">{fileName}</span>{/if}
		</label>
		{#if reading}<p class="muted" role="status">파일을 읽는 중…</p>{/if}
		{#if error}<p class="err" role="alert">{error}</p>{/if}

		{#if parsed}
			<div class="sum" role="status">
				읽은 {parsed.total}줄 중 <b class="ok">{parsed.rows.length}줄</b>을 올릴 수 있어요.
				{#if parsed.problems.length}<b class="bad">{parsed.problems.length}줄</b>은 확인이 필요해서 올리지 않아요.{/if}
			</div>

			{#if parsed.problems.length}
				<ul class="problems">
					{#each parsed.problems as p (p.row)}<li><b>{p.row}번째 줄</b>{p.name ? ` (${p.name})` : ''} · {p.message}</li>{/each}
				</ul>
				<p class="muted">엑셀에서 고친 뒤 파일을 다시 고르면 돼요. 올릴 수 있는 줄만 먼저 올려도 돼요.</p>
			{/if}

			{#if parsed.rows.length}
				<div class="scroll">
					<table>
						<caption class="sr-only">올릴 {label} 미리보기</caption>
						<thead>
							<tr>
								<th scope="col">줄</th><th scope="col">이름</th>
								{#if kind === 'students'}<th scope="col">{terms.grade}</th><th scope="col">{terms.class}</th>{:else}<th scope="col">직분</th><th scope="col">임원</th>{/if}
								<th scope="col">생년월일</th><th scope="col">연락처</th>
							</tr>
						</thead>
						<tbody>
							{#each parsed.rows.slice(0, 10) as r (r.row)}
								<tr>
									<td>{r.row}</td><td class="strong">{r.name}</td>
									{#if 'gradeId' in r}
										<td>{gradeName(r.gradeId)}</td><td>{classes.find((c) => c.id === r.classId)?.name ?? '-'}</td>
									{:else}
										<td>{r.position || '-'}</td><td>{r.officerRole || '-'}</td>
									{/if}
									<td>{r.birthDate || '-'}</td><td>{r.phone || '-'}</td>
								</tr>
							{/each}
						</tbody>
					</table>
				</div>
				{#if parsed.rows.length > 10}<p class="muted">… 외 {parsed.rows.length - 10}줄</p>{/if}
				<div><button class="btn btn-primary" onclick={upload} disabled={uploading}>{uploading ? '올리는 중…' : `${parsed.rows.length}명 올리기`}</button></div>
			{/if}
		{/if}
	</section>

	{#if result}
		<section class="card step done" role="status">
			<h2>올리기를 마쳤어요</h2>
			<p><b class="ok">{result.created}명</b>을 새로 넣었어요.</p>
			{#if result.skipped.length}
				<p class="muted">이미 있어서 건너뛴 사람 {result.skipped.length}명: {result.skipped.map((s) => s.name).join(', ')}</p>
			{/if}
			{#if result.errors.length}
				<ul class="problems">
					{#each result.errors as e (e.row)}<li><b>{e.row}번째 줄</b> ({e.name}) · {e.message}</li>{/each}
				</ul>
			{/if}
			<div><a class="btn btn-primary" href={listHref}>{label} 명단으로 가기</a></div>
		</section>
	{/if}
</div>

<style>
	.stack { display: flex; flex-direction: column; gap: 16px; max-width: 860px; }
	.step { padding: 20px 24px; display: flex; flex-direction: column; gap: 12px; }
	h2 { font-size: 17px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
	.n { width: 26px; height: 26px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; }
	.file { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
	.file input { position: absolute; opacity: 0; width: 1px; height: 1px; }
	.file:has(input:focus-visible) .btn { outline: 2px solid var(--accent); outline-offset: 2px; }
	.file .btn { cursor: pointer; }
	.sum { background: var(--accent-soft); border-radius: 12px; padding: 10px 14px; }
	.ok { color: var(--ok-ink); }
	.bad { color: var(--danger-ink); }
	.err { color: var(--danger-ink); font-weight: 700; }
	.problems { margin: 0; padding: 12px 16px 12px 32px; background: #fff5f7; border: 1px solid #f5b8c7; border-radius: 12px; display: flex; flex-direction: column; gap: 4px; font-size: 13.5px; }
	.scroll { overflow-x: auto; }
	table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
	th { text-align: left; font-size: 12.5px; color: var(--ink-2); padding: 6px 8px; font-weight: 600; }
	td { padding: 6px 8px; border-top: 1px solid var(--line-soft); }
	.strong { font-weight: 600; }
	.done { border-color: #b7e4c7; background: #f6fcf8; }
	button:disabled { opacity: 0.6; }
</style>
