import { getSampleCounts } from '$lib/api';
import { session } from '$lib/session.svelte';

// 샘플 데이터 관리는 교회 관리자만 한다.
export const load = async () => ({ counts: session.user?.isOwner ? await getSampleCounts() : null });
