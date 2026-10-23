<?php
/**
 * File containing the expImportPrice class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The value of a price attribute (ezprice) as the kernel reads and writes it: "price|VAT type id|1 or 0" (VAT
 * included). A record often has a plain number ("12.50", "12,50"): the kernel refused it and the price was stored as
 * 0. A plain number is completed with the class attribute's default VAT type and its "VAT included" setting, and the
 * number is written as the kernel prints a stored price (12.50 -> 12.5), so the next run compares like with like and
 * an unchanged price is not "updated" on every run.
 */
class expImportPrice
{
    /**
     * @param mixed $value the mapped value
     * @param string|int $vatID the class attribute's VAT type id ('' when none)
     * @param bool $vatIncluded the class attribute's default
     * @return string the value for the kernel, '' for an empty value; an unreadable value is returned as it is (the
     *                kernel then refuses it and the record says so)
     */
    public static function normalize( $value, $vatID = '', $vatIncluded = true )
    {
        $text = trim( (string)$value );
        if ( $text === '' )
            return '';
        $parts = explode( '|', $text );
        $number = self::number( $parts[0] );
        if ( $number === null )
            return $text;
        if ( count( $parts ) === 3 )
            return $number . '|' . trim( $parts[1] ) . '|' . trim( $parts[2] );
        if ( count( $parts ) !== 1 )
            return $text;
        return $number . '|' . ( $vatID === null ? '' : trim( (string)$vatID ) ) . '|' . ( $vatIncluded ? '1' : '0' );
    }

    /**
     * A number as PHP prints a float (the way the kernel's toString() gives a stored price), from "12.50", "12,50",
     * "1.234,50", "1,234.50" or "€ 12.50"; null when it is no number.
     *
     * @param string $text
     * @return string|null
     */
    public static function number( $text )
    {
        $s = preg_replace( '/[^0-9,.\-]/', '', (string)$text );
        if ( $s === '' || $s === '-' )
            return null;
        $comma = strrpos( $s, ',' );
        $dot = strrpos( $s, '.' );
        if ( $comma !== false && $dot !== false )
            $s = $comma > $dot ? str_replace( array( '.', ',' ), array( '', '.' ), $s ) : str_replace( ',', '', $s );
        elseif ( $comma !== false )
            $s = substr_count( $s, ',' ) === 1 ? str_replace( ',', '.', $s ) : str_replace( ',', '', $s );
        if ( !is_numeric( $s ) )
            return null;
        $f = (float)$s;
        return is_finite( $f ) ? (string)$f : null;
    }
}
