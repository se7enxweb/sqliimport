<?php /* #?ini charset="utf-8"?

[RegionalSettings]
TranslationExtensions[]=sqliimport

# The webhook / API trigger of an import (sqliimport/trigger) is called by other systems, which never sign in: the
# view checks a signature or an API key of the import itself and answers 404 for an import whose trigger is off.
[SiteAccessSettings]
AnonymousAccessList[]=sqliimport/trigger

[RoleSettings]
PolicyOmitList[]=sqliimport/trigger

*/ ?>
