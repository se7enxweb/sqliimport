<?php /* #?ini charset="utf-8"?

# The server functions of the Imports tab: sqliimport::runstatus (the live run page). The caller needs ezjscore/call
# (unlimited, or limited to the function list "sqliimport"); each function checks the form token and the policy
# sqliimport/view itself.
[ezjscServer]
FunctionList[]=sqliimport

[ezjscServer_sqliimport]
Class=expImportServerFunctions
Functions[]=sqliimport

*/ ?>
