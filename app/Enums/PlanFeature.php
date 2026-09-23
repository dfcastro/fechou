<?php

namespace App\Enums;

enum PlanFeature: string
{
    /*
    |--------------------------------------------------------------------------
    | Recursos básicos
    |--------------------------------------------------------------------------
    */

    case CLIENT_MANAGEMENT = 'client_management';

    case PUBLIC_QUOTE_LINK = 'public_quote_link';

    case PDF_EXPORT = 'pdf_export';

    case WHATSAPP_SHARING = 'whatsapp_sharing';


    /*
    |--------------------------------------------------------------------------
    | Recursos Pro
    |--------------------------------------------------------------------------
    */

    case QUOTE_VERSIONING = 'quote_versioning';

    case FOLLOW_UP = 'follow_up';

    case NOTIFICATIONS = 'notifications';

    case CUSTOM_BRANDING = 'custom_branding';


    /*
    |--------------------------------------------------------------------------
    | Recursos futuros
    |--------------------------------------------------------------------------
    */

    case ADVANCED_REPORTS = 'advanced_reports';

    case TEAM_MEMBERS = 'team_members';
}