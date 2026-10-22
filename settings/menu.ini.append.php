<?php /* #?ini charset="utf-8"?

[NavigationPart]
Part[sqliimportnavigationpart]=Import management

[TopAdminMenu]
Tabs[]=sqliimport

[Topmenu_sqliimport]
# Icon - the icon admin4 draws before the tab's name: a name of design.ini [AdminDesignSettings] IconList[]
Icon=upload
NavigationPartIdentifier=sqliimportnavigationpart
Name=Imports
Tooltip=Manage your imports
# The name and tooltip are translated in this context (translations/<locale>/translation.ts)
TranslationContext=extension/sqliimport/menu
URL[]
URL[default]=sqliimport/list
Enabled[]
Enabled[default]=true
Enabled[browse]=false
Enabled[edit]=false
Shown[]
Shown[default]=true
Shown[navigation]=true
Shown[browse]=false

# Tabs policy on admin interface in 4.3
PolicyList[]=sqliimport/manageimports

*/ ?>
