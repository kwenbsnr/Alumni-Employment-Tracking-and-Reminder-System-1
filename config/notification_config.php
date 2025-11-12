<?php
// config/notification_config.php

define('NOTIFICATIONAPI_CLIENT_ID', 'ls4kt1i6t2hhh7rxd51k00rjj3');
define('NOTIFICATIONAPI_CLIENT_SECRET', 'rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av');
define('NOTIFICATIONAPI_BASE_URL', 'https://api.notificationapi.com');

// Template mappings
define('TEMPLATE_MAPPINGS', [
    'template_one' => 'alumni_employment_tracking_update_your_profile',
    'template_approved' => 'alumni_employment_tracking_profile_approved', 
    'template_rejected' => 'alumni_employment_tracking_profile_rejected',
    'alum_resubmit_admin_notif' => 'alumni_employment_tracking_resubmission_admin',
    'alum_submit_update_admin_notif' => 'alumni_employment_tracking_annual_update_admin',
    'template_admin_notif' => 'alumni_employment_tracking_new_submission_admin'
]);