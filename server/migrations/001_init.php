<?php
declare(strict_types=1);

// 첫 번째 데이터베이스 구조. {p} 는 테이블 접두어(기본 dm_)로 바뀐다.
// 이미 설치된 곳을 바꿀 때는 이 파일을 고치지 말고 002_..., 003_... 파일을 새로 만든다.
//
// is_sample: 1 이면 체험용 샘플 데이터. 설정의 "샘플 데이터 삭제"가 이 표시가 있는 행만 지운다.

$ENGINE = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

return [
    "CREATE TABLE `{p}settings` (
        k VARCHAR(64) NOT NULL PRIMARY KEY,
        v MEDIUMTEXT NULL
    ) $ENGINE",

    "CREATE TABLE `{p}users` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        login_id VARCHAR(50) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(50) NOT NULL,
        phone VARCHAR(20) NULL,
        email VARCHAR(100) NULL,
        is_owner TINYINT(1) NOT NULL DEFAULT 0,
        status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
        must_change_password TINYINT(1) NOT NULL DEFAULT 0,
        last_login_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_login (login_id)
    ) $ENGINE",

    "CREATE TABLE `{p}departments` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        pastor_name VARCHAR(50) NOT NULL DEFAULT '',
        head_name VARCHAR(50) NOT NULL DEFAULT '',
        terms MEDIUMTEXT NULL,
        sort INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_sample TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) $ENGINE",

    "CREATE TABLE `{p}grade_levels` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        label VARCHAR(30) NOT NULL,
        tone VARCHAR(12) NULL,
        sort INT NOT NULL DEFAULT 0,
        next_id INT UNSIGNED NULL,
        is_graduate TINYINT(1) NOT NULL DEFAULT 0,
        CONSTRAINT fk_{p}gl_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}memberships` (
        user_id INT UNSIGNED NOT NULL,
        department_id INT UNSIGNED NOT NULL,
        role ENUM('admin','teacher','viewer') NOT NULL DEFAULT 'viewer',
        teacher_id INT UNSIGNED NULL,
        PRIMARY KEY (user_id, department_id),
        CONSTRAINT fk_{p}mem_user FOREIGN KEY (user_id) REFERENCES `{p}users`(id) ON DELETE CASCADE,
        CONSTRAINT fk_{p}mem_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}invites` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(32) NOT NULL,
        department_id INT UNSIGNED NULL,
        role ENUM('admin','teacher','viewer') NOT NULL DEFAULT 'teacher',
        expires_at DATETIME NULL,
        max_uses INT NOT NULL DEFAULT 1,
        used_count INT NOT NULL DEFAULT 0,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_code (code),
        CONSTRAINT fk_{p}inv_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}login_attempts` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        login_id VARCHAR(50) NULL,
        attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip, attempted_at),
        INDEX idx_login_time (login_id, attempted_at)
    ) $ENGINE",

    "CREATE TABLE `{p}audit_log` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        action VARCHAR(40) NOT NULL,
        target_type VARCHAR(30) NULL,
        target_id INT UNSIGNED NULL,
        detail VARCHAR(500) NULL,
        ip VARCHAR(45) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at)
    ) $ENGINE",

    "CREATE TABLE `{p}classes` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        year SMALLINT UNSIGNED NOT NULL,
        name VARCHAR(40) NOT NULL,
        tone VARCHAR(12) NOT NULL DEFAULT 'blue',
        sort INT NOT NULL DEFAULT 0,
        is_sample TINYINT(1) NOT NULL DEFAULT 0,
        CONSTRAINT fk_{p}cls_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}students` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        name VARCHAR(50) NOT NULL,
        gender ENUM('남','여') NULL,
        birth_date DATE NULL,
        phone VARCHAR(20) NOT NULL DEFAULT '',
        school VARCHAR(50) NOT NULL DEFAULT '',
        grade_level_id INT UNSIGNED NOT NULL,
        class_id INT UNSIGNED NULL,
        status ENUM('active','new','inactive') NOT NULL DEFAULT 'active',
        baptism VARCHAR(20) NOT NULL DEFAULT '-',
        mentor VARCHAR(50) NOT NULL DEFAULT '',
        address VARCHAR(255) NOT NULL DEFAULT '',
        notes TEXT NULL,
        photo_path VARCHAR(255) NULL,
        is_sample TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_dept_grade (department_id, grade_level_id, class_id, name),
        CONSTRAINT fk_{p}stu_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}guardians` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id INT UNSIGNED NOT NULL,
        relation VARCHAR(20) NOT NULL,
        name VARCHAR(50) NOT NULL,
        phone VARCHAR(20) NOT NULL DEFAULT '',
        CONSTRAINT fk_{p}gd_stu FOREIGN KEY (student_id) REFERENCES `{p}students`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}teachers` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        name VARCHAR(50) NOT NULL,
        position VARCHAR(20) NOT NULL DEFAULT '',
        officer_role VARCHAR(20) NOT NULL DEFAULT '',
        birth_date DATE NULL,
        phone VARCHAR(20) NOT NULL DEFAULT '',
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        notes TEXT NULL,
        is_sample TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dept (department_id, name),
        CONSTRAINT fk_{p}tch_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}class_teachers` (
        class_id INT UNSIGNED NOT NULL,
        teacher_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (class_id, teacher_id),
        CONSTRAINT fk_{p}ct_cls FOREIGN KEY (class_id) REFERENCES `{p}classes`(id) ON DELETE CASCADE,
        CONSTRAINT fk_{p}ct_tch FOREIGN KEY (teacher_id) REFERENCES `{p}teachers`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}attendance` (
        student_id INT UNSIGNED NOT NULL,
        attend_date DATE NOT NULL,
        status ENUM('present','online','absent') NOT NULL,
        checked_by INT UNSIGNED NULL,
        PRIMARY KEY (student_id, attend_date),
        INDEX idx_date (attend_date),
        CONSTRAINT fk_{p}att_stu FOREIGN KEY (student_id) REFERENCES `{p}students`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}teacher_attendance` (
        teacher_id INT UNSIGNED NOT NULL,
        attend_date DATE NOT NULL,
        status ENUM('present','online','absent') NOT NULL,
        PRIMARY KEY (teacher_id, attend_date),
        INDEX idx_date (attend_date),
        CONSTRAINT fk_{p}tatt_tch FOREIGN KEY (teacher_id) REFERENCES `{p}teachers`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}visits` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        visit_date DATE NOT NULL,
        method VARCHAR(20) NOT NULL,
        companions VARCHAR(200) NOT NULL DEFAULT '',
        title VARCHAR(200) NOT NULL,
        content TEXT NULL,
        follow_up VARCHAR(255) NOT NULL DEFAULT '',
        follow_up_due DATE NULL,
        follow_up_done TINYINT(1) NOT NULL DEFAULT 0,
        visibility ENUM('private','pastors','staff') NOT NULL DEFAULT 'pastors',
        author_user_id INT UNSIGNED NULL,
        author_name VARCHAR(50) NOT NULL DEFAULT '',
        is_sample TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dept_date (department_id, visit_date),
        CONSTRAINT fk_{p}vis_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}visit_targets` (
        visit_id INT UNSIGNED NOT NULL,
        target_type ENUM('student','teacher') NOT NULL,
        target_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (visit_id, target_type, target_id),
        INDEX idx_target (target_type, target_id),
        CONSTRAINT fk_{p}vt_vis FOREIGN KEY (visit_id) REFERENCES `{p}visits`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}events` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        event_date DATE NOT NULL,
        end_date DATE NULL,
        kind ENUM('ministry','birthday','visit','due','meeting') NOT NULL DEFAULT 'ministry',
        title VARCHAR(200) NOT NULL,
        prayer VARCHAR(100) NOT NULL DEFAULT '',
        notes VARCHAR(255) NOT NULL DEFAULT '',
        is_sample TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_dept_date (department_id, event_date),
        CONSTRAINT fk_{p}ev_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}notices` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NULL,
        title VARCHAR(200) NOT NULL,
        body TEXT NULL,
        pinned TINYINT(1) NOT NULL DEFAULT 0,
        publish_date DATE NOT NULL,
        author_user_id INT UNSIGNED NULL,
        is_sample TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dept (department_id, publish_date),
        CONSTRAINT fk_{p}nt_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}meetings` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        meeting_date DATE NOT NULL,
        title VARCHAR(200) NOT NULL,
        content MEDIUMTEXT NULL,
        attendees VARCHAR(300) NOT NULL DEFAULT '',
        author_user_id INT UNSIGNED NULL,
        is_sample TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dept_date (department_id, meeting_date),
        CONSTRAINT fk_{p}mt_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}ministry_plans` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id INT UNSIGNED NOT NULL,
        year SMALLINT UNSIGNED NOT NULL,
        theme VARCHAR(200) NOT NULL DEFAULT '',
        theme_verse VARCHAR(200) NOT NULL DEFAULT '',
        vision TEXT NULL,
        goals TEXT NULL,
        status ENUM('draft','final') NOT NULL DEFAULT 'draft',
        is_sample TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_dept_year (department_id, year),
        CONSTRAINT fk_{p}mp_dept FOREIGN KEY (department_id) REFERENCES `{p}departments`(id) ON DELETE CASCADE
    ) $ENGINE",

    "CREATE TABLE `{p}ministry_plan_items` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        plan_id INT UNSIGNED NOT NULL,
        area VARCHAR(20) NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT NULL,
        period VARCHAR(60) NOT NULL DEFAULT '',
        owner VARCHAR(60) NOT NULL DEFAULT '',
        budget INT NOT NULL DEFAULT 0,
        status ENUM('todo','doing','done') NOT NULL DEFAULT 'todo',
        sort INT NOT NULL DEFAULT 0,
        CONSTRAINT fk_{p}mpi_plan FOREIGN KEY (plan_id) REFERENCES `{p}ministry_plans`(id) ON DELETE CASCADE
    ) $ENGINE",
];
