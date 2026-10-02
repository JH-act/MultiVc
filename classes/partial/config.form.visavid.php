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

// authentication method
$si = new ilSelectInputGUI($pl->txt("vvd_auth_method"), "vvd_auth_method");
$si->setOptions([
    'token' => $pl->txt("vvd_auth_method_token"),
    'client_credentials' => $pl->txt("vvd_auth_method_client_credentials")
]);
$si->setInfo($pl->txt("vvd_auth_method_info"));
$si->addCustomAttribute('onchange="ilMultiVcToggleVvdAuthMethod(this.value)"');
$combo->addSubItem($si);

// secret: static api token (auth method: token) or client secret (auth method: client_credentials)
$pi = new ilPasswordInputGUI($pl->txt("vvd_secret"), "svr_salt");
$pi->setSkipSyntaxCheck(true);
$pi->setRequired(false);
$pi->setMaxLength(256);
$pi->setSize(6);
$pi->setInfo($pl->txt("vvd_secret_info"));
$pi->setRetype(false);
$combo->addSubItem($pi);

// access token url (auth method: client_credentials)
$ti = new ilTextInputGUI($pl->txt("vvd_token_url"), "vvd_token_url");
$ti->setRequired(false);
$ti->setMaxLength(256);
$ti->setSize(60);
$ti->setInfo($pl->txt("vvd_token_url_info"));
$combo->addSubItem($ti);

// client id (auth method: client_credentials)
$ti = new ilTextInputGUI($pl->txt("vvd_client_id"), "svr_username");
$ti->setRequired(false);
$ti->setMaxLength(256);
$ti->setSize(60);
$ti->setInfo($pl->txt("vvd_client_id_info"));
$combo->addSubItem($ti);

// scope (auth method: client_credentials, optional)
$ti = new ilTextInputGUI($pl->txt("vvd_scope"), "vvd_scope");
$ti->setRequired(false);
$ti->setMaxLength(256);
$ti->setSize(60);
$ti->setInfo($pl->txt("vvd_scope_info"));
$combo->addSubItem($ti);

// show/hide fields depending on the selected auth method
$this->dic->ui()->mainTemplate()->addOnLoadCode('
    window.ilMultiVcToggleVvdAuthMethod = function (mode) {
        var m2m = mode === "client_credentials";
        ["vvd_token_url", "svr_username", "vvd_scope"].forEach(function (id) {
            var el = document.getElementById("il_prop_cont_" + id);
            if (el) { el.style.display = m2m ? "" : "none"; }
        });
    };
    var vvdAuthSelect = document.getElementById("vvd_auth_method");
    window.ilMultiVcToggleVvdAuthMethod(vvdAuthSelect ? vvdAuthSelect.value : "token");
');

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

$combo->addSubItem(addCheckbox($pl, 'hide_username_logs'));
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