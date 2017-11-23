<?php

class sfValidatorEmail extends sfValidatorRegex
{
    const REGEX_EMAIL = '/^([^@\sа-яё]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})$/i';

    protected function configure($options = [], $messages = [])
    {
        parent::configure($options, $messages);

        $this->setOption('pattern', self::REGEX_EMAIL);
    }
}
