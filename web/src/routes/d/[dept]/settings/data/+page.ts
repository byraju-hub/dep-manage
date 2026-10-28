import { getMigrations, getSampleCounts } from '$lib/api';
import { session } from '$lib/session.svelte';

// 샘플 데이터, 백업, 업데이트는 교회 관리자만 한다.
export const load = async () => {
	const owner = !!session.user?.isOwner;
	return {
		counts: owner ? await getSampleCounts() : null,
		migrations: owner ? await getMigrations() : null
	};
};
