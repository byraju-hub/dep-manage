<?php
declare(strict_types=1);

// 새가족 정착 단계, 기도 제목, 행사 참가(신청·회비 납부).

$ENGINE = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

return [
    // 새가족이 단계를 마친 날. 단계: welcome(첫 방문 환영) · attend(2주 출석) · visit(심방) · register(정식 등록)
    "CREATE TABLE `{p}newcomer_steps` (
        student_id INT UNSIGNED NOT NULL,
        step VARCHAR(20) NOT NULL,
        done_date DATE NOT NULL,
        done_by VARCHAR(50) NOT NULL DEFAULT '',
        PRIMARY KEY (student_id, step),
        CONSTRAINT fk_{p}ns_stu FOREIGN KEY (student_id) REFERENCES `{p}students`(id) ON DELETE CASCADE
    ) $ENGINE",

    // 학생·교사별 기도 제목. 심방과 별개로 오래 두고 보며 응답을 표시한다.
    "CREATE TABLE `{p}prayer_requests` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        target_type ENUM('student','teacher') NOT NULL,
        target_id INT UNSIGNED NOT NULL,
        content VARCHAR(500) NOT NULL,
        status ENUM('praying','answered') NOT NULL DEFAULT 'praying',
        request_date DATE NOT NULL,
        answered_date DATE NULL,
        author_user_id INT UNSIGNED NULL,
        author_name VARCHAR(50) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dept_status (department_id, status, request_date),
        INDEX idx_target (target_type, target_id),
        CONSTRAINT fk_{p}pr_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    // 연간 계획의 일정(수련회 등)에 참가 신청한 사람과 회비 납부 여부
    "CREATE TABLE `{p}event_participants` (
        event_id INT UNSIGNED NOT NULL,
        person_type ENUM('student','teacher') NOT NULL,
        person_id INT UNSIGNED NOT NULL,
        paid TINYINT(1) NOT NULL DEFAULT 0,
        amount INT UNSIGNED NULL,
        note VARCHAR(100) NOT NULL DEFAULT '',
        PRIMARY KEY (event_id, person_type, person_id),
        INDEX idx_person (person_type, person_id),
        CONSTRAINT fk_{p}ep_ev FOREIGN KEY (event_id) REFERENCES `{p}events`(id) ON DELETE CASCADE
    ) $ENGINE",
];
