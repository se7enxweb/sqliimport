<?php /* #?ini charset="utf-8"?

# The services of 7x SQLI Import (Exponential's kernel service container, settings/kernelservices.ini).
# A site adds a source, a handler or a transform with a block of its own tagged sqliimport.source,
# sqliimport.handler or sqliimport.transform, and can replace these by a block of the same name.

[Service_sqliimport.registry]
Class=expImportRegistry
Scope=worker
Description=7x SQLI Import: the catalogue of sources, handlers and transforms

*/ ?>
