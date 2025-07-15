<?php

/*****************
 ** GENERAL
 ****************/

// roles allowed to create 
if(!(ilApiMultiVC::setPluginIniSet()['non_role_based_vc'] ?? 0)) {
    $sm = new ilMultiSelectInputGUI($pl->txt("assigned_roles"), 'assigned_roles');
    $sm->setInfo($pl->txt("assigned_roles_info"));
    #$sm->enableSelectAll(true);
    $sm->setWidth('100');
    $sm->setWidthUnit('%');
    $sm->setHeight('200');
    // $sm->setRequired(true);
    $sm->setOptions($this->object->getAssignableGlobalRoles());
    $combo->addSubItem($sm);
}

// domain of visavid system
$ti = new ilTextInputGUI($pl->txt("vvd_domain"), "svr_public_url");
$ti->setRequired(true);
$ti->setMaxLength(256);
$ti->setSize(60);
$ti->setInfo($pl->txt("vvd_domain_info"));
$combo->addSubItem($ti);

// api token for visavid system
$ti = new ilTextInputGUI($pl->txt("vvd_token"), "svr_salt");
$ti->setRequired(true);
$ti->setMaxLength(256);
$ti->setSize(60);
$ti->setInfo($pl->txt("vvd_token_info"));
$combo->addSubItem($ti);

/*****************
 ** ROOM CONFIG
 ****************/

$si = new ilSelectInputGUI($this->plugin_object->txt('vvd_setting_meeting_layout'), 'meeting_type');
$si->setOptions(
    array(
        'SPEAKER' => $this->plugin_object->txt('vvd_setting_meeting_layout' . '_speaker'),
        'CONFERENCE' => $this->plugin_object->txt('vvd_setting_meeting_layout' . '_conference'),
        'FAVORITE' => $this->plugin_object->txt('vvd_setting_meeting_layout' . '_favorite')
        )
);
$si->setRequired(true);
$combo->addSubItem($si);

$combo->addSubItem(addCheckbox($pl, 'cb_moderated_choose'));
$combo->addSubItem(addCheckbox($pl, 'cb_moderated_default'));
$combo->addSubItem(addCheckbox($pl, 'private_chat_choose'));
$combo->addSubItem(addCheckbox($pl, 'private_chat_default'));

$combo->addSubItem(addCheckbox($pl, 'recording_choose'));
$combo->addSubItem(addCheckbox($pl, 'recording_default'));
$combo->addSubItem(addCheckbox($pl, 'cam_only_for_moderator_choose'));
$combo->addSubItem(addCheckbox($pl, 'cam_only_for_moderator_default'));
$combo->addSubItem(addCheckbox($pl, 'guestlink_choose'));
$combo->addSubItem(addCheckbox($pl, 'guestlink_default'));

function addCheckbox($pl, $setting) {
    $prefix = "vvd_setting_";
    $cb = new ilCheckboxInputGUI($pl->txt($prefix . $setting), $setting);
    $cb->setRequired(false);
    $cb->setInfo($pl->txt($prefix . $setting . "_info"));

    return $cb;
}