<?php
/**
 * File containing the expImportTrigger and expImportTriggerKey classes.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The webhook / API trigger of an import (sqliimport_trigger), one row per import, off until it is switched on in the
 * schedule step of the editor:
 *
 *   is_enabled    the trigger answers (otherwise every call gets 404, as for an import that does not exist)
 *   run_user_id   the user a triggered run runs as: a user with the policy Run imports (or Manage imports) for the
 *                 import's handler, never anonymous; the import's robot user (target owner_id) still takes precedence,
 *                 as for every run
 *   allow_push    a call may bring the data in its body, which becomes the run's snapshot (file formats only)
 *   secret_ref    the name of a secret of expImportSecrets to sign with, instead of the derived secret
 *   sign_salt     the random salt the signing secret is derived from (expImportTriggerAuth::signingSecret());
 *                 rotating draws a new one, and the old secret stops working at once
 */
class expImportTrigger extends eZPersistentObject
{
    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_trigger' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportTrigger',
            'name' => 'sqliimport_trigger',
        );
    }

    /** @return expImportTrigger|null */
    public static function fetchByImport( $importID )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'import_id' => (int)$importID ) );
    }

    /** @return expImportTrigger the stored trigger of an import, or a new one (switched off, not stored) */
    public static function forImport( $importID )
    {
        $trigger = self::fetchByImport( $importID );
        if ( $trigger )
            return $trigger;
        $now = expImport::now();
        return new self( array( 'import_id' => (int)$importID, 'is_enabled' => 0, 'allow_push' => 0, 'run_user_id' => 0, 'secret_ref' => '',
                                'sign_salt' => '', 'sign_created' => 0, 'created' => $now, 'modified' => $now, 'modifier_id' => expImport::currentUserID() ) );
    }

    /** @return string|null the secret a call is signed with, null when there is none yet */
    public function signingSecret()
    {
        $ref = (string)$this->attribute( 'secret_ref' );
        if ( $ref !== '' )
            return expImportSecrets::get( $ref );
        $salt = (string)$this->attribute( 'sign_salt' );
        return $salt === '' ? null : expImportTriggerAuth::signingSecret( $salt );
    }

    /**
     * Draws a new signing secret (the old one stops working).
     * @return string the new secret, to be shown once
     */
    public function rotate()
    {
        $this->setAttribute( 'sign_salt', expImportTriggerAuth::newSalt() );
        $this->setAttribute( 'sign_created', expImport::now() );
        return expImportTriggerAuth::signingSecret( (string)$this->attribute( 'sign_salt' ) );
    }

    /** Removes the trigger of an import with its keys and recent calls. */
    public static function removeByImport( $importID )
    {
        $db = expImport::db();
        $importID = (int)$importID;
        foreach ( array( 'sqliimport_trigger', 'sqliimport_trigger_key', 'sqliimport_trigger_call' ) as $table )
            $db->query( "DELETE FROM $table WHERE import_id = $importID" );
    }
}

/**
 * An API key of a trigger (sqliimport_trigger_key): its label, its key id (the part of the key that finds it) and the
 * HMAC of the key (expImportTriggerAuth::hashKey()); the key itself is shown once when it is created and never stored.
 * A revoked key keeps its row (revoked = when) so the page can say when it stopped working.
 */
class expImportTriggerKey extends eZPersistentObject
{
    public static function definition()
    {
        return array(
            'fields' => expImport::persistentFields( 'sqliimport_trigger_key' ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expImportTriggerKey',
            'name' => 'sqliimport_trigger_key',
        );
    }

    /** @return expImportTriggerKey|null */
    public static function fetch( $id )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int)$id ) );
    }

    /** @return expImportTriggerKey|null the key (revoked or not) of a key id */
    public static function fetchByKeyId( $keyID )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'key_id' => (string)$keyID ) );
    }

    /** @return expImportTriggerKey[] */
    public static function fetchByImport( $importID )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null, array( 'import_id' => (int)$importID ), array( 'id' => 'asc' ) );
    }

    /**
     * A new key of an import, stored.
     *
     * @param int $importID
     * @param string $label
     * @return array [ expImportTriggerKey, string the key, shown once ]
     */
    public static function create( $importID, $label )
    {
        list( $key, $keyID ) = expImportTriggerAuth::newKey();
        $row = new self( array( 'import_id' => (int)$importID, 'label' => mb_substr( trim( (string)$label ), 0, 100 ), 'key_id' => $keyID,
                                'key_hash' => expImportTriggerAuth::hashKey( $key ), 'created' => expImport::now(),
                                'creator_id' => expImport::currentUserID(), 'last_used' => 0, 'revoked' => 0 ) );
        $row->store();
        return array( $row, $key );
    }

    /**
     * The stored key of a presented key: same import, not revoked, and the hash matches.
     *
     * @param int $importID
     * @param string $key
     * @return expImportTriggerKey|null
     */
    public static function verify( $importID, $key )
    {
        $keyID = expImportTriggerAuth::keyId( $key );
        if ( $keyID === null )
            return null;
        $row = self::fetchByKeyId( $keyID );
        if ( !$row || (int)$row->attribute( 'import_id' ) !== (int)$importID || (int)$row->attribute( 'revoked' ) )
            return null;
        return expImportTriggerAuth::verifyKey( $key, (string)$row->attribute( 'key_hash' ) ) ? $row : null;
    }
}
