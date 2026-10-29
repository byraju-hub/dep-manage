/** 명단에서 사람을 지울 때: 되돌릴 수 없으므로 두 번 확인한다. */
export function confirmDeletePerson(name: string, what: string): boolean {
	if (!confirm(`'${name}' ${what}을(를) 삭제할까요?`)) return false;
	return confirm(`정말 삭제할까요?\n\n'${name}'의 출석·심방 기록까지 모두 함께 지워지고 되돌릴 수 없어요.\n잠시 쉬는 경우라면 삭제 대신 상태를 바꾸는 것을 권해요.`);
}
