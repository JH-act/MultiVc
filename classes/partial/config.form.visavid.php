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

// default view
// $si = new ilSelectInputGUI($this->plugin_object->txt('conf_meeting_layout'), 'meeting_layout');
// $si->setOptions(
//     array(
//         ilMultiVcConfig::MEETING_LAYOUT_CUSTOM => $this->plugin_object->txt('conf_meeting_layout_' . ilMultiVcConfig::MEETING_LAYOUT_CUSTOM),
//         ilMultiVcConfig::MEETING_LAYOUT_SMART => $this->plugin_object->txt('conf_meeting_layout_' . ilMultiVcConfig::MEETING_LAYOUT_SMART),
//         ilMultiVcConfig::MEETING_LAYOUT_PRESENTATION_FOCUS => $this->plugin_object->txt('conf_meeting_layout_' . ilMultiVcConfig::MEETING_LAYOUT_PRESENTATION_FOCUS),
//         ilMultiVcConfig::MEETING_LAYOUT_VIDEO_FOCUS => $this->plugin_object->txt('conf_meeting_layout_' . ilMultiVcConfig::MEETING_LAYOUT_VIDEO_FOCUS)
//     )
// );
// $si->setInfo($this->plugin_object->txt('info_meeting_layout'));
// $si->setRequired(true);
// $combo->addSubItem($si);



// Raum nur mit Moderator betretbar Auswahlmöglichkeit & Voreinstellung

// chat between participants: toggle in room creation
// $cb = new ilCheckboxInputGUI($pl->txt("private_chat_choose"), "private_chat_choose");
// $cb->setRequired(false);
// $cb->setInfo($pl->txt("private_chat_choose_info"));
// $combo->addSubItem($cb);

// chat between participants: default value
// $cb = new ilCheckboxInputGUI($pl->txt("private_chat_default"), "private_chat_default");
// $cb->setRequired(false);
// $cb->setInfo($pl->txt("private_chat_default_info"));
// $combo->addSubItem($cb);

// recording: toggle in room ceation
// $cb = new ilCheckboxInputGUI($pl->txt("recording_choose"), "recording_choose");
// $cb->setRequired(false);
// $cb->setInfo($pl->txt("recording_choose_info"));
// $combo->addSubItem($cb);

// recording: default value
// $cb = new ilCheckboxInputGUI($pl->txt("recording_default"), "recording_default");
// $cb->setRequired(false);
// $cb->setInfo($pl->txt("recording_default_info"));
// $combo->addSubItem($cb);

// ILIAS einstellung: Aufzeichnung sofort freigeben
// auswahl und voreinstellung

// participant webcam: toggle in room cretion
// participant webcam: default value

// guestlink
// $cb = new ilCheckboxInputGUI($pl->txt("guestlink_choose"), "guestlink_choose");
// $cb->setRequired(false);
// $cb->setInfo($pl->txt("guestlink_choose_info"));
// $combo->addSubItem($cb);

// $cb = new ilCheckboxInputGUI($pl->txt("guestlink_default"), "guestlink_default");
// $cb->setRequired(false);
// $cb->setInfo($pl->txt("guestlink_default_info"));
// $combo->addSubItem($cb);
