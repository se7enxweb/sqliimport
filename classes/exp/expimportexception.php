<?php
/**
 * File containing the exceptions of 7x SQLI Import.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** The base of the exceptions of the 2.x engine. */
class expImportException extends RuntimeException
{
}

/** A source cannot be read (missing file, bad format, network error). */
class expImportSourceException extends expImportException
{
}

/** A value cannot be transformed; the record fails with this message. */
class expImportTransformException extends expImportException
{
}

/** A definition, mapping, schedule or option is not valid. */
class expImportValidationException extends expImportException
{
    /** @var array name => message */
    public $errors = array();

    /**
     * @param array $errors name => message
     * @param string $message
     */
    public function __construct( array $errors, $message = '' )
    {
        $this->errors = $errors;
        parent::__construct( $message !== '' ? $message : implode( '; ', $errors ) );
    }
}
