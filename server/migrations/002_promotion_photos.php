<?php
declare(strict_types=1);

// 학년 승급 기록, 교사 사진.

$ENGINE = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

return [
    // 같은 부서에서 같은 해에 승급을 두 번 하지 못하게 기록한다.
    "CREATE TABLE `{p}promotion_log` (
        department_id INT UNSIGNED NOT NULL,
        year SMALLINT UNSIGNED NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        applied_by INT UNSIGNED NULL,
        student_count INT NOT NULL DEFAULT 0,
        PRIMARY KEY (department_id, year),
        CONSTRAINT fk_{p}pl_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "ALTER TABLE `{p}teachers` ADD COLUMN photo_path VARCHAR(255) NULL AFTER notes",
];
