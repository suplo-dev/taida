<?php

namespace App\Enums;

enum MenuLocation: string
{
    case Header = 'header';
    case Footer = 'footer';

    /** The thin bar above the header: corporate links that are not part of the main navigation. */
    case Utility = 'utility';
}
