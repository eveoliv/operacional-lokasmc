export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

export type OrganizationalUnit = {
    id: number;
    name: string;
};

export type OrganizationUnitType = {
    id: number;
    code: string;
    name: string;
    hierarchy_order: number;
};

export type OrganizationUnit = OrganizationalUnit & {
    organizational_unit_type_id: number;
    parent_id: number | null;
    code: string;
    is_active: boolean;
    archived_at: string | null;
    type: OrganizationUnitType;
    parent: OrganizationalUnit | null;
};

export type AuditLog = {
    id: number;
    action: string;
    auditable_type: string;
    auditable_id: number | null;
    organizational_unit_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    actor: Pick<ManagedUser, 'id' | 'name' | 'email'> | null;
    organizational_unit: OrganizationalUnit | null;
};

export type Person = {
    id: number;
    organizational_unit_id: number;
    user_id: number | null;
    name: string;
    email: string | null;
    phone: string | null;
    document: string | null;
    birth_date: string | null;
    status: 'active' | 'inactive';
    archived_at: string | null;
    organizational_unit: OrganizationalUnit;
    user: Pick<ManagedUser, 'id' | 'name' | 'email'> | null;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    disabled_at: string | null;
    person:
        | (Pick<Person, 'id' | 'name' | 'organizational_unit_id'> & {
              organizational_unit: OrganizationalUnit;
          })
        | null;
};

export type Role = {
    id: number;
    code: string;
    name: string;
    hierarchy_level: number;
};

export type AccessGrant = {
    id: number;
    user_id: number;
    role_id: number;
    organizational_unit_id: number;
    starts_at: string | null;
    ends_at: string | null;
    revoked_at: string | null;
    revoked_reason?: string | null;
    user: Pick<ManagedUser, 'id' | 'name' | 'email'>;
    role: Role;
    organizational_unit: OrganizationalUnit;
};

export type EventStatus =
    | 'draft'
    | 'published'
    | 'in_progress'
    | 'completed'
    | 'cancelled'
    | 'archived';

export type EventAudience = {
    id: number;
    include_descendants: boolean;
    organizational_unit: OrganizationalUnit;
};

export type OperationalEvent = {
    id: number;
    organizational_unit_id: number;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string | null;
    location: string | null;
    capacity: number | null;
    status: EventStatus;
    archived_at: string | null;
    organizational_unit: OrganizationalUnit;
    audiences: EventAudience[];
};

export type Registration = {
    id: number;
    event_id: number;
    person_id: number;
    status: 'active' | 'cancelled';
    source: 'self_service' | 'operator' | 'import';
    registered_at: string;
    cancelled_at: string | null;
    cancellation_reason?: string | null;
    event: Pick<OperationalEvent, 'id' | 'title' | 'organizational_unit_id'>;
    person: Pick<Person, 'id' | 'name' | 'organizational_unit_id'>;
};

export type AttendanceSession = {
    id: number;
    event_id: number;
    name: string;
    starts_at: string;
    ends_at: string | null;
    locked_at: string | null;
    archived_at: string | null;
    event: Pick<OperationalEvent, 'id' | 'title' | 'organizational_unit_id'>;
};
