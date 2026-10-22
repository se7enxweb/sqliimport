<?php /* #?ini charset="utf-8"?

# The jobs of a run of 7x SQLI Import (expImportRunner): the start (snapshot and count), the chunks of records, and
# the finish (caches, indexing, summary). Every run has its own job group and the resource sqliimport:<import id>.
[JobType_sqliimport.run]
Handler=expImportJobHandler
Class=bulk
Timeout=900
MaxAttempts=3
Description=Starts an import run: the snapshot of the source and the count of its records

[JobType_sqliimport.chunk]
Handler=expImportJobHandler
Class=bulk
Timeout=1800
MaxAttempts=3
Description=Processes a chunk of the records of an import run

[JobType_sqliimport.finish]
Handler=expImportJobHandler
Class=bulk
Timeout=1800
MaxAttempts=3
Description=Ends an import run: view caches, indexing and the summary

*/ ?>
