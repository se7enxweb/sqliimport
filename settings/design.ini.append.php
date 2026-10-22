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

*/ ?>
