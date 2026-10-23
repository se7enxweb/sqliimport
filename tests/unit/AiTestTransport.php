<?php
/**
 * A transport of the AI assist for the unit tests (expImportAi::$transport): it records every request and answers
 * with the next of the answers it was given, or refuses. No provider, no network.
 */
class AiTestTransport implements expImportAiTransport
{
    /** @var array the requests sent: list of array( request, context ) */
    public $sent = array();

    /** @var string[] the answers, in order */
    public $answers = array();

    /** @var string '' or why the user may not use the assist */
    public $refuse = '';

    public function __construct( array $answers = array() )
    {
        $this->answers = $answers;
    }

    public function refusal( array $context )
    {
        return $this->refuse;
    }

    public function send( array $request, array $context )
    {
        if ( $this->refuse !== '' )
            throw new expImportAiException( $this->refuse );
        $this->sent[] = array( 'request' => $request, 'context' => $context );
        return array( 'text' => (string)array_shift( $this->answers ), 'model' => 'test-model' );
    }

    /** @return string everything the last request sent to the model */
    public function lastText()
    {
        $last = end( $this->sent );
        return $last ? $last['request']['system'] . "\n" . $last['request']['user'] : '';
    }
}
