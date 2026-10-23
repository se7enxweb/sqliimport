<?php
/**
 * File containing the expImportAiTransport interface and the expImportAiException class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The way the AI assist of 7x SQLI Import (expImportAi) reaches a model. The installation's transport is
 * expImportAiExpaiTransport: every request goes through the extension expai, under its master switch, permissions,
 * data policy, limits, budget, usage log and audit; there is no provider key or call of its own. The unit tests use a
 * double that answers without a provider.
 */
interface expImportAiTransport
{
    /**
     * Why the current user cannot use the assist ('' when the user can). Asked before any button is shown.
     *
     * @param array $context purpose (mapping, class, explain, summary), import_id, run_id, node_id, section_id,
     *                       class_identifier
     * @return string
     */
    public function refusal( array $context );

    /**
     * Sends one request.
     *
     * @param array $request system (string), user (string: the whole question, already minimised), max_tokens (int)
     * @param array $context as for refusal()
     * @return array text (the answer, without reasoning), model (string)
     * @throws expImportAiException the request was refused or failed
     */
    public function send( array $request, array $context );
}

/** The AI assist could not answer: not available, refused (switch, permission, data policy, budget) or unreadable. */
class expImportAiException extends expImportException
{
}
