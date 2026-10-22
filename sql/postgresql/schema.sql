-- 7x SQLI Import: the tables sqliimport_definition, sqliimport_item, sqliimport_mapping, sqliimport_mapping_field, sqliimport_run, sqliimport_run_log, sqliimport_run_row, sqliimport_schedule, sqliimport_scheduled (postgresql).
-- Generated from expImportSchema::definition() by ./console ext:sqliimport:install-schema --write-files with the
-- kernel's schema handler; ./console ext:sqliimport:install-schema creates the same tables in a running installation.

CREATE SEQUENCE IF NOT EXISTS sqliimport_definition_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS sqliimport_definition (
  behaviour_json text,
  created integer DEFAULT 0 NOT NULL,
  creator_id integer DEFAULT 0 NOT NULL,
  def_version integer DEFAULT 0 NOT NULL,
  description text,
  "handler" character varying(100) DEFAULT 'mapping'::character varying NOT NULL,
  id integer DEFAULT nextval('sqliimport_definition_id_seq'::text) NOT NULL,
  identifier character varying(100) DEFAULT ''::character varying NOT NULL,
  is_enabled integer DEFAULT 0 NOT NULL,
  legacy_scheduled_id integer DEFAULT 0 NOT NULL,
  mapping_id integer DEFAULT 0 NOT NULL,
  modified integer DEFAULT 0 NOT NULL,
  modifier_id integer DEFAULT 0 NOT NULL,
  name character varying(255) DEFAULT ''::character varying NOT NULL,
  options_json text,
  source_config text,
  source_type character varying(40) DEFAULT ''::character varying NOT NULL,
  target_json text
);

CREATE UNIQUE INDEX sqliimport_def_identifier ON sqliimport_definition USING btree ( identifier );
CREATE INDEX sqliimport_def_handler ON sqliimport_definition USING btree ( "handler" );
ALTER TABLE ONLY sqliimport_definition ADD CONSTRAINT sqliimport_definition_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS sqliimport_item_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS sqliimport_item (
  "handler" character varying(50) DEFAULT NULL,
  id integer DEFAULT nextval('sqliimport_item_id_seq'::text) NOT NULL,
  options_serialized text,
  percentage_int smallint DEFAULT '0',
  process_time integer DEFAULT 0,
  progression_notes text,
  requested_time integer DEFAULT 0,
  scheduled_id integer DEFAULT NULL,
  status smallint DEFAULT '0',
  "type" smallint DEFAULT '1',
  user_id integer DEFAULT NULL
);

CREATE INDEX import_item_handler ON sqliimport_item USING btree ( "handler" );
CREATE INDEX import_item_status ON sqliimport_item USING btree ( status );
CREATE INDEX import_item_user ON sqliimport_item USING btree ( user_id );
CREATE INDEX import_scheduled_id ON sqliimport_item USING btree ( scheduled_id );
ALTER TABLE ONLY sqliimport_item ADD CONSTRAINT sqliimport_item_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS sqliimport_mapping_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS sqliimport_mapping (
  class_identifier character varying(100) DEFAULT ''::character varying NOT NULL,
  created integer DEFAULT 0 NOT NULL,
  id integer DEFAULT nextval('sqliimport_mapping_id_seq'::text) NOT NULL,
  import_id integer DEFAULT 0 NOT NULL,
  "language" character varying(20) DEFAULT ''::character varying NOT NULL,
  location_json text,
  match_key character varying(255) DEFAULT ''::character varying NOT NULL,
  modified integer DEFAULT 0 NOT NULL
);

CREATE INDEX sqliimport_map_import ON sqliimport_mapping USING btree ( import_id );
ALTER TABLE ONLY sqliimport_mapping ADD CONSTRAINT sqliimport_mapping_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS sqliimport_mapping_field_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS sqliimport_mapping_field (
  attribute character varying(100) DEFAULT ''::character varying NOT NULL,
  default_value text,
  id integer DEFAULT nextval('sqliimport_mapping_field_id_seq'::text) NOT NULL,
  is_required integer DEFAULT 0 NOT NULL,
  "language" character varying(20) DEFAULT ''::character varying NOT NULL,
  mapping_id integer DEFAULT 0 NOT NULL,
  sort_order integer DEFAULT 0 NOT NULL,
  source_path character varying(255) DEFAULT ''::character varying NOT NULL,
  transforms_json text
);

CREATE INDEX sqliimport_mapf_mapping ON sqliimport_mapping_field USING btree ( mapping_id, sort_order );
ALTER TABLE ONLY sqliimport_mapping_field ADD CONSTRAINT sqliimport_mapping_field_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS sqliimport_run_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS sqliimport_run (
  created_count integer DEFAULT 0 NOT NULL,
  cursor_json text,
  def_version integer DEFAULT 0 NOT NULL,
  failed_count integer DEFAULT 0 NOT NULL,
  finished_at integer DEFAULT 0 NOT NULL,
  id integer DEFAULT nextval('sqliimport_run_id_seq'::text) NOT NULL,
  import_id integer DEFAULT 0 NOT NULL,
  legacy_item_id integer DEFAULT 0 NOT NULL,
  memory_peak integer DEFAULT 0 NOT NULL,
  parent_run_id integer DEFAULT 0 NOT NULL,
  processed_count integer DEFAULT 0 NOT NULL,
  queue_group character varying(40) DEFAULT ''::character varying NOT NULL,
  queue_job character varying(40) DEFAULT ''::character varying NOT NULL,
  queued_at integer DEFAULT 0 NOT NULL,
  read_count integer DEFAULT 0 NOT NULL,
  run_kind character varying(20) DEFAULT 'import'::character varying NOT NULL,
  schedule_id integer DEFAULT 0 NOT NULL,
  skipped_count integer DEFAULT 0 NOT NULL,
  source_ref character varying(255) DEFAULT ''::character varying NOT NULL,
  started_at integer DEFAULT 0 NOT NULL,
  status character varying(30) DEFAULT 'queued'::character varying NOT NULL,
  summary text,
  total_count integer DEFAULT 0 NOT NULL,
  trigger_kind character varying(20) DEFAULT 'manual'::character varying NOT NULL,
  unchanged_count integer DEFAULT 0 NOT NULL,
  updated_count integer DEFAULT 0 NOT NULL,
  user_id integer DEFAULT 0 NOT NULL
);

CREATE INDEX sqliimport_run_import ON sqliimport_run USING btree ( import_id, id );
CREATE INDEX sqliimport_run_status ON sqliimport_run USING btree ( status );
ALTER TABLE ONLY sqliimport_run ADD CONSTRAINT sqliimport_run_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS sqliimport_run_log_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS sqliimport_run_log (
  context_json text,
  created integer DEFAULT 0 NOT NULL,
  id integer DEFAULT nextval('sqliimport_run_log_id_seq'::text) NOT NULL,
  message text,
  row_ref character varying(255) DEFAULT ''::character varying NOT NULL,
  run_id integer DEFAULT 0 NOT NULL,
  severity character varying(10) DEFAULT 'info'::character varying NOT NULL
);

CREATE INDEX sqliimport_log_run ON sqliimport_run_log USING btree ( run_id, id );
ALTER TABLE ONLY sqliimport_run_log ADD CONSTRAINT sqliimport_run_log_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS sqliimport_run_row_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS sqliimport_run_row (
  created integer DEFAULT 0 NOT NULL,
  data_json text,
  id integer DEFAULT nextval('sqliimport_run_row_id_seq'::text) NOT NULL,
  message text,
  nodes_json text,
  object_id integer DEFAULT 0 NOT NULL,
  outcome character varying(20) DEFAULT ''::character varying NOT NULL,
  remote_id character varying(100) DEFAULT ''::character varying NOT NULL,
  row_index integer DEFAULT 0 NOT NULL,
  row_ref character varying(255) DEFAULT ''::character varying NOT NULL,
  run_id integer DEFAULT 0 NOT NULL,
  status character varying(20) DEFAULT ''::character varying NOT NULL,
  version_after integer DEFAULT 0 NOT NULL,
  version_before integer DEFAULT 0 NOT NULL
);

CREATE UNIQUE INDEX sqliimport_row_ref ON sqliimport_run_row USING btree ( run_id, row_ref );
CREATE INDEX sqliimport_row_outcome ON sqliimport_run_row USING btree ( run_id, outcome );
CREATE INDEX sqliimport_row_object ON sqliimport_run_row USING btree ( object_id );
ALTER TABLE ONLY sqliimport_run_row ADD CONSTRAINT sqliimport_run_row_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS sqliimport_schedule_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS sqliimport_schedule (
  catch_up character varying(20) DEFAULT 'one'::character varying NOT NULL,
  created integer DEFAULT 0 NOT NULL,
  creator_id integer DEFAULT 0 NOT NULL,
  cron_expr character varying(100) DEFAULT ''::character varying NOT NULL,
  id integer DEFAULT nextval('sqliimport_schedule_id_seq'::text) NOT NULL,
  import_id integer DEFAULT 0 NOT NULL,
  interval_minutes integer DEFAULT 0 NOT NULL,
  is_active integer DEFAULT 0 NOT NULL,
  kind character varying(20) DEFAULT 'once'::character varying NOT NULL,
  last_run integer DEFAULT 0 NOT NULL,
  modified integer DEFAULT 0 NOT NULL,
  next_run integer DEFAULT 0 NOT NULL,
  notify_json text,
  run_at integer DEFAULT 0 NOT NULL,
  timezone character varying(64) DEFAULT 'UTC'::character varying NOT NULL
);

CREATE INDEX sqliimport_sched_import ON sqliimport_schedule USING btree ( import_id );
CREATE INDEX sqliimport_sched_due ON sqliimport_schedule USING btree ( is_active, next_run );
ALTER TABLE ONLY sqliimport_schedule ADD CONSTRAINT sqliimport_schedule_pkey PRIMARY KEY ( id );
CREATE SEQUENCE IF NOT EXISTS sqliimport_scheduled_id_seq
  START 1
  INCREMENT 1
  MAXVALUE 9223372036854775807
  MINVALUE 1
  CACHE 1;
CREATE TABLE IF NOT EXISTS sqliimport_scheduled (
  frequency character varying(30) DEFAULT ''::character varying NOT NULL,
  "handler" character varying(50) DEFAULT NULL,
  id integer DEFAULT nextval('sqliimport_scheduled_id_seq'::text) NOT NULL,
  is_active smallint DEFAULT '0',
  label character varying(255) DEFAULT NULL,
  manual_frequency integer DEFAULT 0,
  "next" integer DEFAULT 0,
  options_serialized text,
  requested_time integer DEFAULT NULL,
  user_id integer DEFAULT NULL
);

CREATE INDEX import_handler ON sqliimport_scheduled USING btree ( "handler" );
CREATE INDEX next_import ON sqliimport_scheduled USING btree ( "next" );
ALTER TABLE ONLY sqliimport_scheduled ADD CONSTRAINT sqliimport_scheduled_pkey PRIMARY KEY ( id );
