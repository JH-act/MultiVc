<?php

class ilApiVisavid implements ilApiInterface
{

    public function __construct(ilObjMultiVcGUI $a_parent)
    {
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
        return true
    }

    public function isMeetingRecordable(): bool
    {
        return true;
    }
}
