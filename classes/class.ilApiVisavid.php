<?php

class ilApiVisavid implements ilApiInterface
{
    
    /** @var bool|ilObjCategory $category */
    public $category;
    private ILIAS\DI\Container $dic;    
    // TODO müsste der (laufende?) Raum sein.. 
    private ?ilObjSession $ilObjSession = null;
    private ?ilObjCourse $course = null;
    private ?ilObjMultiVc $object;
    private ?ilMultiVcConfig $settings;
    /** @var bool|ilObject $parentObj */
    private $parentObj;
    private string $userRole;

    public function __construct(\ilObjMultiVcGUI $a_parent)
    {
        global $DIC;
        $this->dic = $DIC;
        
        // attention: $a_parent->object might be null when ilApiVisavid is constructed at vc object creation
        if($a_parent !== null) {
            $this->object = $a_parent->object;
            $this->settings = ilMultiVcConfig::getInstance($this->object->getConnId());
        }
        
        $this->setUserRole();
    }

    public function createRoom(string $domain, string $token, string $name, string $description) { 
        $data = [
            "name" => $name,
            "description" => $description
        ];
        $jsonData = json_encode($data);
        $url = $domain . '/api/verwaltung/v1.1.0/rooms';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'Content-Length: ' . strlen($jsonData)
        ]);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

        $response = curl_exec($ch);
        curl_close($ch);

        if (curl_errno($ch)) {
            throw new \Exception('cURL error accessing Visavid API: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            throw new \Exception("Unexpected HTTP Status code accessing Visavid API: $httpCode");
        }

        return json_decode($response, true);
    }

    public function getUrlJoinMeeting() { // TODO inkl unterscheidung mod
        $ilDB = $this->dic->database();

        // TODO Hier mit join alle daten aus data und vvd abrufen und damit werte in klasse setzen wie mod link etc
        // dann in constructor verschieben und urljoinmeeting gibt nur noch die tn/mod url zurück
        $result = $ilDB->query("SELECT * FROM rep_robj_xmvc_data WHERE id = " . $ilDB->quote($this->object->getRefId(), "integer"));
        while ($record = $ilDB->fetchAssoc($result)) {
            var_dump($record["id"]);exit;
            // $this->setPrivateChat($settings->isPrivateChatDefault());
        }
        return "";
    }

    // TODO
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
