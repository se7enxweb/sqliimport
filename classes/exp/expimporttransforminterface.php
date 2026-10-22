<?php
/**
 * File containing the expImportTransformInterface interface.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A named transform of a mapping line: it turns a source value into the value a class attribute takes (the
 * fromString() form of its datatype). A site adds one as a kernel service tagged sqliimport.transform
 * (settings/kernelservices.ini.append.php); the built-in ones are in expImportTransforms.
 *
 * A transform is a function of its input, its arguments and the record: it does not keep state between calls, and it
 * never runs code it was given as data. A mapping (and an AI suggestion) can only name registered transforms.
 */
interface expImportTransformInterface
{
    /** @return string the name a mapping uses, lower case letters, digits and _ */
    public function name();

    /** @return string one line for the catalogue and the mapping editor */
    public function label();

    /** @return array the arguments, as an expImportOptionSchema schema */
    public function schema();

    /**
     * @param mixed $value the value so far
     * @param array $args validated arguments
     * @param expImportRecord|null $record the whole record (for concat and template)
     * @return mixed
     * @throws expImportTransformException the value cannot be transformed (the row fails with the message)
     */
    public function apply( $value, array $args, ?expImportRecord $record = null );
}
