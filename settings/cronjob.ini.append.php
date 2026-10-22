<?php /* #?ini charset="utf-8"?

[CronjobSettings]
ExtensionDirectories[]=sqliimport

# The cron part of 7x SQLI Import 2.x, run it every minute:
#   * * * * * cd <installation> && php runcronjobs.php -q sqliimport
# It queues the runs of due schedules and the pending imports of 1.x, ends runs whose jobs ended, and purges old
# runs ([RunSettings] KeepDays). The runs themselves are done by the workers of the job queue (cron part expqueue).
# The tick also runs within the cron part of the job queue (every minute where expqueue is set up), so a site
# needs no cron line of its own; a lock keeps the two from overlapping.
[CronjobPart-expqueue]
Scripts[]=sqliimport_schedule.php

[CronjobPart-sqliimport]
Scripts[]=sqliimport_schedule.php

# Deprecated 1.x cron parts, kept for one major version: with the job queue installed, sqliimport_run hands the
# pending and scheduled 1.x imports to the 2.x engine (as the part above does); without it, it runs them as 1.x did.
[CronjobPart-sqliimport_cleanup]
Scripts[]=sqliimport_cleanup.php
Scripts[]=indexcontent.php

[CronjobPart-sqliimport_run]
Scripts[]=sqliimport_run.php
Scripts[]=sqliimport_cleanup.php
Scripts[]=indexcontent.php

*/ ?>
