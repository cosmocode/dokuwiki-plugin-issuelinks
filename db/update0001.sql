CREATE TABLE issues (
    service TEXT NOT NULL CHECK(service <> ''),
    id INTEGER NOT NULL CHECK(id > 0),
    project TEXT NOT NULL CHECK(project <> ''),
    is_mergerequest INTEGER NOT NULL CHECK(is_mergerequest IN (0,1)),
    summary TEXT,
    description TEXT,
    type TEXT,
    status TEXT,
    parent TEXT,
    epic TEXT,
    components TEXT,
    labels TEXT,
    priority TEXT,
    duedate TEXT,
    versions TEXT,
    updated INTEGER,
    PRIMARY KEY (service, id, project, is_mergerequest)
);

CREATE TABLE issue_issues (
    service      TEXT    NOT NULL CHECK (service <> ''),
    id              INTEGER NOT NULL CHECK (id > 0),
    project         TEXT    NOT NULL CHECK (project <> ''),
    is_mergerequest INTEGER NOT NULL CHECK (is_mergerequest IN (0, 1)),
    referenced_service      TEXT    NOT NULL CHECK (referenced_service <> ''),
    referenced_id              INTEGER NOT NULL CHECK (referenced_id > 0),
    referenced_project         TEXT    NOT NULL CHECK (referenced_project <> ''),
    referenced_is_mergerequest INTEGER NOT NULL CHECK (referenced_is_mergerequest IN (0, 1)),
    PRIMARY KEY (service, id, project, is_mergerequest, referenced_service, referenced_id, referenced_project, referenced_is_mergerequest)
);

-- FIXME: What does it do? Do we need it? issues_queue
CREATE TABLE issues_queue (
    service TEXT NOT NULL CHECK(service <> ''),
    project TEXT NOT NULL CHECK(project <> ''),
    id INTEGER NOT NULL CHECK(id > 0),
    is_mergerequest INTEGER NOT NULL CHECK(is_mergerequest IN (0,1)),
    PRIMARY KEY (service, project, id, is_mergerequest)
);

CREATE TABLE pagerev_issues (
    page       TEXT NOT NULL CHECK(page <> ''),
    rev        INTEGER NOT NULL CHECK(rev > 0),
    service TEXT NOT NULL CHECK(service <> ''),
    project_id TEXT NOT NULL CHECK(project_id <> ''),
    issue_id   INTEGER NOT NULL,-- CHECK(issue_id > 0),
    is_mergerequest INTEGER NOT NULL CHECK(is_mergerequest IN (0,1)),
    type       TEXT NOT NULL CHECK(type <> ''),
    PRIMARY KEY (page, rev, service, project_id, issue_id, is_mergerequest, type),
    FOREIGN KEY (service) REFERENCES issues (service),
    FOREIGN KEY (project_id) REFERENCES issues(project),
    FOREIGN KEY (issue_id) REFERENCES issues(id),
    FOREIGN KEY (is_mergerequest) REFERENCES issues(is_mergerequest),
    FOREIGN KEY (page) REFERENCES pagerevs(page),
    FOREIGN KEY (rev) REFERENCES pagerevs(rev)
);

CREATE TABLE webhooks (
    service TEXT NOT NULL CHECK(service <> ''),
    repository_id TEXT NOT NULL CHECK(repository_id <> ''),
    id TEXT NOT NULL CHECK(id <> ''),
    secret TEXT NOT NULL CHECK(secret <> ''),
    PRIMARY KEY (service, repository_id, id)
);

