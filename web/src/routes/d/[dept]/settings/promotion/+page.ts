import { getPromotions } from '$lib/api';

export const load = async ({ params }) => ({ plan: await getPromotions(Number(params.dept)) });
