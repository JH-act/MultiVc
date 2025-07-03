<?php

class ilApiVisavid implements ilApiInterface
{
    // TODO müsste der (laufende?) Raum sein.. 
    private ?ilObjSession $ilObjSession = null;
    private ILIAS\DI\Container $dic;    

    // attention: $a_parent->object might be null when ilApiVisavid is constructed at vc object creation
    public function __construct(\ilObjMultiVcGUI $a_parent)
    {
        global $DIC;
        $this->dic = $DIC;
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

    // TODO
    public function hasSessionObject(): bool
    {
        return !!$this->ilObjSession;
    }

    public function isUserAdmin(): bool
    {
        return true;
    }

    public function isModeratorPresent(): bool
    {
        return true;
    }

    public function isMeetingStartable(): bool
    {
        return true;
    }

    public function isMeetingRunning(): bool
    {
        return true;
    }

    public function isValidAppointmentUser(): bool
    {
        return true;
    }

    public function isUserModerator(): bool
    {
        return true;
    }

    public function isModeratedMeeting(): bool
    {
        return true;
    }

    public function isMeetingRecordable(): bool
    {
        return true;
    }
}
