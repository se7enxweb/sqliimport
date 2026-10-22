{* Editor step "schedule", part 2: the webhook / API trigger of the import (expImportEditor::saveTrigger(), doc/triggers.md).
   Input: $trigger (triggerForForm(): never a secret, only whether there is one), $trigger_revealed (a new signing secret or
   API key, in this answer only), $base, $errors. Each action is its own POST form (the form token is added to every POST
   form); rotating and revoking ask first (data-a4-confirm). No inline CSS or script. *}
<section class="a4-section" aria-labelledby="sq-trig-title">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-trig-title">{'Started by another system (webhook or API)'|i18n( 'extension/sqliimport/edit' )}</h2></div>
<p class="a4-intro">{'Another system can start this import with a signed call or an API key, and may bring the data in the call. Off until it is switched on here.'|i18n( 'extension/sqliimport/edit' )}</p>
{if $trigger.globally_enabled|not}<p class="a4-notice is-warn" role="status">{'Triggers are switched off for every import ([TriggerSettings] Trigger=disabled): calls are answered with 404.'|i18n( 'extension/sqliimport/edit' )}</p>{/if}

{if $trigger_revealed}
<div class="a4-notice is-warn" role="alert">
<p class="a4-notice-title">{if eq( $trigger_revealed.kind, 'key' )}{'API key "%label"'|i18n( 'extension/sqliimport/edit',, hash( '%label', $trigger_revealed.label ) )|wash}{else}{'Signing secret'|i18n( 'extension/sqliimport/edit' )}{/if}</p>
<code class="a4-sqliimport-secret" id="sq-trig-revealed">{$trigger_revealed.value|wash}</code>
<p class="a4-meta">{'Copy it now and keep it where the calling system keeps its secrets: it is shown only this once and is not stored in a readable form. If it is lost, create a new one.'|i18n( 'extension/sqliimport/edit' )}</p>
</div>
{/if}

<form class="a4-form is-wide" method="post" action={concat( $base, '/(step)/schedule' )|ezurl}>
<input type="hidden" name="Step" value="schedule" />
<input type="hidden" name="TriggerAction" value="save" />
<div class="a4-field a4-field-wide">
<label class="a4-check"><input type="checkbox" name="TriggerEnabled" value="1"{if $trigger.is_enabled} checked="checked"{/if} /> {'The trigger is on'|i18n( 'extension/sqliimport/edit' )}</label>
</div>
<div class="a4-field">
<label for="sq-trig-user">{'Run as user (ID)'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="number" id="sq-trig-user" name="TriggerRunUserID" value="{$trigger.run_user_id|wash}" min="0" />
{if is_set( $errors.TriggerRunUserID )}<span class="a4-field-error">{$errors.TriggerRunUserID|wash}</span>{/if}
<span class="a4-help">{'A user with the policy Run imports (or Manage imports) for this import; a triggered run never runs as anonymous. The robot user of the processing step still takes precedence.'|i18n( 'extension/sqliimport/edit' )}{if ne( $trigger.run_user_login, '' )} {'Now: %login.'|i18n( 'extension/sqliimport/edit',, hash( '%login', $trigger.run_user_login ) )|wash}{/if}</span>
</div>
<div class="a4-field">
<label class="a4-check"><input type="checkbox" id="sq-trig-push" name="TriggerAllowPush" value="1"{if $trigger.allow_push} checked="checked"{/if}{if $trigger.takes_data|not} disabled="disabled"{/if} /> {'A call may bring the data'|i18n( 'extension/sqliimport/edit' )}</label>
{if is_set( $errors.TriggerAllowPush )}<span class="a4-field-error">{$errors.TriggerAllowPush|wash}</span>{/if}
<span class="a4-help">{if $trigger.takes_data}{'The body of the call (in the format of the import, up to %mb MB) is read instead of the source, for that run only.'|i18n( 'extension/sqliimport/edit',, hash( '%mb', $trigger.max_body_megabytes ) )|wash}{else}{'Only an import that reads a file can take its data from a call; this one reads its source itself.'|i18n( 'extension/sqliimport/edit' )}{/if}</span>
</div>
<div class="a4-field">
<label for="sq-trig-ref">{'Signing secret by name (optional)'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-trig-ref" name="TriggerSecretRef" value="{$trigger.secret_ref|wash}" placeholder="env:IMPORT_HOOK_SECRET" />
{if is_set( $errors.TriggerSecretRef )}<span class="a4-field-error">{$errors.TriggerSecretRef|wash}</span>{/if}
<span class="a4-help">{'Empty: the extension makes a secret and shows it once. A name: a secret of at least 32 characters kept in Secrets, the settings or the environment.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
<div class="a4-field a4-field-wide">
<p><strong>{'Address'|i18n( 'extension/sqliimport/edit' )}</strong></p>
<p class="a4-sqliimport-trigger-url">POST {$trigger.url|wash}</p>
<p class="a4-meta">{'A signed call signs the path %path, the time and the body (doc/triggers.md); an API key is sent as Authorization: Bearer or X-Api-Key. Call a siteaccess that does not ask to sign in.'|i18n( 'extension/sqliimport/edit',, hash( '%path', $trigger.path ) )|wash}</p>
</div>
<div class="a4-toolbar is-bottom">
<button type="submit" class="a4-btn a4-btn-primary" name="TriggerSaveButton" value="1">{'Save the trigger'|i18n( 'extension/sqliimport/edit' )}</button>
</div>
</form>

<h3 class="a4-h3">{'Signing secret'|i18n( 'extension/sqliimport/edit' )}</h3>
{if ne( $trigger.secret_ref, '' )}
<p class="a4-meta">{'The trigger signs with the secret %name.'|i18n( 'extension/sqliimport/edit',, hash( '%name', $trigger.secret_ref ) )|wash}</p>
{elseif $trigger.has_signing}
<p class="a4-meta">{'Made on %date.'|i18n( 'extension/sqliimport/edit',, hash( '%date', $trigger.sign_created|l10n( 'shortdatetime' ) ) )|wash}</p>
<form class="a4-sqliimport-inline" method="post" action={concat( $base, '/(step)/schedule' )|ezurl} data-a4-confirm="{'Make a new signing secret? The current one stops working at once.'|i18n( 'extension/sqliimport/edit' )|wash}">
<input type="hidden" name="Step" value="schedule" />
<input type="hidden" name="TriggerAction" value="rotate" />
<button type="submit" class="a4-btn a4-btn-small"><span class="a4-icon a4-bi-arrow-clockwise" aria-hidden="true"></span> {'New signing secret'|i18n( 'extension/sqliimport/edit' )}</button>
</form>
{else}
<p class="a4-meta">{'None yet: it is made when the trigger is switched on.'|i18n( 'extension/sqliimport/edit' )}</p>
{/if}

<h3 class="a4-h3">{'API keys'|i18n( 'extension/sqliimport/edit' )}</h3>
{if $trigger.keys|count|eq( 0 )}
<p class="a4-meta">{'No key yet. A key may start runs and read their status.'|i18n( 'extension/sqliimport/edit' )}</p>
{else}
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-label="{'API keys'|i18n( 'extension/sqliimport/edit' )|wash}">
<table class="a4-table">
<thead><tr><th>{'Name'|i18n( 'extension/sqliimport/edit' )}</th><th>{'Key ID'|i18n( 'extension/sqliimport/edit' )}</th><th>{'Created'|i18n( 'extension/sqliimport/edit' )}</th><th>{'Last used'|i18n( 'extension/sqliimport/edit' )}</th><th><span class="a4-sr-only">{'Actions'|i18n( 'extension/sqliimport/edit' )}</span></th></tr></thead>
<tbody>
{foreach $trigger.keys as $sq_key}
<tr>
<td>{$sq_key.label|wash}</td>
<td><code>sqk_{$sq_key.key_id|wash}_…</code></td>
<td class="a4-nowrap">{$sq_key.created|l10n( 'shortdatetime' )}</td>
<td class="a4-nowrap">{if gt( $sq_key.last_used, 0 )}{$sq_key.last_used|l10n( 'shortdatetime' )}{else}<span class="a4-meta">{'never'|i18n( 'extension/sqliimport/edit' )}</span>{/if}</td>
<td class="a4-end">{if gt( $sq_key.revoked, 0 )}<span class="a4-badge">{'revoked'|i18n( 'extension/sqliimport/edit' )}</span>{else}
<form class="a4-sqliimport-inline" method="post" action={concat( $base, '/(step)/schedule' )|ezurl} data-a4-confirm="{'Revoke this key? Calls with it are refused at once.'|i18n( 'extension/sqliimport/edit' )|wash}">
<input type="hidden" name="Step" value="schedule" />
<input type="hidden" name="TriggerAction" value="revoke" />
<input type="hidden" name="TriggerKeyID" value="{$sq_key.id|wash}" />
<button type="submit" class="a4-btn a4-btn-small a4-btn-outline-danger">{'Revoke'|i18n( 'extension/sqliimport/edit' )}</button>
</form>{/if}</td>
</tr>
{/foreach}
</tbody>
</table>
</div>
{/if}
<form class="a4-form is-wide" method="post" action={concat( $base, '/(step)/schedule' )|ezurl}>
<input type="hidden" name="Step" value="schedule" />
<input type="hidden" name="TriggerAction" value="newkey" />
<div class="a4-field">
<label for="sq-trig-label">{'Name of a new key'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-trig-label" name="TriggerKeyLabel" value="" maxlength="100" placeholder="{'e.g. the shop system'|i18n( 'extension/sqliimport/edit' )|wash}" />
{if is_set( $errors.TriggerKeyLabel )}<span class="a4-field-error">{$errors.TriggerKeyLabel|wash}</span>{/if}
</div>
<div class="a4-toolbar is-bottom">
<button type="submit" class="a4-btn"><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'Create an API key'|i18n( 'extension/sqliimport/edit' )}</button>
</div>
</form>
</section>
