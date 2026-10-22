-- 7x SQLI Import: the tables sqliimport_definition, sqliimport_item, sqliimport_mapping, sqliimport_mapping_field, sqliimport_run, sqliimport_run_log, sqliimport_run_row, sqliimport_schedule, sqliimport_scheduled (mysql).
-- Generated from expImportSchema::definition() by ./console ext:sqliimport:install-schema --write-files with the
-- kernel's schema handler; ./console ext:sqliimport:install-schema creates the same tables in a running installation.

CREATE TABLE sqliimport_definition (
  behaviour_json longtext,
  created int(11) NOT NULL DEFAULT '0',
  creator_id int(11) NOT NULL DEFAULT '0',
  def_version int(11) NOT NULL DEFAULT '0',
  description longtext,
  handler varchar(100) NOT NULL DEFAULT 'mapping',
  id int(11) NOT NULL AUTO_INCREMENT,
  identifier varchar(100) NOT NULL DEFAULT '',
  is_enabled int(11) NOT NULL DEFAULT '0',
  legacy_scheduled_id int(11) NOT NULL DEFAULT '0',
  mapping_id int(11) NOT NULL DEFAULT '0',
  modified int(11) NOT NULL DEFAULT '0',
  modifier_id int(11) NOT NULL DEFAULT '0',
  name varchar(255) NOT NULL DEFAULT '',
  options_json longtext,
  source_config longtext,
  source_type varchar(40) NOT NULL DEFAULT '',
  target_json longtext,
  PRIMARY KEY ( id ),
  UNIQUE KEY sqliimport_def_identifier ( identifier ),
  KEY sqliimport_def_handler ( handler )
) ENGINE=InnoDB;

CREATE TABLE sqliimport_item (
  handler varchar(50) DEFAULT NULL,
  id int(11) NOT NULL AUTO_INCREMENT,
  options_serialized longtext,
  percentage_int smallint(6) DEFAULT '0',
  process_time int(11) DEFAULT '0',
  progression_notes longtext,
  requested_time int(11) DEFAULT '0',
  scheduled_id int(11) DEFAULT NULL,
  status tinyint(4) DEFAULT '0',
  type tinyint(4) DEFAULT '1',
  user_id int(11) DEFAULT NULL,
  PRIMARY KEY ( id ),
  KEY import_item_handler ( handler ),
  KEY import_item_status ( status ),
  KEY import_item_user ( user_id ),
  KEY import_scheduled_id ( scheduled_id )
) ENGINE=InnoDB;

CREATE TABLE sqliimport_mapping (
  class_identifier varchar(100) NOT NULL DEFAULT '',
  created int(11) NOT NULL DEFAULT '0',
  id int(11) NOT NULL AUTO_INCREMENT,
  import_id int(11) NOT NULL DEFAULT '0',
  language varchar(20) NOT NULL DEFAULT '',
  location_json longtext,
  match_key varchar(255) NOT NULL DEFAULT '',
  modified int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  KEY sqliimport_map_import ( import_id )
) ENGINE=InnoDB;

CREATE TABLE sqliimport_mapping_field (
  attribute varchar(100) NOT NULL DEFAULT '',
  default_value longtext,
  id int(11) NOT NULL AUTO_INCREMENT,
  is_required int(11) NOT NULL DEFAULT '0',
  language varchar(20) NOT NULL DEFAULT '',
  mapping_id int(11) NOT NULL DEFAULT '0',
  sort_order int(11) NOT NULL DEFAULT '0',
  source_path varchar(255) NOT NULL DEFAULT '',
  transforms_json longtext,
  PRIMARY KEY ( id ),
  KEY sqliimport_mapf_mapping ( mapping_id, sort_order )
) ENGINE=InnoDB;

CREATE TABLE sqliimport_run (
  created_count int(11) NOT NULL DEFAULT '0',
  cursor_json longtext,
  def_version int(11) NOT NULL DEFAULT '0',
  failed_count int(11) NOT NULL DEFAULT '0',
  finished_at int(11) NOT NULL DEFAULT '0',
  id int(11) NOT NULL AUTO_INCREMENT,
  import_id int(11) NOT NULL DEFAULT '0',
  legacy_item_id int(11) NOT NULL DEFAULT '0',
  memory_peak int(11) NOT NULL DEFAULT '0',
  parent_run_id int(11) NOT NULL DEFAULT '0',
  processed_count int(11) NOT NULL DEFAULT '0',
  queue_group varchar(40) NOT NULL DEFAULT '',
  queue_job varchar(40) NOT NULL DEFAULT '',
  queued_at int(11) NOT NULL DEFAULT '0',
  read_count int(11) NOT NULL DEFAULT '0',
  run_kind varchar(20) NOT NULL DEFAULT 'import',
  schedule_id int(11) NOT NULL DEFAULT '0',
  skipped_count int(11) NOT NULL DEFAULT '0',
  source_ref varchar(255) NOT NULL DEFAULT '',
  started_at int(11) NOT NULL DEFAULT '0',
  status varchar(30) NOT NULL DEFAULT 'queued',
  summary longtext,
  total_count int(11) NOT NULL DEFAULT '0',
  trigger_kind varchar(20) NOT NULL DEFAULT 'manual',
  unchanged_count int(11) NOT NULL DEFAULT '0',
  updated_count int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  KEY sqliimport_run_import ( import_id, id ),
  KEY sqliimport_run_status ( status )
) ENGINE=InnoDB;

CREATE TABLE sqliimport_run_log (
  context_json longtext,
  created int(11) NOT NULL DEFAULT '0',
  id int(11) NOT NULL AUTO_INCREMENT,
  message longtext,
  row_ref varchar(255) NOT NULL DEFAULT '',
  run_id int(11) NOT NULL DEFAULT '0',
  severity varchar(10) NOT NULL DEFAULT 'info',
  PRIMARY KEY ( id ),
  KEY sqliimport_log_run ( run_id, id )
) ENGINE=InnoDB;

CREATE TABLE sqliimport_run_row (
  created int(11) NOT NULL DEFAULT '0',
  data_json longtext,
  id int(11) NOT NULL AUTO_INCREMENT,
  message longtext,
  nodes_json longtext,
  object_id int(11) NOT NULL DEFAULT '0',
  outcome varchar(20) NOT NULL DEFAULT '',
  remote_id varchar(100) NOT NULL DEFAULT '',
  row_index int(11) NOT NULL DEFAULT '0',
  row_ref varchar(255) NOT NULL DEFAULT '',
  run_id int(11) NOT NULL DEFAULT '0',
  status varchar(20) NOT NULL DEFAULT '',
  version_after int(11) NOT NULL DEFAULT '0',
  version_before int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY ( id ),
  UNIQUE KEY sqliimport_row_ref ( run_id, row_ref ),
  KEY sqliimport_row_outcome ( run_id, outcome ),
  KEY sqliimport_row_object ( object_id )
) ENGINE=InnoDB;

CREATE TABLE sqliimport_schedule (
  catch_up varchar(20) NOT NULL DEFAULT 'one',
  created int(11) NOT NULL DEFAULT '0',
  creator_id int(11) NOT NULL DEFAULT '0',
  cron_expr varchar(100) NOT NULL DEFAULT '',
  id int(11) NOT NULL AUTO_INCREMENT,
  import_id int(11) NOT NULL DEFAULT '0',
  interval_minutes int(11) NOT NULL DEFAULT '0',
  is_active int(11) NOT NULL DEFAULT '0',
  kind varchar(20) NOT NULL DEFAULT 'once',
  last_run int(11) NOT NULL DEFAULT '0',
  modified int(11) NOT NULL DEFAULT '0',
  next_run int(11) NOT NULL DEFAULT '0',
  notify_json longtext,
  run_at int(11) NOT NULL DEFAULT '0',
  timezone varchar(64) NOT NULL DEFAULT 'UTC',
  PRIMARY KEY ( id ),
  KEY sqliimport_sched_import ( import_id ),
  KEY sqliimport_sched_due ( is_active, next_run )
) ENGINE=InnoDB;

CREATE TABLE sqliimport_scheduled (
  frequency varchar(30) NOT NULL DEFAULT '',
  handler varchar(50) DEFAULT NULL,
  id int(11) NOT NULL AUTO_INCREMENT,
  is_active tinyint(4) DEFAULT '0',
  label varchar(255) DEFAULT NULL,
  manual_frequency int(11) DEFAULT '0',
  next int(11) DEFAULT '0',
  options_serialized longtext,
  requested_time int(11) DEFAULT NULL,
  user_id int(11) DEFAULT NULL,
  PRIMARY KEY ( id ),
  KEY import_handler ( handler ),
  KEY next_import ( next )
) ENGINE=InnoDB;

