<?php
/**
 * File containing the expImportDataTableFieldSource class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/*
 * The provider of the fields of an import for the kernel's "Module table" wizard (service
 * datatable.field_source.sqliimport, expImportDataTableFieldSourceBase has the methods). It implements the kernel's
 * interface expDataTableFieldSource where the kernel has it (Exponential 6.0.15 with the wizard); on an older kernel
 * the same class exists without it, so the registration in kernelservices.ini never breaks loading the services.
 */
if ( interface_exists( 'expDataTableFieldSource' ) )
{
    class expImportDataTableFieldSource extends expImportDataTableFieldSourceBase implements expDataTableFieldSource
    {
    }
}
else
{
    class expImportDataTableFieldSource extends expImportDataTableFieldSourceBase
    {
    }
}
