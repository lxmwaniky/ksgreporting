-- KSG Reports System — PostgreSQL Schema

CREATE DATABASE ksg_reports
    WITH ENCODING 'UTF8'
    LC_COLLATE = 'en_US.UTF-8'
    LC_CTYPE = 'en_US.UTF-8'
    TEMPLATE template0;

\c ksg_reports;

CREATE TABLE users (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(150)  NOT NULL,
    email           VARCHAR(200)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NOT NULL,
    campus          VARCHAR(50)   NOT NULL,
    role            VARCHAR(20)   NOT NULL DEFAULT 'staff'
                        CHECK (role IN ('staff', 'hod', 'deputy_director', 'director', 'admin')),
    department      VARCHAR(100),
    hod_name        VARCHAR(150),
    designation     VARCHAR(150),
    is_active       SMALLINT      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_campus     ON users(campus);
CREATE INDEX idx_users_role       ON users(role);
CREATE INDEX idx_users_active     ON users(is_active);
CREATE INDEX idx_users_department ON users(department);

CREATE TABLE campus_directors (
    id              SERIAL PRIMARY KEY,
    campus          VARCHAR(50)   NOT NULL UNIQUE,
    director_name   VARCHAR(150)  NOT NULL,
    director_email  VARCHAR(200)  NOT NULL,
    is_active       SMALLINT      NOT NULL DEFAULT 1,
    user_id         INTEGER       REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_campus_directors_campus ON campus_directors(campus);

CREATE TABLE reports (
    id                      SERIAL PRIMARY KEY,
    report_code             VARCHAR(50),
    campus                  VARCHAR(50)   NOT NULL,
    department              VARCHAR(100)  NOT NULL,
    hod_name                VARCHAR(150)  NOT NULL,
    reporting_week_start    DATE          NOT NULL,
    reporting_week_end      DATE          NOT NULL,
    report_date             DATE          NOT NULL,
    prepared_by_name        VARCHAR(150)  NOT NULL,
    prepared_by_designation VARCHAR(150)  NOT NULL,
    prepared_date           DATE,
    reviewed_by_name        VARCHAR(150),
    reviewed_by_designation VARCHAR(150),
    reviewed_date           DATE,
    created_by              INTEGER       REFERENCES users(id) ON DELETE SET NULL,
    email_sent              SMALLINT      NOT NULL DEFAULT 0,
    email_sent_at           TIMESTAMP,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_reports_campus     ON reports(campus);
CREATE INDEX idx_reports_department ON reports(department);
CREATE INDEX idx_reports_week_start ON reports(reporting_week_start);
CREATE INDEX idx_reports_created_at ON reports(created_at);
CREATE INDEX idx_reports_created_by ON reports(created_by);
CREATE INDEX idx_reports_email_sent ON reports(email_sent);

CREATE TABLE report_activities (
    id            SERIAL PRIMARY KEY,
    report_id     INTEGER       NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    item_no       SMALLINT      NOT NULL,
    activity      VARCHAR(500)  NOT NULL,
    status        VARCHAR(50)   NOT NULL,
    action_needed VARCHAR(500),
    notes         VARCHAR(500)
);

CREATE INDEX idx_report_activities_report ON report_activities(report_id);

CREATE TABLE tasks (
    id               SERIAL PRIMARY KEY,
    title            VARCHAR(255)  NOT NULL,
    description      TEXT,
    assigned_by      INTEGER       REFERENCES users(id) ON DELETE SET NULL,
    assigned_to      INTEGER       REFERENCES users(id) ON DELETE SET NULL,
    campus           VARCHAR(50)   NOT NULL,
    department       VARCHAR(100),
    section          VARCHAR(100),
    deadline         DATE,
    status           VARCHAR(20)   NOT NULL DEFAULT 'pending'
                         CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled', 'overdue')),
    overdue_notified SMALLINT      NOT NULL DEFAULT 0,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_tasks_assigned_to ON tasks(assigned_to);
CREATE INDEX idx_tasks_assigned_by ON tasks(assigned_by);
CREATE INDEX idx_tasks_campus      ON tasks(campus);
CREATE INDEX idx_tasks_status      ON tasks(status);
CREATE INDEX idx_tasks_deadline    ON tasks(deadline);

-- email_logs covers all outbound notifications (reports, tasks, welcome emails).
-- Both report_id and task_id are nullable; only the relevant one is populated per row.
CREATE TABLE email_logs (
    id              SERIAL PRIMARY KEY,
    report_id       INTEGER       REFERENCES reports(id) ON DELETE SET NULL,
    task_id         INTEGER       REFERENCES tasks(id)   ON DELETE SET NULL,
    email_type      VARCHAR(50)   NOT NULL DEFAULT 'report',
    recipient_email VARCHAR(200)  NOT NULL,
    recipient_name  VARCHAR(150)  NOT NULL,
    subject         VARCHAR(255)  NOT NULL,
    status          VARCHAR(20)   NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending', 'sent', 'failed')),
    error_message   TEXT,
    sent_at         TIMESTAMP,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_email_logs_report ON email_logs(report_id);
CREATE INDEX idx_email_logs_task   ON email_logs(task_id);
CREATE INDEX idx_email_logs_status ON email_logs(status);
CREATE INDEX idx_email_logs_type   ON email_logs(email_type);

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_campus_directors_updated_at
    BEFORE UPDATE ON campus_directors
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_reports_updated_at
    BEFORE UPDATE ON reports
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_tasks_updated_at
    BEFORE UPDATE ON tasks
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Default admin account. Change password on first login.
INSERT INTO users (name, email, password_hash, campus, role) VALUES
(
    'System Administrator',
    'admin@ksg.ac.ke',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'nairobi',
    'admin'
);