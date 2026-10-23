<?php
/**
 * File containing the expImportAiExpaiTransport class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The AI assist through the extension expai (Exponential Intelligence), the only way it reaches a model. Before a
 * request is sent, in this order:
 *
 *  - expai is installed (its classes exist) and its master switch is on for this siteaccess (expAiGate);
 *  - the user has expai's policy expai/write (as for expai's field actions) and may use the model expai chooses for
 *    them (expAiConfig::effectiveModel(), mayUseModel(): the site default, model choice, expai/model_use);
 *  - the model fits a page request (expAiWebBudget) and the monthly budget has room for the request's estimate
 *    (expAiBudget);
 *  - expai's data policy allows the target to go to the model's provider: the section of the import's target and the
 *    content class (expAiDataPolicy::assertClass()); for a data table or a class still to be chosen, the section only.
 *
 * The request is sent through expAiUsageRecorder (action sqliimport:<purpose>, the target's parent node), so expai's
 * veto rules, limits, usage log and audit trail apply to it like to every other request of expai.
 */
class expImportAiExpaiTransport implements expImportAiTransport
{
    /** @return bool whether the classes of expai this transport needs exist */
    public static function installed()
    {
        foreach ( array( 'expAiConfig', 'expAiGate', 'expAiUsageRecorder', 'expAiDataPolicy', 'expAiText' ) as $class )
            if ( !class_exists( $class ) )
                return false;
        return true;
    }

    public function refusal( array $context )
    {
        if ( !self::installed() )
            return 'Exponential Intelligence (expai) is not installed.';
        try
        {
            $config = expAiConfig::instance();
            $off = expAiGate::switchedOff( $config );
            if ( $off !== '' )
                return $off;
            if ( !self::hasPolicy() )
                return 'You do not have access to Exponential Intelligence.';
            $alias = (string)$config->effectiveModel();
            if ( $alias === '' || !$config->mayUseModel( $alias ) )
                return 'You may not use the model of Exponential Intelligence.';
        }
        catch ( Throwable $e )
        {
            return 'Exponential Intelligence is not available.';
        }
        return '';
    }

    public function send( array $request, array $context )
    {
        $refusal = $this->refusal( $context );
        if ( $refusal !== '' )
            throw new expImportAiException( $refusal );
        try
        {
            $config = expAiConfig::instance();
            $alias = (string)$config->effectiveModel();
            if ( class_exists( 'expAiWebBudget' ) )
                expAiWebBudget::assertFits( $alias, 'field', $config );
            $provider = $config->registry()->providerFor( $alias );
            expAiDataPolicy::forProvider( $config, $provider->providerId() )
                ->assertClass( isset( $context['class_identifier'] ) ? (string)$context['class_identifier'] : '', isset( $context['section_id'] ) ? (int)$context['section_id'] : 0 );
            $maxTokens = isset( $request['max_tokens'] ) ? max( 100, (int)$request['max_tokens'] ) : 1200;
            $apiRequest = array(
                'system' => (string)$request['system'],
                'messages' => array( array( 'role' => 'user', 'content' => array( array( 'type' => 'text', 'text' => (string)$request['user'] ) ) ) ),
                'max_tokens' => $maxTokens,
            );
            if ( class_exists( 'expAiBudget' ) )
            {
                $budget = new expAiBudget( $config );
                $estimate = $budget->estimate( $alias, $budget->estimateInputTokens( $apiRequest ), $maxTokens );
                $over = $budget->refusal( (int)$estimate['micros'] );
                if ( $over )
                    throw new expImportAiException( 'The budget of Exponential Intelligence has no room for this request: ' . $over['message'] );
            }
            $purpose = isset( $context['purpose'] ) ? preg_replace( '/[^a-z_]/', '', (string)$context['purpose'] ) : '';
            $recorder = new expAiUsageRecorder( $provider, array( 'node_id' => isset( $context['node_id'] ) ? (int)$context['node_id'] : 0,
                                                                  'action' => 'sqliimport:' . $purpose ) );
            $answer = $recorder->send( $apiRequest );
        }
        catch ( expImportAiException $e )
        {
            throw $e;
        }
        catch ( Throwable $e )
        {
            $message = method_exists( $e, 'translatedMessage' ) ? (string)$e->translatedMessage() : $e->getMessage();
            throw new expImportAiException( $message !== '' ? $message : 'The model could not be asked.' );
        }
        $text = '';
        foreach ( isset( $answer['content'] ) ? (array)$answer['content'] : array() as $block )
            if ( is_array( $block ) && isset( $block['type'], $block['text'] ) && $block['type'] === 'text' )
                $text .= $block['text'];
        return array( 'text' => (string)expAiText::stripReasoning( $text ), 'model' => isset( $answer['model'] ) ? (string)$answer['model'] : '' );
    }

    /** @return bool the current user has expai's policy expai/write (any access word but no) */
    protected static function hasPolicy()
    {
        $user = eZUser::currentUser();
        if ( !$user instanceof eZUser || !$user->isRegistered() )
            return false;
        $access = $user->hasAccessTo( 'expai', 'write' );
        return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
    }
}
