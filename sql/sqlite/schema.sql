-- 7x SQLI Import: the tables sqliimport_definition, sqliimport_item, sqliimport_mapping, sqliimport_mapping_field, sqliimport_run, sqliimport_run_log, sqliimport_run_row, sqliimport_schedule, sqliimport_scheduled (sqlite).
-- Generated from expImportSchema::definition() by ./console ext:sqliimport:install-schema --write-files with the
-- kernel's schema handler; ./console ext:sqliimport:install-schema creates the same tables in a running installation.

CREATE TABLE sqliimport_definition (
  behaviour_json longtext,
  created INTEGER(11) NOT NULL DEFAULT '0',
  creator_id INTEGER(11) NOT NULL DEFAULT '0',
  def_version INTEGER(11) NOT NULL DEFAULT '0',
  description longtext,
  handler varchar(100) NOT NULL DEFAULT 'mapping',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  identifier varchar(100) NOT NULL DEFAULT '',
  is_enabled INTEGER(11) NOT NULL DEFAULT '0',
  legacy_scheduled_id INTEGER(11) NOT NULL DEFAULT '0',
  mapping_id INTEGER(11) NOT NULL DEFAULT '0',
  modified INTEGER(11) NOT NULL DEFAULT '0',
  modifier_id INTEGER(11) NOT NULL DEFAULT '0',
  name varchar(255) NOT NULL DEFAULT '',
  options_json longtext,
  source_config longtext,
  source_type varchar(40) NOT NULL DEFAULT '',
  target_json longtext
);

CREATE  INDEX sqliimport_definition__sqliimport_def_handler ON sqliimport_definition  ( handler );
CREATE  UNIQUE INDEX sqliimport_definition__sqliimport_def_identifier ON sqliimport_definition  ( identifier );
CREATE TABLE sqliimport_item (
  handler varchar(50) DEFAULT NULL,
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  options_serialized longtext,
  percentage_int smallint(6) DEFAULT '0',
  process_time INTEGER(11) DEFAULT '0',
  progression_notes longtext,
  requested_time INTEGER(11) DEFAULT '0',
  scheduled_id INTEGER(11) DEFAULT NULL,
  status tinyint(4) DEFAULT '0',
  type tinyint(4) DEFAULT '1',
  user_id INTEGER(11) DEFAULT NULL
);

CREATE  INDEX sqliimport_item__import_item_handler ON sqliimport_item  ( handler );
CREATE  INDEX sqliimport_item__import_item_status ON sqliimport_item  ( status );
CREATE  INDEX sqliimport_item__import_item_user ON sqliimport_item  ( user_id );
CREATE  INDEX sqliimport_item__import_scheduled_id ON sqliimport_item  ( scheduled_id );
CREATE TABLE sqliimport_mapping (
  class_identifier varchar(100) NOT NULL DEFAULT '',
  created INTEGER(11) NOT NULL DEFAULT '0',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  import_id INTEGER(11) NOT NULL DEFAULT '0',
  language varchar(20) NOT NULL DEFAULT '',
  location_json longtext,
  match_key varchar(255) NOT NULL DEFAULT '',
  modified INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX sqliimport_mapping__sqliimport_map_import ON sqliimport_mapping  ( import_id );
CREATE TABLE sqliimport_mapping_field (
  attribute varchar(100) NOT NULL DEFAULT '',
  default_value longtext,
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  is_required INTEGER(11) NOT NULL DEFAULT '0',
  language varchar(20) NOT NULL DEFAULT '',
  mapping_id INTEGER(11) NOT NULL DEFAULT '0',
  sort_order INTEGER(11) NOT NULL DEFAULT '0',
  source_path varchar(255) NOT NULL DEFAULT '',
  transforms_json longtext
);

CREATE  INDEX sqliimport_mapping_field__sqliimport_mapf_mapping ON sqliimport_mapping_field  ( mapping_id, sort_order );
CREATE TABLE sqliimport_run (
  created_count INTEGER(11) NOT NULL DEFAULT '0',
  cursor_json longtext,
  def_version INTEGER(11) NOT NULL DEFAULT '0',
  failed_count INTEGER(11) NOT NULL DEFAULT '0',
  finished_at INTEGER(11) NOT NULL DEFAULT '0',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  import_id INTEGER(11) NOT NULL DEFAULT '0',
  legacy_item_id INTEGER(11) NOT NULL DEFAULT '0',
  memory_peak INTEGER(11) NOT NULL DEFAULT '0',
  parent_run_id INTEGER(11) NOT NULL DEFAULT '0',
  processed_count INTEGER(11) NOT NULL DEFAULT '0',
  queue_group varchar(40) NOT NULL DEFAULT '',
  queue_job varchar(40) NOT NULL DEFAULT '',
  queued_at INTEGER(11) NOT NULL DEFAULT '0',
  read_count INTEGER(11) NOT NULL DEFAULT '0',
  run_kind varchar(20) NOT NULL DEFAULT 'import',
  schedule_id INTEGER(11) NOT NULL DEFAULT '0',
  skipped_count INTEGER(11) NOT NULL DEFAULT '0',
  source_ref varchar(255) NOT NULL DEFAULT '',
  started_at INTEGER(11) NOT NULL DEFAULT '0',
  status varchar(30) NOT NULL DEFAULT 'queued',
  summary longtext,
  total_count INTEGER(11) NOT NULL DEFAULT '0',
  trigger_kind varchar(20) NOT NULL DEFAULT 'manual',
  unchanged_count INTEGER(11) NOT NULL DEFAULT '0',
  undone_count INTEGER(11) NOT NULL DEFAULT '0',
  updated_count INTEGER(11) NOT NULL DEFAULT '0',
  user_id INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX sqliimport_run_import ON sqliimport_run  ( import_id, id );
CREATE  INDEX sqliimport_run_status ON sqliimport_run  ( status );
CREATE TABLE sqliimport_run_log (
  context_json longtext,
  created INTEGER(11) NOT NULL DEFAULT '0',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  message longtext,
  row_ref varchar(255) NOT NULL DEFAULT '',
  run_id INTEGER(11) NOT NULL DEFAULT '0',
  severity varchar(10) NOT NULL DEFAULT 'info'
);

CREATE  INDEX sqliimport_run_log__sqliimport_log_run ON sqliimport_run_log  ( run_id, id );
CREATE TABLE sqliimport_run_row (
  created INTEGER(11) NOT NULL DEFAULT '0',
  data_json longtext,
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  message longtext,
  nodes_json longtext,
  object_id INTEGER(11) NOT NULL DEFAULT '0',
  outcome varchar(20) NOT NULL DEFAULT '',
  remote_id varchar(100) NOT NULL DEFAULT '',
  row_index INTEGER(11) NOT NULL DEFAULT '0',
  row_ref varchar(255) NOT NULL DEFAULT '',
  run_id INTEGER(11) NOT NULL DEFAULT '0',
  status varchar(20) NOT NULL DEFAULT '',
  version_after INTEGER(11) NOT NULL DEFAULT '0',
  version_before INTEGER(11) NOT NULL DEFAULT '0'
);

CREATE  INDEX sqliimport_run_row__sqliimport_row_object ON sqliimport_run_row  ( object_id );
CREATE  INDEX sqliimport_run_row__sqliimport_row_outcome ON sqliimport_run_row  ( run_id, outcome );
CREATE  UNIQUE INDEX sqliimport_run_row__sqliimport_row_ref ON sqliimport_run_row  ( run_id, row_ref );
CREATE TABLE sqliimport_schedule (
  catch_up varchar(20) NOT NULL DEFAULT 'one',
  created INTEGER(11) NOT NULL DEFAULT '0',
  creator_id INTEGER(11) NOT NULL DEFAULT '0',
  cron_expr varchar(100) NOT NULL DEFAULT '',
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  import_id INTEGER(11) NOT NULL DEFAULT '0',
  interval_minutes INTEGER(11) NOT NULL DEFAULT '0',
  is_active INTEGER(11) NOT NULL DEFAULT '0',
  kind varchar(20) NOT NULL DEFAULT 'once',
  last_run INTEGER(11) NOT NULL DEFAULT '0',
  modified INTEGER(11) NOT NULL DEFAULT '0',
  next_run INTEGER(11) NOT NULL DEFAULT '0',
  notify_json longtext,
  run_at INTEGER(11) NOT NULL DEFAULT '0',
  timezone varchar(64) NOT NULL DEFAULT 'UTC'
);

CREATE  INDEX sqliimport_schedule__sqliimport_sched_due ON sqliimport_schedule  ( is_active, next_run );
CREATE  INDEX sqliimport_schedule__sqliimport_sched_import ON sqliimport_schedule  ( import_id );
CREATE TABLE sqliimport_scheduled (
  frequency varchar(30) NOT NULL DEFAULT '',
  handler varchar(50) DEFAULT NULL,
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  is_active tinyint(4) DEFAULT '0',
  label varchar(255) DEFAULT NULL,
  manual_frequency INTEGER(11) DEFAULT '0',
  next INTEGER(11) DEFAULT '0',
  options_serialized longtext,
  requested_time INTEGER(11) DEFAULT NULL,
  user_id INTEGER(11) DEFAULT NULL
);

CREATE  INDEX sqliimport_scheduled__import_handler ON sqliimport_scheduled  ( handler );
CREATE  INDEX sqliimport_scheduled__next_import ON sqliimport_scheduled  ( next );
