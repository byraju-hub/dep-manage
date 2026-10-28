<script lang="ts">
	// 전화번호를 누르면 '전화 걸기 / 문자 보내기' 를 고를 수 있다. (휴대폰에서 바로 앱이 열린다)
	let { phone, name = '' }: { phone: string; name?: string } = $props();

	let open = $state(false);
	const digits = $derived(phone.replace(/[^0-9+]/g, ''));
</script>

{#if digits}
	<button type="button" class="num" onclick={() => (open = true)}>{phone}</button>
	{#if open}
		<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
		<div class="back" onclick={() => (open = false)}>
			<div class="sheet" role="dialog" aria-label="연락하기" onclick={(e) => e.stopPropagation()}>
				<p class="who">{name ? `${name} · ` : ''}{phone}</p>
				<a class="act call" href="tel:{digits}" onclick={() => (open = false)}>전화 걸기</a>
				<a class="act" href="sms:{digits}" onclick={() => (open = false)}>문자 보내기</a>
				<button type="button" class="act cancel" onclick={() => (open = false)}>닫기</button>
			</div>
		</div>
	{/if}
{/if}

<style>
	.num { background: none; border: 0; padding: 0; font: inherit; color: var(--accent); text-decoration: underline; text-underline-offset: 3px; cursor: pointer; white-space: nowrap; }
	.back { position: fixed; inset: 0; z-index: 100; background: rgba(16, 24, 40, 0.45); display: flex; align-items: flex-end; justify-content: center; }
	.sheet { width: 100%; max-width: 420px; background: var(--surface); border-radius: 18px 18px 0 0; padding: 16px 16px calc(20px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; gap: 8px; }
	.who { text-align: center; font-weight: 700; color: var(--ink-2); padding-bottom: 4px; }
	.act { display: flex; align-items: center; justify-content: center; height: 48px; border-radius: var(--radius-sm); border: 1px solid var(--line-strong); background: var(--surface); font-size: 16px; font-weight: 700; color: var(--ink); }
	.act.call { background: var(--accent); border-color: var(--accent); color: #fff; }
	.act.cancel { color: var(--muted); font-weight: 600; }
	@media (min-width: 901px) { .back { align-items: center; } .sheet { border-radius: 18px; } }
</style>
