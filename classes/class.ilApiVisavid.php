<?php

class ilApiVisavid implements ilApiInterface
{
    // TODO
    private ?ilObjSession $ilObjSession = null;

    public function __construct(ilObjMultiVcGUI $a_parent)
    {
    }



    public function createRoom() { 

        $url = 'xy';
        $token = 'abc';

        $data = [
            "name" => "Test"
        ];
        $jsonData = json_encode($data);


        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'Content-Length: ' . strlen($jsonData)
        ]);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        } else {
            $data = json_decode($response, true);
            var_dump($data);
        } 

        curl_close($ch);
    }






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
