<?php /* #?ini charset="utf-8"?

[NavigationPart]
Part[sqliimportnavigationpart]=Import management

[TopAdminMenu]
# A siteaccess that resets Tabs[] in its own menu.ini.append.php (a line "Tabs[]" with no value) must list
# Tabs[]=sqliimport there itself, or the tab is not shown.
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

# The left menu of the Imports tab (parts/sqliimport/menu.tpl draws it with the administration's shared menu
# template, parts/ini_menu.tpl, and in admin4 with its width switch). A tab or link is shown only to a user who may
# open its address (menu.ini [MenuAccessSettings] CheckViewAccess): the views accept the 2.x and the 1.x policies.
[Leftmenu_sqliimport]
Name=Imports
Links[]
Links[dashboard]=sqliimport/list
Links[statistics]=sqliimport/statistics
Links[new]=sqliimport/edit/new
Links[schedules]=sqliimport/schedules
Links[catalogue]=sqliimport/catalogue
Links[settings]=sqliimport/settings
Links[active]=sqliimport/list/(status)/active
Links[failed]=sqliimport/list/(status)/failed
Links[queue]=expqueue/dashboard
LinkNames[]
LinkNames[dashboard]=Overview
LinkNames[statistics]=Statistics
LinkNames[new]=New import
LinkNames[schedules]=Schedules
LinkNames[catalogue]=Catalogue
LinkNames[settings]=Settings
LinkNames[active]=Running runs
LinkNames[failed]=Failed runs
LinkNames[queue]=Job queue
Groups[]
Groups[]=runs
GroupNames[]
GroupNames[runs]=Imports and runs
GroupLinks_runs[]
GroupLinks_runs[]=dashboard
GroupLinks_runs[]=statistics
GroupLinks_runs[]=new
GroupLinks_runs[]=active
GroupLinks_runs[]=failed
GroupLinks_runs[]=schedules
GroupLinks_runs[]=queue
GroupLinks_runs[]=catalogue
GroupLinks_runs[]=settings

*/ ?>
