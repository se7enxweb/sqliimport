-- 7x SQLI Import: the tables sqliimport_definition, sqliimport_item, sqliimport_mapping, sqliimport_mapping_field, sqliimport_notice, sqliimport_run, sqliimport_run_log, sqliimport_run_row, sqliimport_schedule, sqliimport_scheduled (oracle).
-- Generated from expImportSchema::definition() by ./console ext:sqliimport:install-schema --write-files with the
-- kernel's schema handler; ./console ext:sqliimport:install-schema creates the same tables in a running installation.

BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_definition';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_definition (
  behaviour_json CLOB,
  created INTEGER DEFAULT 0 NOT NULL,
  creator_id INTEGER DEFAULT 0 NOT NULL,
  def_version INTEGER DEFAULT 0 NOT NULL,
  description CLOB,
  handler VARCHAR2(100) DEFAULT 'mapping',
  id INTEGER NOT NULL,
  identifier VARCHAR2(100),
  is_enabled INTEGER DEFAULT 0 NOT NULL,
  legacy_scheduled_id INTEGER DEFAULT 0 NOT NULL,
  mapping_id INTEGER DEFAULT 0 NOT NULL,
  modified INTEGER DEFAULT 0 NOT NULL,
  modifier_id INTEGER DEFAULT 0 NOT NULL,
  name VARCHAR2(255),
  options_json CLOB,
  source_config CLOB,
  source_type VARCHAR2(40),
  target_json CLOB,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_definition_id_tr
BEFORE INSERT ON sqliimport_definition FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_definition.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX sqliimport_def_handler ON sqliimport_definition ( handler );
CREATE UNIQUE INDEX sqliimport_def_identifier ON sqliimport_definition ( identifier );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_item';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_item (
  handler VARCHAR2(50),
  id INTEGER NOT NULL,
  options_serialized CLOB,
  percentage_int INTEGER DEFAULT 0,
  process_time INTEGER DEFAULT 0,
  progression_notes CLOB,
  requested_time INTEGER DEFAULT 0,
  scheduled_id INTEGER,
  status INTEGER DEFAULT 0,
  type INTEGER DEFAULT 1,
  user_id INTEGER,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_item_id_tr
BEFORE INSERT ON sqliimport_item FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_item.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX import_item_handler ON sqliimport_item ( handler );
CREATE INDEX import_item_status ON sqliimport_item ( status );
CREATE INDEX import_item_user ON sqliimport_item ( user_id );
CREATE INDEX import_scheduled_id ON sqliimport_item ( scheduled_id );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_mapping';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_mapping (
  class_identifier VARCHAR2(100),
  created INTEGER DEFAULT 0 NOT NULL,
  id INTEGER NOT NULL,
  import_id INTEGER DEFAULT 0 NOT NULL,
  language VARCHAR2(20),
  location_json CLOB,
  match_key VARCHAR2(255),
  modified INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_mapping_id_tr
BEFORE INSERT ON sqliimport_mapping FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_mapping.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX sqliimport_map_import ON sqliimport_mapping ( import_id );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_mapping_field';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_mapping_field (
  attribute VARCHAR2(100),
  default_value CLOB,
  id INTEGER NOT NULL,
  is_required INTEGER DEFAULT 0 NOT NULL,
  language VARCHAR2(20),
  mapping_id INTEGER DEFAULT 0 NOT NULL,
  sort_order INTEGER DEFAULT 0 NOT NULL,
  source_path VARCHAR2(255),
  transforms_json CLOB,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_mapping_field_id_tr
BEFORE INSERT ON sqliimport_mapping_field FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_mapping_field.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX sqliimport_mapf_mapping ON sqliimport_mapping_field ( mapping_id, sort_order );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_notice';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_notice (
  created INTEGER DEFAULT 0 NOT NULL,
  id INTEGER NOT NULL,
  import_id INTEGER DEFAULT 0 NOT NULL,
  kind VARCHAR2(20),
  run_id INTEGER DEFAULT 0 NOT NULL,
  seen INTEGER DEFAULT 0 NOT NULL,
  user_id INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_notice_id_tr
BEFORE INSERT ON sqliimport_notice FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_notice.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX sqliimport_notice_run ON sqliimport_notice ( run_id );
CREATE INDEX sqliimport_notice_user ON sqliimport_notice ( user_id, seen, id );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_run';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_run (
  created_count INTEGER DEFAULT 0 NOT NULL,
  cursor_json CLOB,
  def_version INTEGER DEFAULT 0 NOT NULL,
  failed_count INTEGER DEFAULT 0 NOT NULL,
  finished_at INTEGER DEFAULT 0 NOT NULL,
  id INTEGER NOT NULL,
  import_id INTEGER DEFAULT 0 NOT NULL,
  legacy_item_id INTEGER DEFAULT 0 NOT NULL,
  memory_peak INTEGER DEFAULT 0 NOT NULL,
  parent_run_id INTEGER DEFAULT 0 NOT NULL,
  processed_count INTEGER DEFAULT 0 NOT NULL,
  queue_group VARCHAR2(40),
  queue_job VARCHAR2(40),
  queued_at INTEGER DEFAULT 0 NOT NULL,
  read_count INTEGER DEFAULT 0 NOT NULL,
  run_kind VARCHAR2(20) DEFAULT 'import',
  schedule_id INTEGER DEFAULT 0 NOT NULL,
  skipped_count INTEGER DEFAULT 0 NOT NULL,
  source_ref VARCHAR2(255),
  started_at INTEGER DEFAULT 0 NOT NULL,
  status VARCHAR2(30) DEFAULT 'queued',
  summary CLOB,
  total_count INTEGER DEFAULT 0 NOT NULL,
  trigger_kind VARCHAR2(20) DEFAULT 'manual',
  unchanged_count INTEGER DEFAULT 0 NOT NULL,
  undone_count INTEGER DEFAULT 0 NOT NULL,
  updated_count INTEGER DEFAULT 0 NOT NULL,
  user_id INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_run_id_tr
BEFORE INSERT ON sqliimport_run FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_run.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX sqliimport_run_import ON sqliimport_run ( import_id, id );
CREATE INDEX sqliimport_run_status ON sqliimport_run ( status );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_run_log';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_run_log (
  context_json CLOB,
  created INTEGER DEFAULT 0 NOT NULL,
  id INTEGER NOT NULL,
  message CLOB,
  row_ref VARCHAR2(255),
  run_id INTEGER DEFAULT 0 NOT NULL,
  severity VARCHAR2(10) DEFAULT 'info',
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_run_log_id_tr
BEFORE INSERT ON sqliimport_run_log FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_run_log.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX sqliimport_log_run ON sqliimport_run_log ( run_id, id );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_run_row';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_run_row (
  created INTEGER DEFAULT 0 NOT NULL,
  data_json CLOB,
  id INTEGER NOT NULL,
  message CLOB,
  nodes_json CLOB,
  object_id INTEGER DEFAULT 0 NOT NULL,
  outcome VARCHAR2(20),
  remote_id VARCHAR2(100),
  row_index INTEGER DEFAULT 0 NOT NULL,
  row_ref VARCHAR2(255),
  run_id INTEGER DEFAULT 0 NOT NULL,
  status VARCHAR2(20),
  version_after INTEGER DEFAULT 0 NOT NULL,
  version_before INTEGER DEFAULT 0 NOT NULL,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_run_row_id_tr
BEFORE INSERT ON sqliimport_run_row FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_run_row.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX sqliimport_row_object ON sqliimport_run_row ( object_id );
CREATE INDEX sqliimport_row_outcome ON sqliimport_run_row ( run_id, outcome );
CREATE UNIQUE INDEX sqliimport_row_ref ON sqliimport_run_row ( run_id, row_ref );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_schedule';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_schedule (
  catch_up VARCHAR2(20) DEFAULT 'one',
  created INTEGER DEFAULT 0 NOT NULL,
  creator_id INTEGER DEFAULT 0 NOT NULL,
  cron_expr VARCHAR2(100),
  id INTEGER NOT NULL,
  import_id INTEGER DEFAULT 0 NOT NULL,
  interval_minutes INTEGER DEFAULT 0 NOT NULL,
  is_active INTEGER DEFAULT 0 NOT NULL,
  kind VARCHAR2(20) DEFAULT 'once',
  last_run INTEGER DEFAULT 0 NOT NULL,
  modified INTEGER DEFAULT 0 NOT NULL,
  next_run INTEGER DEFAULT 0 NOT NULL,
  notify_json CLOB,
  run_at INTEGER DEFAULT 0 NOT NULL,
  timezone VARCHAR2(64) DEFAULT 'UTC',
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_schedule_id_tr
BEFORE INSERT ON sqliimport_schedule FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_schedule.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX sqliimport_sched_due ON sqliimport_schedule ( is_active, next_run );
CREATE INDEX sqliimport_sched_import ON sqliimport_schedule ( import_id );
BEGIN
  EXECUTE IMMEDIATE 'CREATE SEQUENCE se_sqliimport_scheduled';
EXCEPTION WHEN OTHERS THEN
  IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
CREATE TABLE sqliimport_scheduled (
  frequency VARCHAR2(30),
  handler VARCHAR2(50),
  id INTEGER NOT NULL,
  is_active INTEGER DEFAULT 0,
  label VARCHAR2(255),
  manual_frequency INTEGER DEFAULT 0,
  next INTEGER DEFAULT 0,
  options_serialized CLOB,
  requested_time INTEGER,
  user_id INTEGER,
  PRIMARY KEY ( id )
);

CREATE OR REPLACE TRIGGER sqliimport_scheduled_id_tr
BEFORE INSERT ON sqliimport_scheduled FOR EACH ROW WHEN (new.id IS NULL)
BEGIN
  SELECT se_sqliimport_scheduled.nextval INTO :new.id FROM dual;
END;
/

CREATE INDEX import_handler ON sqliimport_scheduled ( handler );
CREATE INDEX next_import ON sqliimport_scheduled ( next );
