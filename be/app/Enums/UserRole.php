<?php

namespace App\Enums;

enum UserRole: string
{
    /** Full access, including managing other users and site settings. */
    case Admin = 'admin';

    /** May create and edit content, but not manage users or settings. */
    case Editor = 'editor';
}
