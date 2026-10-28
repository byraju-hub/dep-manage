<?php
declare(strict_types=1);

// 추가 항목(사용자 정의 항목): 교회마다 다른 항목(카카오톡 등록, 수련회 참석 등)을 부서에서 직접 만들어 학생·교사 정보에 붙인다.
// 값은 학생·교사 행의 custom 칸에 {항목 번호: 값} 모양의 JSON 으로 담는다.

$ENGINE = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

return [
    "CREATE TABLE `{p}custom_fields` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        entity ENUM('student','teacher') NOT NULL DEFAULT 'student',
        label VARCHAR(40) NOT NULL,
        field_type ENUM('text','longtext','bool','select','date','number') NOT NULL DEFAULT 'text',
        options TEXT NULL,
        sort INT NOT NULL DEFAULT 0,
        show_in_list TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_dept_entity (department_id, entity, sort),
        CONSTRAINT fk_{p}cf_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "ALTER TABLE `{p}students` ADD COLUMN custom MEDIUMTEXT NULL",
    "ALTER TABLE `{p}teachers` ADD COLUMN custom MEDIUMTEXT NULL",
];
