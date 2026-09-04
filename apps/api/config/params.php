<?php

return [
    'senderEmail' => getenv('MAIL_FROM') ?: 'noreply@ownlane.app',
    'senderName' => getenv('MAIL_FROM_NAME') ?: 'OwnLane',
    'frontendUrl' => getenv('FRONTEND_URL') ?: 'http://localhost:3000',
];
