<?php
declare(strict_types=1);

// 추가 항목에 '음력 날짜' 종류를 더하고, 모든 부서에 알레르기·건강 유의사항과 사진·개인정보 이용 동의 항목을 넣는다.
// (이미 같은 이름의 항목이 있는 부서는 건너뛴다. 새 부서는 만들 때 같은 항목이 함께 만들어진다.)

$consent = '["동의","일부 동의","미동의","확인 전"]';

return [
    "ALTER TABLE `{p}custom_fields` MODIFY field_type ENUM('text','longtext','bool','select','date','number','lunar') NOT NULL DEFAULT 'text'",

    "INSERT INTO `{p}custom_fields` (department_id, entity, label, field_type, options, sort, show_in_list)
     SELECT d.id, 'student', '알레르기 · 건강 유의사항', 'longtext', NULL,
            (SELECT COUNT(*) FROM `{p}custom_fields` c WHERE c.department_id = d.id AND c.entity = 'student'), 0
     FROM `{p}departments` d
     WHERE NOT EXISTS (SELECT 1 FROM `{p}custom_fields` x WHERE x.department_id = d.id AND x.entity = 'student' AND x.label = '알레르기 · 건강 유의사항')",

    "INSERT INTO `{p}custom_fields` (department_id, entity, label, field_type, options, sort, show_in_list)
     SELECT d.id, 'student', '사진 · 개인정보 이용 동의', 'select', '$consent',
            (SELECT COUNT(*) FROM `{p}custom_fields` c WHERE c.department_id = d.id AND c.entity = 'student'), 0
     FROM `{p}departments` d
     WHERE NOT EXISTS (SELECT 1 FROM `{p}custom_fields` x WHERE x.department_id = d.id AND x.entity = 'student' AND x.label = '사진 · 개인정보 이용 동의')",
];
