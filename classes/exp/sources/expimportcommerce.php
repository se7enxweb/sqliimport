<?php
/**
 * File containing the expImportCommerce class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What the product feed sources share: prices as decimal strings with their currency, lists written in one cell, and
 * the mapping preset to the class product of the Exponential webshop.
 */
class expImportCommerce
{
    /**
     * A price as a decimal string with a point: "1.299,00" and "1,299.00" are "1299.00", "15" is "15". '' for none.
     *
     * @param string $value
     * @return string
     */
    public static function decimal( $value )
    {
        $s = preg_replace( '/[^0-9.,\-]/', '', (string)$value );
        if ( $s === '' || !preg_match( '/\d/', $s ) )
            return '';
        $negative = strpos( $s, '-' ) === 0;
        $s = str_replace( '-', '', $s );
        $comma = strrpos( $s, ',' );
        $point = strrpos( $s, '.' );
        if ( $comma !== false && $point !== false )
        {
            // the one written last is the decimal mark, the other groups thousands
            $decimalMark = $comma > $point ? ',' : '.';
            $s = str_replace( $decimalMark === ',' ? '.' : ',', '', $s );
            $s = str_replace( ',', '.', $s );
        }
        elseif ( $comma !== false )
        {
            // one comma before one or two digits is a decimal comma; 1,299 groups thousands
            $s = preg_match( '/^\d+,\d{1,2}$/', $s ) ? str_replace( ',', '.', $s ) : str_replace( ',', '', $s );
        }
        elseif ( substr_count( $s, '.' ) > 1 )
            $s = str_replace( '.', '', $s );
        if ( !is_numeric( $s ) )
            return '';
        return ( $negative ? '-' : '' ) . $s;
    }

    /**
     * A price with its currency, as Google writes it: "15.00 USD", "USD 15.00", "15,00 EUR", "€15".
     *
     * @param string $value
     * @param string $currency the currency when the value names none
     * @return array( 'amount' => decimal string, 'currency' => ISO 4217 code or '' )
     */
    public static function money( $value, $currency = '' )
    {
        $value = trim( (string)$value );
        $code = strtoupper( (string)$currency );
        if ( preg_match( '/\b([A-Za-z]{3})\b/', $value, $m ) )
            $code = strtoupper( $m[1] );
        else
        {
            $symbols = array( '€' => 'EUR', '$' => 'USD', '£' => 'GBP', '¥' => 'JPY', 'CHF' => 'CHF' );
            foreach ( $symbols as $symbol => $iso )
                if ( strpos( $value, $symbol ) !== false && $code === '' )
                    $code = $iso;
        }
        return array( 'amount' => self::decimal( $value ), 'currency' => $code );
    }

    /**
     * A list written in one cell: "a, b, c" (a comma inside a value escaped with a backslash, as WooCommerce writes it).
     *
     * @param string $value
     * @param string $separator
     * @return string[]
     */
    public static function cellList( $value, $separator = ',' )
    {
        $out = array();
        $current = '';
        $value = (string)$value;
        for ( $i = 0, $n = strlen( $value ); $i < $n; $i++ )
        {
            $c = $value[$i];
            if ( $c === '\\' && $i + 1 < $n && $value[$i + 1] === $separator )
            {
                $current .= $separator;
                $i++;
                continue;
            }
            if ( $c === $separator )
            {
                $out[] = trim( $current );
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $out[] = trim( $current );
        return array_values( array_filter( $out, 'strlen' ) );
    }

    /**
     * The mapping preset to the class product of the webshop (name, product_number, short_description,
     * description, price, tags). The price is written as ezprice takes it: amount|VAT type 1|including VAT.
     *
     * @param string $prefix the start of the remote IDs
     * @param string $numberField the field with the product number
     * @param string $shortField the field with the short description, '' for none
     * @return array
     */
    public static function productPreset( $prefix, $numberField, $shortField = '' )
    {
        $fields = array(
            array( 'source' => 'title', 'attribute' => 'name', 'transforms' => array( array( 'name' => 'strip_tags' ), array( 'name' => 'trim' ) ) ),
            array( 'source' => $numberField, 'attribute' => 'product_number' ),
        );
        if ( $shortField !== '' )
            $fields[] = array( 'source' => $shortField, 'attribute' => 'short_description', 'transforms' => array( array( 'name' => 'richtext' ) ) );
        $fields[] = array( 'source' => 'description', 'attribute' => 'description', 'transforms' => array( array( 'name' => 'richtext' ) ) );
        $fields[] = array( 'source' => 'price', 'attribute' => 'price', 'transforms' => array( array( 'name' => 'template', 'args' => array( 'text' => '{price}|1|1' ) ) ) );
        $fields[] = array( 'source' => 'tags', 'attribute' => 'tags', 'transforms' => array( array( 'name' => 'join', 'args' => array( 'separator' => ', ' ) ) ) );
        return array(
            'class' => 'product',
            'match' => array( 'type' => 'remote_id', 'pattern' => $prefix . '-{id}' ),
            'fields' => $fields,
        );
    }
}
