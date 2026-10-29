<?php
/**
 * File containing the expImportVcardSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * vCard files (.vcf; versions 4.0, 3.0 and, as far as it goes, 2.1), as address books and phones export contacts.
 * Every BEGIN:VCARD ... END:VCARD is one record:
 *
 *   id            the UID (without urn:uuid:), else a hash of the formatted name and the first e-mail address
 *   uid, version, kind (4.0: individual, org ...), fn (the formatted name)
 *   n             family, given, additional, prefix, suffix; also as family_name and given_name
 *   nickname, org (the organization), org_unit, title, role, note, bday (as written), bday_iso (YYYY-MM-DD, or
 *                 --MM-DD without a year), anniversary, gender, rev
 *   email, emails the preferred (else the first) address and all of them; email_work, email_home the first of that type
 *   tel, tels     the preferred (else the first) number and all of them; tel_work, tel_home, tel_cell, tel_fax the first of that type
 *   adr           the first address: po_box, extended, street, locality, region, postal_code, country, type;
 *                 adr_text the same on one line; adrs every address
 *   url, urls, categories (a list), photo (the URL of the photo; an embedded photo is not kept: photo_embedded 1),
 *   raw           every property by name, as text
 *
 * Contact data is personal data: import it only where the people it names agreed to it.
 */
class expImportVcardSource extends expImportContentLineSource
{
    public static function identifier()
    {
        return 'vcard';
    }

    public static function label()
    {
        return 'vCard contacts (.vcf)';
    }

    public static function extensions()
    {
        return array( 'vcf', 'vcard', 'txt' );
    }

    public static function description()
    {
        return 'The contact format of address books, phones and mail programs (vCard 3.0 and 4.0). Every card is a record '
             . 'with name and its parts, e-mail addresses, phone numbers, organization, title, address, web address, note, '
             . 'birthday and photo address. There is no preset mapping: map the cards to a contact class of your site.';
    }

    public static function group()
    {
        return 'content';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Three fictitious contacts in vCard 4.0 and 3.0', 'file' => 'contacts.vcf' ) );
    }

    public static function schema()
    {
        return array(
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id',
                            'help' => 'The field that names a record. id: the UID, else a hash of name and e-mail address.' ),
        );
    }

    public function records( array $cursor = array() )
    {
        $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        foreach ( $this->components( $cursor, array( 'VCARD' ) ) as $component )
        {
            list( , $lines, $end ) = $component;
            $data = self::card( $lines );
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'pos' => $end, 'index' => $index + 1 ) );
            $index++;
        }
        if ( $index === 0 && empty( $cursor['pos'] ) )
            throw new expImportSourceException( 'the file has no vCard (no BEGIN:VCARD)' );
    }

    /**
     * The record of one card.
     * @param array $lines its parsed lines
     * @return array
     */
    public static function card( array $lines )
    {
        $props = self::byName( $lines );
        $text = function ( $key ) use ( $props )
        {
            return isset( $props[$key] ) ? trim( self::text( $props[$key][0] ) ) : '';
        };
        $n = isset( $props['N'] ) ? self::split( self::decoded( $props['N'][0] ), ';' ) : array();
        $part = function ( array $list, $i )
        {
            return isset( $list[$i] ) ? trim( $list[$i] ) : '';
        };
        $name = array( 'family' => $part( $n, 0 ), 'given' => $part( $n, 1 ), 'additional' => $part( $n, 2 ),
                       'prefix' => $part( $n, 3 ), 'suffix' => $part( $n, 4 ) );
        $fn = $text( 'FN' );
        if ( $fn === '' )
            $fn = trim( implode( ' ', array_filter( array( $name['prefix'], $name['given'], $name['additional'], $name['family'], $name['suffix'] ), 'strlen' ) ) );
        // e-mail addresses and phone numbers, all of them and the first of each type
        $typed = function ( $key, array $kinds, $clean ) use ( $props )
        {
            $all = array();
            $first = array_fill_keys( $kinds, '' );
            $preferred = '';
            foreach ( isset( $props[$key] ) ? $props[$key] : array() as $line )
            {
                $value = $clean( trim( self::text( $line ) ) );
                if ( $value === '' )
                    continue;
                $all[] = $value;
                $types = self::types( $line );
                if ( $preferred === '' && ( in_array( 'pref', $types, true ) || isset( $line['params']['PREF'] ) ) )
                    $preferred = $value;
                foreach ( $kinds as $kind )
                    if ( $first[$kind] === '' && in_array( $kind, $types, true ) )
                        $first[$kind] = $value;
                if ( isset( $first['cell'] ) && $first['cell'] === '' && in_array( 'mobile', $types, true ) )
                    $first['cell'] = $value;
            }
            return array( $all, $first, $preferred !== '' ? $preferred : ( $all ? $all[0] : '' ) );
        };
        list( $emails, $emailTypes, $email ) = $typed( 'EMAIL', array( 'work', 'home' ), function ( $v ) { return (string)preg_replace( '/^mailto:/i', '', $v ); } );
        list( $tels, $telTypes, $tel ) = $typed( 'TEL', array( 'work', 'home', 'cell', 'fax' ), function ( $v ) { return (string)preg_replace( '/^tel:/i', '', $v ); } );
        $adrs = array();
        foreach ( isset( $props['ADR'] ) ? $props['ADR'] : array() as $line )
        {
            $p = self::split( self::decoded( $line ), ';' );
            $adr = array( 'po_box' => $part( $p, 0 ), 'extended' => $part( $p, 1 ), 'street' => $part( $p, 2 ), 'locality' => $part( $p, 3 ),
                          'region' => $part( $p, 4 ), 'postal_code' => $part( $p, 5 ), 'country' => $part( $p, 6 ),
                          'type' => implode( ',', array_diff( self::types( $line ), array( 'pref' ) ) ) );
            if ( implode( '', $adr ) !== '' )
                $adrs[] = $adr;
        }
        $adr = $adrs ? $adrs[0] : array( 'po_box' => '', 'extended' => '', 'street' => '', 'locality' => '', 'region' => '', 'postal_code' => '', 'country' => '', 'type' => '' );
        $adrText = implode( ', ', array_filter( array( $adr['po_box'], $adr['extended'], str_replace( "\n", ', ', $adr['street'] ),
                                                       trim( $adr['postal_code'] . ' ' . $adr['locality'] ), $adr['region'], $adr['country'] ), 'strlen' ) );
        $org = isset( $props['ORG'] ) ? self::split( self::decoded( $props['ORG'][0] ), ';' ) : array();
        $urls = array();
        foreach ( isset( $props['URL'] ) ? $props['URL'] : array() as $line )
            if ( ( $u = trim( self::text( $line ) ) ) !== '' )
                $urls[] = $u;
        $categories = array();
        foreach ( isset( $props['CATEGORIES'] ) ? $props['CATEGORIES'] : array() as $line )
            foreach ( self::split( self::decoded( $line ), ',' ) as $c )
                if ( trim( $c ) !== '' )
                    $categories[] = trim( $c );
        list( $photo, $embedded ) = isset( $props['PHOTO'] ) ? self::photo( $props['PHOTO'][0] ) : array( '', false );
        $bday = $text( 'BDAY' );
        $uid = (string)preg_replace( '/^urn:uuid:/i', '', $text( 'UID' ) );
        $raw = array();
        foreach ( $props as $key => $list )
            $raw[strtolower( $key )] = $key === 'PHOTO' || $key === 'LOGO' || $key === 'SOUND' || $key === 'KEY' ? $photo : trim( self::text( $list[0] ) );
        $gender = isset( $props['GENDER'] ) ? self::split( self::decoded( $props['GENDER'][0] ), ';' ) : array();
        return array(
            'id' => $uid !== '' ? $uid : 'vcard-' . substr( sha1( strtolower( $fn . '|' . $email ) ), 0, 16 ),
            'uid' => $uid,
            'version' => $text( 'VERSION' ),
            'kind' => strtolower( $text( 'KIND' ) ),
            'fn' => $fn,
            'n' => $name,
            'family_name' => $name['family'],
            'given_name' => $name['given'],
            'nickname' => $text( 'NICKNAME' ),
            'org' => $part( $org, 0 ),
            'org_unit' => implode( ', ', array_filter( array_map( 'trim', array_slice( $org, 1 ) ), 'strlen' ) ),
            'title' => $text( 'TITLE' ),
            'role' => $text( 'ROLE' ),
            'email' => $email,
            'emails' => $emails,
            'email_work' => $emailTypes['work'],
            'email_home' => $emailTypes['home'],
            'tel' => $tel,
            'tels' => $tels,
            'tel_work' => $telTypes['work'],
            'tel_home' => $telTypes['home'],
            'tel_cell' => $telTypes['cell'],
            'tel_fax' => $telTypes['fax'],
            'adr' => $adr,
            'adr_text' => $adrText,
            'adrs' => $adrs,
            'url' => $urls ? $urls[0] : '',
            'urls' => $urls,
            'note' => $text( 'NOTE' ),
            'bday' => $bday,
            'bday_iso' => self::isoDate( $bday ),
            'anniversary' => $text( 'ANNIVERSARY' ),
            'gender' => strtoupper( $part( $gender, 0 ) ),
            'categories' => $categories,
            'photo' => $photo,
            'photo_embedded' => $embedded ? '1' : '0',
            'rev' => $text( 'REV' ),
            'raw' => $raw,
        );
    }

    /**
     * The address of a photo: an http(s) URL is kept, an embedded image (base64, data:) is not.
     *
     * @return array the address (string) and whether it is embedded (bool)
     */
    protected static function photo( array $property )
    {
        $value = trim( $property['value'] );
        $encoding = isset( $property['params']['ENCODING'] ) ? strtolower( $property['params']['ENCODING'] ) : '';
        if ( $encoding === 'b' || $encoding === 'base64' || stripos( $value, 'data:' ) === 0 )
            return array( '', true );
        return preg_match( '#^https?://#i', $value ) ? array( $value, false ) : array( '', $value !== '' );
    }

    /** @return string a vCard date as YYYY-MM-DD ("19850412", "1985-04-12", "--0412" gives "--04-12"), '' if none */
    public static function isoDate( $value )
    {
        $value = trim( (string)$value );
        if ( preg_match( '/^(\d{4})-?(\d{2})-?(\d{2})(?:T.*)?$/', $value, $m ) )
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        if ( preg_match( '/^--(\d{2})-?(\d{2})$/', $value, $m ) )
            return '--' . $m[1] . '-' . $m[2];
        return '';
    }
}
