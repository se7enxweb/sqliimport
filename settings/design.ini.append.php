<?php /* #?ini charset="utf-8"?

# The templates of the admin module "sqliimport" live in design/admin/templates/sqliimport/.
[ExtensionSettings]
DesignExtensions[]=sqliimport

# The look of the Import management tab: the administration's shared components (.a4-*) and tokens (--a4-*), and
# what they cannot do in design/admin/stylesheets/sqliimport.css (.a4-sqliimport-*). No template carries inline CSS
# (tests/unit/StylingRulesTest.php).
[StylesheetSettings]
BackendCSSFileList[]=sqliimport.css

# The question of an action that needs a confirmation (data-a4-confirm) in a design without admin4's a4_actions.js.
# No template carries an inline script or event handler attribute (tests/unit/ScriptRulesTest.php).
[JavaScriptSettings]
BackendJavaScriptList[]=sqliimport_confirm.js
# The live run page (sqliimport::runstatus through Exp.io of expui)
BackendJavaScriptList[]=sqliimport_admin.js


# The bell of the administration's header (admin4 and admin4l, from Exponential 6.0.15): the user's unread notices of
# runs that ended, loaded from sqliimport::notices. Leave it out with HeaderItems[] in a siteaccess's design.ini.
[AdminHeaderSettings]
HeaderItems[]=sqliimport/header_bell.tpl

*/ ?>
