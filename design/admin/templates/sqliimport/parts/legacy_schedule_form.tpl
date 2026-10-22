{* The form of a 1.x scheduled import on sqliimport/schedules/(legacy)/<id> or /new (it was sqliimport/addscheduled):
   label, handler, options (name=value per line), the first run (date, hour, minute), the frequency and whether it is
   active. The field names are those of 1.x. Input: $legacy_form (expImportLegacy::form()), $legacy_errors (name =>
   message), $legacy_form_handlers (id => name the user may manage). *}
{def $f = $legacy_form
     $base = concat( '/sqliimport/schedules/(legacy)/', cond( gt( $f.id, 0 ), $f.id, 'new' ) )}
<section class="a4-card a4-sqliimport-legacy-form" aria-labelledby="a4-sqliimport-legacy-form-title">
<div class="a4-card-head">
<h2 class="a4-h2" id="a4-sqliimport-legacy-form-title">{if gt( $f.id, 0 )}{'Edit the 1.x scheduled import #%id'|i18n( 'extension/sqliimport',, hash( '%id', $f.id ) )}{else}{'New 1.x scheduled import'|i18n( 'extension/sqliimport' )}{/if}</h2>
<p>{'A 1.x scheduled import requests an import of its handler at the first run and then at every interval of its frequency. To use the schedules of 2.x (cron expressions, time zones, notification), make it an import with ./console ext:sqliimport:migrate-1x.'|i18n( 'extension/sqliimport' )}</p>
</div>
{if $legacy_errors|count|gt( 0 )}
<div class="a4-notice is-bad a4-error-summary" role="alert">
<p><strong>{'Input did not validate'|i18n( 'extension/sqliimport' )}</strong></p>
<ul>{foreach $legacy_errors as $name => $message}<li><a href="#sq-legacy-{$name|downcase|wash}">{$message|wash}</a></li>{/foreach}</ul>
</div>
{/if}
<form class="a4-form is-wide a4-sqliimport-options" method="post" action={$base|ezurl}>
<div class="a4-field">
<label for="sq-legacy-scheduledlabel">{'Import label'|i18n( 'extension/sqliimport' )}</label>
<input type="text" id="sq-legacy-scheduledlabel" name="ScheduledLabel" value="{$f.label|wash}" maxlength="255" />
</div>
<div class="a4-field">
<label for="sq-legacy-importhandler">{'1.x handler'|i18n( 'extension/sqliimport' )}</label>
<select id="sq-legacy-importhandler" name="ImportHandler"{if is_set( $legacy_errors.ImportHandler )} aria-invalid="true"{/if}>
{if and( ne( $f.handler, '' ), is_set( $legacy_form_handlers[$f.handler] )|not )}<option value="{$f.handler|wash}" selected="selected">{$f.handler|wash}</option>{/if}
{foreach $legacy_form_handlers as $id => $name}<option value="{$id|wash}"{if eq( $id, $f.handler )} selected="selected"{/if}>{$name|wash}</option>{/foreach}
</select>
{if is_set( $legacy_errors.ImportHandler )}<span class="a4-field-error">{$legacy_errors.ImportHandler|wash}</span>{/if}
</div>
<div class="a4-field a4-field-wide">
<label for="sq-legacy-importoptions">{'Options (optional)'|i18n( 'extension/sqliimport' )}</label>
<textarea id="sq-legacy-importoptions" name="ImportOptions" rows="4">{$f.options|wash}</textarea>
<span class="a4-help">{'One option per line: name=value'|i18n( 'extension/sqliimport' )}</span>
</div>
<div class="a4-field">
<label for="sq-legacy-scheduleddate">{'First run on'|i18n( 'extension/sqliimport' )}</label>
<input type="date" id="sq-legacy-scheduleddate" name="ScheduledDate" value="{$f.date|wash}" required="required"{if is_set( $legacy_errors.ScheduledDate )} aria-invalid="true"{/if} />
{if is_set( $legacy_errors.ScheduledDate )}<span class="a4-field-error">{$legacy_errors.ScheduledDate|wash}</span>{/if}
</div>
<div class="a4-field">
<label for="sq-legacy-scheduledhour">{'Hour'|i18n( 'extension/sqliimport' )}</label>
<select id="sq-legacy-scheduledhour" name="ScheduledHour">{for 0 to 23 as $h}<option value="{$h}"{if eq( $h, $f.hour )} selected="selected"{/if}>{$h}</option>{/for}</select>
</div>
<div class="a4-field">
<label for="sq-legacy-scheduledminute">{'Minutes'|i18n( 'extension/sqliimport' )}</label>
<select id="sq-legacy-scheduledminute" name="ScheduledMinute">{foreach $f.minutes as $m}<option value="{$m}"{if eq( $m, $f.minute )} selected="selected"{/if}>{$m}</option>{/foreach}</select>
</div>
<fieldset class="a4-field a4-field-wide" id="sq-legacy-scheduledfrequency">
<legend>{'Frequency'|i18n( 'extension/sqliimport/schedulefrequency' )}</legend>
<div class="a4-radios">
{def $kinds = hash( 'none', 'Once'|i18n( 'extension/sqliimport/schedulefrequency' ),
                    'hourly', 'Hourly'|i18n( 'extension/sqliimport/schedulefrequency' ),
                    'daily', 'Daily'|i18n( 'extension/sqliimport/schedulefrequency' ),
                    'weekly', 'Weekly'|i18n( 'extension/sqliimport/schedulefrequency' ),
                    'monthly', 'Monthly'|i18n( 'extension/sqliimport/schedulefrequency' ),
                    'manual', 'Every N minutes'|i18n( 'extension/sqliimport/schedulefrequency' ) )}
{foreach $kinds as $k => $label}<label><input type="radio" name="ScheduledFrequency" value="{$k}"{if eq( $f.frequency, $k )} checked="checked"{/if} /> {$label|wash}</label>{/foreach}
{undef $kinds}
</div>
{if is_set( $legacy_errors.ScheduledFrequency )}<span class="a4-field-error">{$legacy_errors.ScheduledFrequency|wash}</span>{/if}
</fieldset>
<div class="a4-field">
<label for="sq-legacy-manualscheduledfrequency">{'Every how many minutes'|i18n( 'extension/sqliimport' )}</label>
<input type="number" id="sq-legacy-manualscheduledfrequency" name="ManualScheduledFrequency" value="{$f.manual_frequency|wash}" min="5"{if is_set( $legacy_errors.ManualScheduledFrequency )} aria-invalid="true"{/if} />
<span class="a4-help">{'For "Every N minutes"; at least 5.'|i18n( 'extension/sqliimport' )}</span>
{if is_set( $legacy_errors.ManualScheduledFrequency )}<span class="a4-field-error">{$legacy_errors.ManualScheduledFrequency|wash}</span>{/if}
</div>
<div class="a4-field">
<label class="a4-check"><input type="checkbox" name="ScheduledActive" value="1"{if $f.is_active} checked="checked"{/if} /> {'The scheduled import is active'|i18n( 'extension/sqliimport' )}</label>
</div>
<div class="a4-field-wide a4-row">
<button type="submit" class="a4-btn a4-btn-primary" name="LegacyScheduleSaveButton" value="1">{'Save'|i18n( 'extension/sqliimport' )}</button>
<a class="a4-btn" href={'/sqliimport/schedules'|ezurl}>{'Cancel'|i18n( 'extension/sqliimport' )}</a>
</div>
</form>
</section>
{undef $f $base}
