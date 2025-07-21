<?php

class ilApiVisavid implements ilApiInterface
{
    
    /** @var bool|ilObjCategory $category */
    public $category;
    /** @var bool|ilObjGroup $group */
    private $group;
    private ILIAS\DI\Container $dic;    
    // TODO müsste der (laufende?) Raum sein.. 
    private ?ilObjSession $ilObjSession = null;
    private ?ilObjCourse $course = null;
    private ?ilObjMultiVc $object;
    private ?ilMultiVcConfig $settings;
    /** @var bool|ilObject $parentObj */
    private $parentObj;
    private string $userRole;
    private ?string $roomId = null;

    public function __construct(\ilObjMultiVcGUI $a_parent)
    {
        global $DIC;
        $this->dic = $DIC;

        $this->object = $a_parent->object;
        $this->settings = ilMultiVcConfig::getInstance($this->object->getConnId());
        $this->setUserRole();
    }

    public function getGroup(): bool|ilObject
    {
        return $this->group;
    }

    public function createRoom($id = null) {
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
            'recording' => $isRecordingAllowed
        ];

        $jsonData = json_encode($data);
        $url = $this->buildUrl($id === null ? 'create_room' : 'update_room');
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $id === null ? 'POST' : 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'Content-Length: ' . strlen($jsonData)
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (curl_errno($ch)) {
            throw new \Exception('cURL error accessing Visavid API (id: ' . $id .'): ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($id !== null && $httpCode === 404) {
            // Raum am Visavid-System nicht mehr verfügbar - neu erstellen
            // TODO delete Eintrag in DB, Recordings etc
            return $this->createRoom();    
        }
        else if ($httpCode !== 200) {
            throw new \Exception('Unexpected HTTP Status code accessing Visavid API (id: ' . $id .'): ' . $httpCode);
        }

        $room = json_decode($response, true);
        if($id === null) {
            // Neuer Raum wurde erstellt: Id merken
            // TODO nur noch id persistieren
            $this->persistVisavidRoom($room['id'], $room['dialIn']['moderatorLink'], $room['dialIn']['participantLink']);
        }

        return $room;
    }

    public function getUrlJoinMeeting() {
        // Raum erstellen oder aktualisieren
        $room = $this->createRoom($this->getRoomId());
        // Join-URL aus Raum extrahieren
        return $room['dialIn'][$this->isUserModerator() ? 'moderatorLink' : 'participantLink'];
    }

    public function getInviteUserUrl() {
        // TODO
        return $this->getUrlJoinMeeting();
    }

    public function generateNewGuestlink() {
        // TODO EInwahlcodes neu generieren und neuen TN-Code zurückgeben
        return "";
    }

    private function getRecordingsForSession($sessId) {
        $res = $this->curlGet('recordings', $sessId);
        $recList = [];
        if($res !== null) {
            // map recordings to MultiVC Recording list
            foreach($res as $rec) {
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
        $file = $this->curlGet('download_recording', $recId);
        if ($file === null || strlen($file) === 0) {
            throw new \Exception('Error when downloading visavid recording (roomId: ' . $this->getRoomId() . ', id: ' . $recId .'): ');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="visavid.mp4"');
        header('Content-Length: ' . strlen($file));
        echo $file;
        exit;
    }

    public function deleteRecord($recId) {
        $token = $this->settings->getSvrSalt();
        $url = $this->buildUrl('delete_recording', $recId);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (curl_errno($ch)) {
            throw new \Exception('cURL error deleting Visavid Recording (id: ' . $recId .'): ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            throw new \Exception('Unexpected HTTP Status code deleting Visavid Recording (id: ' . $recId .'): ' . $httpCode);
        }
    }

    /**
     * Load recordings for all recorded sessions of the room
     */
    public function getRecordings() {
        // load room sessions with recordings
        $sess = $this->curlGet('sessions');
        
        $recList = [];
        
        if($sess !== null) {
            // load corresponding recordings
            foreach($sess as $s) {
                $recList = array_merge($recList, $this->getRecordingsForSession($s['id']));
            }
        }
        return $recList;
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
        // TODO
        return true;
    }

    public function isMeetingRecordable(): bool
    {
        // TODO
        return true;
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

    private function getRoomId() {
        if($this->roomId === null) {
            $ilDB = $this->dic->database();
            
            $result = $ilDB->query('SELECT v.id FROM ilias.rep_robj_xmvc_data d INNER JOIN rep_robj_xmvc_vvd v ON d.id = v.ref_id WHERE d.id = ' . $ilDB->quote($this->object->getId(), 'integer')); 
            while ($row = $ilDB->fetchAssoc($result)) {
                $this->roomId = $row['id'];
            }
        }
        return $this->roomId;
    }

    /**
     * persist visavid specific data
     */
    private function persistVisavidRoom($roomId, $moderatorLink, $participantLink) {
        $ilDB = $this->dic->database();

        // remove existing entries for this ref_id
        $ilDB->manipulate('DELETE FROM rep_robj_xmvc_vvd WHERE ref_id = ' . $ilDB->quote($this->object->getId(), 'integer')); 

        // persist new entry
        $a_data = array (
            'id' => array('string', $roomId),
            'ref_id' => array('string', $this->object->getId()),
            'url_mod' => array('string', $moderatorLink),
            'url_par' => array('string', $participantLink),
        );
        $ilDB->insert('rep_robj_xmvc_vvd', $a_data);
    }

    private function buildUrl(string $type, ?string $id = null) {
        $domain = $this->settings->getSvrPublicUrl();
        $apiRoot = $domain . '/api/verwaltung/v1.1.0/rooms/';
        $base = $apiRoot . $this->getRoomId();
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
            case 'download_recording':
            case 'delete_recording': 
                return $base . '/recordings/' . $id; 
            default:
                return null;
        }
    }

    private function curlGet(string $type, ?string $id = null) {
        $token = $this->settings->getSvrSalt();
        $url = $this->buildUrl($type, $id);
        if(!$url) {
            throw new \Exception('Error when building URL for Visavid API request');
        }

        $accept = 'Accept: ' . ($type === 'download_recording' ? 'application/octet-stream' : 'application/json');
        $authorization = 'Authorization: Bearer ' . $token;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [$accept, $authorization]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (curl_errno($ch)) {
            throw new \Exception('cURL error for GET Visavid API (type: ' . $type . ', roomId: ' . $this->getRoomId() . ', id: ' . $id .'): ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($httpCode === 404) {
            return null;
        }
        else if ($httpCode !== 200) {
            throw new \Exception('Unexpected HTTP Status code for GET Visavid API (type: ' . $type . ', roomId: ' . $this->getRoomId() . ', id: ' . $id .'): ' . $httpCode);
        }

        return $type === 'download_recording' ? $response : json_decode($response, true);
    }


////////////////////////////////////////
///    COPIED FROM ilApiBBB
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
