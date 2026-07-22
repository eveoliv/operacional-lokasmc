<?php

namespace App\Enums;

enum PermissionCode: string
{
    case OrganizationView = 'organization.view';
    case OrganizationManage = 'organization.manage';
    case PeopleView = 'people.view';
    case PeopleManage = 'people.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case AccessManage = 'access.manage';
    case EventsView = 'events.view';
    case EventsManage = 'events.manage';
    case RegistrationsView = 'registrations.view';
    case RegistrationsManage = 'registrations.manage';
    case AttendanceView = 'attendance.view';
    case AttendanceManage = 'attendance.manage';
    case AttendanceLock = 'attendance.lock';
    case AuditView = 'audit.view';
}
