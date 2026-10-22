{* Editor step "schedule": when the import runs by itself, in a time zone; the next five times are shown after a save.
   Then the webhook / API trigger of the import (sqliimport/edit/trigger.tpl). *}
<form class="a4-form is-wide" method="post" action={concat( $base, '/(step)/schedule' )|ezurl}>
<fieldset class="a4-field a4-field-wide">
<legend>{'Runs by itself'|i18n( 'extension/sqliimport/edit' )}</legend>
<div class="a4-radios">
{def $sq_sch_kinds = hash( 'none', 'Never (only by hand or from the command line)'|i18n( 'extension/sqliimport/edit' ),
                    'once', 'Once, at the date and time below'|i18n( 'extension/sqliimport/edit' ),
                    'interval', 'Every N minutes, from the date and time below'|i18n( 'extension/sqliimport/edit' ),
                    'hourly', 'Every hour, at the minute of the time below'|i18n( 'extension/sqliimport/edit' ),
                    'daily', 'Every day, at the time below'|i18n( 'extension/sqliimport/edit' ),
                    'weekly', 'Every week, on the weekday and at the time below'|i18n( 'extension/sqliimport/edit' ),
                    'monthly', 'Every month, on the day and at the time below'|i18n( 'extension/sqliimport/edit' ),
                    'cron', 'A cron expression'|i18n( 'extension/sqliimport/edit' ) )}
{foreach $sq_sch_kinds as $k => $label}<label><input type="radio" name="ScheduleKind" value="{$k}"{if eq( $schedule.kind, $k )} checked="checked"{/if} /> {$label|wash}</label>{/foreach}
{undef $sq_sch_kinds}
</div>
</fieldset>
<div class="a4-field">
<label for="sq-runat">{'Date and time'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="datetime-local" id="sq-runat" name="RunAt" value="{$schedule.run_at_local|wash}" />
</div>
<div class="a4-field">
<label for="sq-tz">{'Time zone'|i18n( 'extension/sqliimport/edit' )}</label>
<select id="sq-tz" name="Timezone">{foreach $timezones as $tz}<option value="{$tz|wash}"{if eq( $tz, $schedule.timezone )} selected="selected"{/if}>{$tz|wash}</option>{/foreach}</select>
</div>
<div class="a4-field">
<label for="sq-interval">{'Every how many minutes'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="number" id="sq-interval" name="IntervalMinutes" value="{$schedule.interval_minutes|wash}" min="5" />
<span class="a4-help">{'At least 5.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
<div class="a4-field">
<label for="sq-cron">{'Cron expression'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-cron" name="CronExpr" value="{$schedule.cron_expr|wash}" placeholder="0 2 * * mon-fri" />
<span class="a4-help">{'Minute, hour, day of the month, month, day of the week; *, lists, ranges, steps and names (mon, jan).'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
<div class="a4-field">
<label for="sq-catch">{'When runs were missed'|i18n( 'extension/sqliimport/edit' )}</label>
<select id="sq-catch" name="CatchUp">
<option value="one"{if eq( $schedule.catch_up, 'one' )} selected="selected"{/if}>{'Run once as soon as possible'|i18n( 'extension/sqliimport/edit' )}</option>
<option value="skip"{if eq( $schedule.catch_up, 'skip' )} selected="selected"{/if}>{'Wait for the next time'|i18n( 'extension/sqliimport/edit' )}</option>
</select>
</div>
<div class="a4-field">
<label for="sq-notify">{'E-mail when a run fails'|i18n( 'extension/sqliimport/edit' )}</label>
<textarea id="sq-notify" name="Notify" rows="2">{$schedule.notify|wash}</textarea>
<span class="a4-help">{'Addresses, one per line.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
<div class="a4-field">
<label class="a4-check"><input type="checkbox" name="ScheduleActive" value="1"{if $schedule.is_active} checked="checked"{/if} /> {'The schedule is active'|i18n( 'extension/sqliimport/edit' )}</label>
</div>
{if $schedule.next|count|gt( 0 )}
<div class="a4-field a4-field-wide">
<p><strong>{'Next runs'|i18n( 'extension/sqliimport/edit' )}</strong></p>
<ul class="a4-plain">{foreach $schedule.next as $t}<li>{$t|l10n( 'datetime' )}</li>{/foreach}</ul>
</div>
{/if}
{include uri='design:sqliimport/edit/buttons.tpl' step='schedule' stay=1}
</form>
{if $trigger}{include uri='design:sqliimport/edit/trigger.tpl' base=$base}{/if}
