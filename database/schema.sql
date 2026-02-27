-- database/schema.sql (PostgreSQL)

CREATE DATABASE ksg_reports 
    WITH ENCODING 'UTF8'
    LC_COLLATE = 'en_US.UTF-8'
    LC_CTYPE = 'en_US.UTF-8'
    TEMPLATE template0;

\c ksg_reports;

CREATE TABLE users (
    id                  SERIAL PRIMARY KEY,
    name                VARCHAR(150)  NOT NULL,
    email               VARCHAR(200)  NOT NULL UNIQUE,
    password_hash       VARCHAR(255)  NOT NULL,
    campus              VARCHAR(50)   NOT NULL,
    role                VARCHAR(20)   NOT NULL DEFAULT 'staff' CHECK (role IN ('staff', 'hod', 'admin')),
    is_active           SMALLINT      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_campus ON users(campus);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_active ON users(is_active);

CREATE TABLE campus_directors (
    id              SERIAL PRIMARY KEY,
    campus          VARCHAR(50)   NOT NULL UNIQUE,
    director_name   VARCHAR(150)  NOT NULL,
    director_email  VARCHAR(200)  NOT NULL,
    is_active       SMALLINT      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_campus_directors_campus ON campus_directors(campus);

CREATE TABLE reports (
    id                          SERIAL PRIMARY KEY,
    campus                      VARCHAR(50)   NOT NULL,
    department                  VARCHAR(100)  NOT NULL,
    hod_name                    VARCHAR(150)  NOT NULL,
    reporting_week_start        DATE          NOT NULL,
    reporting_week_end          DATE          NOT NULL,
    report_date                 DATE          NOT NULL,
    prepared_by_name            VARCHAR(150)  NOT NULL,
    prepared_by_designation     VARCHAR(150)  NOT NULL,
    prepared_date               DATE,
    reviewed_by_name            VARCHAR(150),
    reviewed_by_designation     VARCHAR(150),
    reviewed_date               DATE,
    created_by                  INTEGER       REFERENCES users(id) ON DELETE SET NULL,
    email_sent                  SMALLINT      NOT NULL DEFAULT 0,
    email_sent_at               TIMESTAMP,
    created_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_reports_campus ON reports(campus);
CREATE INDEX idx_reports_department ON reports(department);
CREATE INDEX idx_reports_week_start ON reports(reporting_week_start);
CREATE INDEX idx_reports_created_at ON reports(created_at);
CREATE INDEX idx_reports_email_sent ON reports(email_sent);

CREATE TABLE report_activities (
    id              SERIAL PRIMARY KEY,
    report_id       INTEGER       NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    item_no         SMALLINT      NOT NULL,
    activity        VARCHAR(500)  NOT NULL,
    status          VARCHAR(50)   NOT NULL,
    action_needed   VARCHAR(500),
    notes           VARCHAR(500)
);

CREATE INDEX idx_report_activities_report ON report_activities(report_id);

CREATE TABLE email_logs (
    id              SERIAL PRIMARY KEY,
    report_id       INTEGER       NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    recipient_email VARCHAR(200)  NOT NULL,
    recipient_name  VARCHAR(150)  NOT NULL,
    subject         VARCHAR(255)  NOT NULL,
    status          VARCHAR(20)   NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
    error_message   TEXT,
    sent_at         TIMESTAMP,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_email_logs_report ON email_logs(report_id);
CREATE INDEX idx_email_logs_status ON email_logs(status);

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_campus_directors_updated_at BEFORE UPDATE ON campus_directors
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_reports_updated_at BEFORE UPDATE ON reports
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

INSERT INTO campus_directors (campus, director_name, director_email, is_active) VALUES
('nairobi',  'Dr. Jane Mwangi',      'director.nairobi@ksg.ac.ke',  1),
('mombasa',  'Eng. Maurice Odida',     'clement.langat@ksg.ac.ke',  1),
('matuga',   'Dr. Grace Odhiambo',   'director.matuga@ksg.ac.ke',   1),
('embu',     'Mr. Peter Kamau',      'director.embu@ksg.ac.ke',     1),
('baringo',  'Ms. Mary Chebet',      'director.baringo@ksg.ac.ke',  1);

INSERT INTO users (name, email, password_hash, campus, role) VALUES
('System Administrator', 'admin@ksg.ac.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nairobi', 'admin');