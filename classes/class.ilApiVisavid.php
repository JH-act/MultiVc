<?php

class ilApiVisavid implements ilApiInterface
{

    /** @var bool|ilObjCategory */
    public $category;
    /** @var bool|ilObjGroup */
    private $group;
    /** @var ILIAS\DI\Container */
    private $dic;    
    /** @var ilObjSession|null */
    private $ilObjSession = null;
    /** @var ilObjCourse|null */
    private $course = null;
    /** @var ilObjMultiVc|null */
    private $object;
    /** @var ilMultiVcConfig|null */
    private $settings;
    /** @var bool|ilObject */
    private $parentObj;
    /** @var string */
    private $userRole;
    /** @var array|null */
    private $room = null;

    /**
     * @param ilObjMultiVcGUI|ilObjMultiVc $parent_or_object
     */
    public function __construct($parent_or_object)
    {
        global $DIC;
        $this->dic = $DIC;
        
        if ($parent_or_object instanceof \ilObjMultiVcGUI) {
            // normal case: user interacts with the multivc object
            $this->object = $parent_or_object->object;
            $this->setUserRole();
        } else {
            // special cases: user deletes multivc object from trash or uninstalls plugin
            $this->object = $parent_or_object;         
        }
        $this->settings = ilMultiVcConfig::getInstance($this->object->getConnId());
    }

    /**
     * @return bool|ilObject
     */
    public function getGroup()
    {
        return $this->group;
    }

    public function loadRoom($id = null) {
        $domain = $this->settings->getSvrPublicUrl();
        $token = $this->settings->getSvrSalt();

        $title = $this->object->getTitle();
        $desc = $this->object->getDescription();
        $isPrivateChat = $this->object->isPrivateChat();
        $isModerated = $this->object->get_moderated();
        $isRecordingAllowed = $this->object->isRecordingAllowed();
        $isCamOnlyForModerator = $this->object->isCamOnlyForModerator();
        $isGuestlink = $this->object->isGuestlink();

        $data = [
            'name' => $title,
            'description' => $desc,
            'webcam' => !$isCamOnlyForModerator,
            'chat1to1' => $isPrivateChat,
            'enterWithoutModerator' => !$isModerated,
            'recording' => $isRecordingAllowed,
            'requireCode' => true,
            'emojis' => true,
            'raiseHand' => true,
            'attendance' => [
                'attendanceLogging' => true
            ]
        ];


        $jsonData = json_encode($data);
        $type = $id === null ? 'create_room' : 'update_room';
        $url = $this->buildUrl($type, $id);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $id === null ? 'POST' : 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'Content-Length: ' . strlen($jsonData)
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno) {
            $this->logAndShowError('cURL error calling Visavid API (type: ' . $type . ', roomId: ' . $id . ', url: ' . $url . '): ' . $curlError);
            return;
        }

        if ($id !== null && $httpCode === 404) {
            // Raum am Visavid-System nicht mehr verfügbar - Eintrag in DB entfernen und Raum neu erstellen
            $ilDB = $this->dic->database();
            $ilDB->manipulate('DELETE FROM rep_robj_xmvc_vvd WHERE id = ' . $ilDB->quote($id, 'text'));
            return $this->loadRoom();
        }
        elseif ($httpCode !== 200) {
            $this->logAndShowError('Unexpected HTTP status code ' . $httpCode . ' calling Visavid API (type: ' . $type . ', roomId: ' . $id . ', url: ' . $url . ')');
            return;
        }

        $room = json_decode($response, true);
        if($id === null) {
            // Neuer Raum wurde erstellt: Id merken
            $this->persistVisavidRoom($room['id']);
        }
        
        $this->room = $room;
        return $this->room;
    }

    /**
     * Get URL to join visavid room
     * Unlock room if moderator
     */
    public function getUrlJoinMeeting() {
        $room = $this->getRoom();
        if($room === null) {
            $this->logAndShowError("Visavid room not found");
            return;
        }
            
        $baseUrl = $room['dialIn'][$this->isUserModerator() ? 'moderatorLink' : 'participantLink'];
        $name = urlencode($this->dic->user()->firstname . ' ' . $this->dic->user()->lastname);
        $queryParams =  'autoJoin=true&termsConfirmed=true&name=' . $name;

        if($this->isUserModerator()) {
            $this->curlPOST('unlock_room');
        }

        return $baseUrl . (strpos($baseUrl, '?') !== false ? '&' : '?') . $queryParams;
    }

    public function getInviteUserUrl() {
        $room = $this->getRoom();
        if($room === null) {
            $this->logAndShowError("Visavid room not found");
            return;
        }
        return $room['dialIn']['participantLink'];
    }

    /**
     * Generate new login codes for the room
     * Attention: The room will be locked during this process, which will stop the conference if active
     */
    public function generateNewGuestlink() {
        $this->curlPOST('lock_room');
        $this->curlPOST('new_codes');
        $this->curlPOST('unlock_room');

        // reset room: needs to be reloaded for new codes
        $this->room = null;
    }

    /**
     * Exports Visavid attendance data as json
     */
    public function exportAttendanceData() {
        $roomId = $this->getRoomId();
        if ($roomId === null) {
            $this->logAndShowError("Visavid roomId not found");

            return;
        }

        $attendance = $this->curlGet('attendance_export', $roomId);
        if(empty($attendance)) {
            return;
        }

        $attendance_json = json_encode($attendance, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = "visavid_anwesenheiten.json";

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($attendance_json));
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $attendance_json;
        exit;
    }

    /**
     * Load attendance data for all sessions of the room
     */
    public function getAttendanceData() {
        $roomId = $this->getRoomId();
        if ($roomId === null) {
            $this->logAndShowError("Visavid roomId not found");
            return;
        }

        $attendance = $this->curlGet('attendance', $roomId);
        if(empty($attendance)) {
            return [];
        }

        $timezone = new DateTimeZone('Europe/Berlin');
        $data = [];
        foreach ($attendance as $session) {
            foreach ($session['participants'] as $participant) {
                $name = $participant['name'] ?? 'Unbekannt';
                foreach ($participant['attendancePeriods'] as $period) {
                    $join = new DateTime($period['start']);
                    $join->setTimezone($timezone);
                    $joinFormatted = $join->format('d.m.Y H:i \U\h\r');
                    $leave = new DateTime($period['end']);
                    $leave->setTimezone($timezone);
                    $leaveFormatted = $leave->format('d.m.Y H:i \U\h\r');

                    $data[] = [
                        'DISPLAY_NAME' => $name,
                        'JOIN_TIME' => $joinFormatted,
                        'LEAVE_TIME' => $leaveFormatted,
                        'IS_MODERATOR' => 'Teilnehmer',
                        'USER' => '',
                        'REF' => '',
                        'START_TIME' => '',
                        'MEETING_ID' => '',
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Load recordings for all recorded sessions of the room
     */
    public function getRecordings() {
        // load room sessions with recordings
        $roomId = $this->getRoomId();
        if ($roomId === null) {
            $this->logAndShowError("Visavid roomId not found");
            return;
        }

        $sess = $this->curlGet('sessions', $roomId);
        if(empty($sess)) {
            return [];
        }

        // load corresponding recordings
        $recList = [];
        foreach($sess as $s) {
            $recList = array_merge($recList, $this->getRecordingsForSession($roomId, $s['id']));
        }
        return $recList;
    }

    private function getRecordingsForSession($roomId, $sessId) {
        if ($roomId === null) {
            $this->logAndShowError("Visavid roomId not found");
            return;
        }

        $res = $this->curlGet('recordings', $roomId, $sessId);
        if(empty($res)) {
            return [];
        }

        $recList = [];
        foreach($res as $rec) {
            // ignore pending or unknown recordings
            if (isset($rec['status']) && $rec['status'] === 'READY') {
                // map recordings to MultiVC Recording list
                $recList[$rec['id']] = [
                    'START_TIME' => (new DateTimeImmutable($rec['start']))->getTimestamp(),
                    'END_TIME' => (new DateTimeImmutable($rec['stop']))->getTimestamp(),
                    'SESSION_ID' => $rec['id'],
                    'FILE_SIZE' => $rec['size'],
                    'ROOM_ID' => $this->getRoomId()
                ];
            }
        }
        return $recList;
    }

    public function downloadRecording($recId) {
        $roomId = $this->getRoomId();
        if ($roomId === null) {
            $this->logAndShowError("Visavid roomId not found");
            return;
        }

        $file = $this->curlGet('download_recording', $roomId, $recId);
        if (empty($file) || strlen($file) === 0) {
            throw new \Exception('Error when downloading visavid recording (roomId: ' . $roomId . ', id: ' . $recId .'): ');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="visavid.mp4"');
        header('Content-Length: ' . strlen($file));
        echo $file;
        exit;
    }

    public function deleteRoom() {
        $this->curlDelete('delete_room');
    }

    public function deleteRecord($recId) {
        $this->curlDelete('delete_recording', $recId);
    }

    public function hasSessionObject(): bool
    {
        return !!$this->ilObjSession;
    }

    public function isUserAdmin(): bool
    {
        $userLiaRoles = $this->dic->rbac()->review()->assignedRoles($this->dic->user()->getId());
        $found = $this->dic->access()->checkAccessOfUser($this->dic->user()->getId(), 'write', 'showContent', $this->object->getRefId());
        return false !== $found;
    }

    public function isUserModerator(): bool
    {
        return $this->userRole === 'moderator';
    }

    public function isModeratedMeeting(): bool
    {
        return $this->object->get_moderated();
    }

    public function isMeetingRecordable(): bool
    {
        return $this->object->isRecordingAllowed();
    }

    /**
     * always true as far as ILIAS is concerned: Anyone can start the meeting, so act as if moderator is present
     */
    public function isModeratorPresent(): bool
    {
        return true;
    }

    /**
     * always true: Anyone can start the meeting
     */
    public function isMeetingStartable(): bool
    {
        return true;
    }

    /**
     * always true: Anyone can start the meeting
     */
    public function isMeetingRunning(): bool
    { 
        return true;
    }

    /**
     * always true: Anyone can start the meeting
     */
    public function isValidAppointmentUser(): bool
    {
        return true;
    }

    /**
     * Tries to load roomId from DB or room
     * Does not load/create the room!
     */
    private function getRoomId() {
        if($this->room === null) {
            $ilDB = $this->dic->database();
            $roomId = null;
            $result = $ilDB->query('SELECT id FROM rep_robj_xmvc_vvd WHERE ref_id = ' . $ilDB->quote($this->object->getId(), 'integer')); 
            while ($row = $ilDB->fetchAssoc($result)) {
                $roomId = $row['id'];
            }

            return $roomId;
        }
        return $this->room['id'];
    }

    private function getRoom() {
        if($this->room === null) {
            $roomId = $this->getRoomId();
            return $this->loadRoom($roomId);
        }
        return $this->room;
    }

    /**
     * persist visavid specific data
     */
    private function persistVisavidRoom($roomId) {
        $ilDB = $this->dic->database();

        // remove existing entries for this ref_id
        $ilDB->manipulate('DELETE FROM rep_robj_xmvc_vvd WHERE ref_id = ' . $ilDB->quote($this->object->getId(), 'integer')); 

        // persist new entry
        $a_data = array (
            'id' => array('string', $roomId),
            'ref_id' => array('string', $this->object->getId()),
        );
        $ilDB->insert('rep_robj_xmvc_vvd', $a_data);
    }

    private function buildUrl(string $type, ?string $roomId = null, ?string $id = null) {
        if(!$roomId && $type !== 'create_room') {
            throw new \Exception("Missing roomId for Visavid API-type '$type'");
        }
        if(!$id && ($type === 'recordings' || $type === 'delete_recording')) {
            throw new \Exception("Missing id for Visavid API-type '$type' for roomId '$roomId'");
        }

        $domain = rtrim($this->settings->getSvrPublicUrl(), '/');
        $apiRoot = $domain . '/api/verwaltung/v1.2.0/rooms';
        $base = $apiRoot . ($roomId !== null ? '/' . $roomId : '');

        switch ($type) {
            case 'create_room':
                return $apiRoot;
            case 'update_room':
            case 'delete_room':
                return $base;
            case 'sessions': 
                return $base . '/sessions/with-recordings';
            case 'recordings': 
                return $base . '/sessions/' . $id . '/recordings';
            case 'attendance':
            case 'attendance_export':
                return $base . '/attendance';
            case 'download_recording':
            case 'delete_recording': 
                return $base . '/recordings/' . $id; 
            case 'new_codes':
                return $base . '/actions/newcodes';
            case 'lock_room':
                return $base . '/actions/lock';
            case 'unlock_room':
                return $base . '/actions/unlock';
            default:
                return null;
        }
    }

    private function curlGet(string $type, ?string $roomId = null, ?string $id = null) {
        $token = $this->settings->getSvrSalt();
        $url = $this->buildUrl($type, $roomId, $id);

        $accept = 'Accept: ' . ($type === 'download_recording' ? 'application/octet-stream' : 'application/json');
        $authorization = 'Authorization: Bearer ' . $token;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [$accept, $authorization]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno) {
            $this->dic->logger()->root()->error('cURL error calling Visavid API (type: ' . $type . ', roomId: ' . $roomId . ', url: ' . $url . '): ' . $curlError);
            return null;
        }

        if ($httpCode === 404) {
            return null;
        } elseif ($httpCode !== 200) {
            $this->dic->logger()->root()->error('Unexpected HTTP status code ' . $httpCode . ' calling Visavid API (type: ' . $type . ', roomId: ' . $roomId . ', url: ' . $url . ')');
            return null;
        }

        return $type === 'download_recording' ? $response : json_decode($response, true);
    }

    private function curlPOST(string $type) {
        $token = $this->settings->getSvrSalt();
        $roomId = $this->getRoomId();
        $url = $this->buildUrl($type, $roomId);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno) {
            $this->logAndShowError('cURL error calling Visavid API (type: ' . $type . ', roomId: ' . $roomId . ', url: ' . $url . '): ' . $curlError);
        }

        if ($httpCode !== 200) {
            $this->logAndShowError('Unexpected HTTP status code ' . $httpCode . ' calling Visavid API (type: ' . $type . ', roomId: ' . $roomId . ', url: ' . $url . ')');
        }
    }

    private function curlDelete(string $type, ?string $id = null) {
        $token = $this->settings->getSvrSalt();
        $roomId = $this->getRoomId();
        if ($roomId === null) {
            $this->dic->logger()->root()->error('Unknown roomId: skip contacting Visavid system');
            return;
        }

        $url = $this->buildUrl($type, $roomId, $id);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno) {
            $this->dic->logger()->root()->error('cURL error calling Visavid API (type: ' . $type . ', roomId: ' . $roomId . ', url: ' . $url . '): ' . $curlError);
        } 
        elseif ($httpCode !== 200 && $httpCode !== 404) {
            // do nothing for 404 - resource does not exist in visavid system (anymore)
            $this->dic->logger()->root()->error('Unexpected HTTP status code ' . $httpCode . ' calling Visavid API (type: ' . $type . ', roomId: ' . $roomId . ', url: ' . $url . ')');
        }
    }

    private function logAndShowError(string $msg = null, ?bool $keep = false) {
        $this->dic->logger()->root()->error($msg);
        // Nur loggen und Fehler anzeigen wäre schöner, aber so kann man Fehler leichter einsehen / schneller nachvollziehen
        throw new \Exception($msg);
        // $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', 'Bei der Kommunikation mit Visavid ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.', $keep);
    }

////////////////////////////////////////
///    FOLLOWING CODE IDENTICAL WITH ilApiBBB
////////////////////////////////////////

    /**
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     */
    private function setUserRole(): void
    {
        switch (true) {
            case $this->isInCourseOrGroup() && $this->isAdminOrTutor():
            case $this->isInCourseOrGroup() && $this->dic->access()->checkAccessOfUser($this->dic->user()->getId(), 'write', 'showContent', $this->object->getRefId()):
            case !$this->isInCourseOrGroup() && $this->dic->access()->checkAccessOfUser($this->dic->user()->getId(), 'write', 'showContent', $this->object->getRefId()):
            case !$this->isModeratedMeeting():
                $this->userRole = 'moderator';
                break;
            default:
                $this->userRole = 'participant';
        }
    }

        /**
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     */
    private function isInCourseOrGroup(): bool
    {
        if(!$this->parentObj) {
            $this->setParentObj();
        }

        if(!$this->category) {
            return true;
        }

        return false;
    }

    /**
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     */
    private function isAdminOrTutor(): bool
    {
        global $DIC;

        if($this->isInCourseOrGroup()) {
            $userLiaRoles = $DIC->rbac()->review()->assignedRoles($DIC->user()->getId());
            if(!!$this->course) {
                $found = array_search($this->course->getDefaultAdminRole(), $userLiaRoles);
                $found = false !== $found ? true : array_search($this->course->getDefaultTutorRole(), $userLiaRoles);
                return false !== $found;
            }
            if(!!$this->group) {
                $found = array_search($this->group->getDefaultAdminRole(), $userLiaRoles);
                return false !== $found;
            }
        }
        return false;
    }

    /** 
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     */
    private function setParentObj(): void
    {
        global $DIC;

        $parent = [];
        $path = array_reverse($DIC->repositoryTree()->getPathFull($this->object->getRefId()));
        $keys = array_keys($path);
        #$parent = $path[$keys[1]];
        foreach($path as $key => $node) {
            if(false !== array_search($node['type'], ['crs', 'grp', 'cat'])) {
                $parent = $node;
                break;
            }
        }

        if(!$parent['ref_id']) {
            #$this->dic->ui()->mainTemplate()->setMessage('error', 'No RefId given', true);
            $this->dic->ui()->mainTemplate()->setOnScreenMessage('failure', 'No RefId given', true);
            $this->dic->ctrl()->redirectToURL(ILIAS_HTTP_PATH);
        }
        $this->parentObj = ilObjectFactory::getInstanceByRefId($parent['ref_id']);
        switch(true) {
            case 'crs' === $parent['type'] && !$this->course:
                $this->course = $this->parentObj;
                break;
            case 'grp' === $parent['type'] && !$this->group:
                $this->group = $this->parentObj;
                break;
            case 'cat' === $parent['type'] && !$this->category:
                $this->category = $this->parentObj;
                break;
        }


        //$appAss = ilCalendarCategoryAssignments::_getAssignedAppointments([$this->parentObj->getId()]);
        //var_dump($appAss); exit;

        // check for ilObjSession
        if(false !== array_search($parent['type'], ['crs', 'grp'])) {
            $events = ilEventItems::getEventsForItemOrderedByStartingTime($this->object->getRefId());
            if((bool) sizeof($events)) {
                $now = date('U');
                //var_dump($now);
                foreach($events as $eventId => $eventStart) {
                    if(!$this->ilObjSession) {
                        /** @var ilObjSession $tmpSessObj */
                        $tmpSessObj = ilObjectFactory::getInstanceByObjId($eventId);

                        $dTplId = ilDidacticTemplateObjSettings::lookupTemplateId($tmpSessObj->getRefId());
                        //echo $dTplId; exit;
                        //if( (int)$dTplId === 2 )
                        if(!(bool) $dTplId && $now >= $eventStart) {
                            //var_dump( ilSessionAppointment::_lookupAppointment($eventId)['fullday'] ); exit;
                            $event = ilSessionAppointment::_lookupAppointment($eventId);
                            $end = (bool) $event['fullday']
                                ? $eventStart + 60 * 60 * 24
                                : $event['end'];
                            if($now < $end) {
                                $this->ilObjSession = $tmpSessObj; // ilObjectFactory::getInstanceByObjId($eventId);
                                //var_dump( $this->ilObjSession->getMembersObject()->getEventParticipants()->getUserId() ); exit;
                                //var_dump( $this->ilObjSession->isUserRegistered($this->dic->user()->getId()) ); exit;
                            }
                        }
                    }
                }
            }
        }
    }
}