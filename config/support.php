<?php

// Support tickets: what members can ask about, canned replies for staff, and how long a resolved ticket stays open.
return [
    'categories' => [
        'account' => 'My account',
        'transaction' => 'A transaction',
        'deposit' => 'A deposit',
        'card' => 'A card',
        'loan' => 'A loan',
        'technical' => 'The website or app',
        'other' => 'Something else',
    ],

    'priorities' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'],

    'statuses' => [
        'open' => 'Open',
        'awaiting_staff' => 'Needs a reply',
        'awaiting_member' => 'Waiting for the member',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ],

    // A resolved ticket closes by itself after this many days, and a member can no longer reply to a closed one.
    'auto_close_days' => 7,
    'max_attachments' => 3,

    // Tokens: {first_name}, {short_name}, {phone}, {hours}. Staff can edit the text before sending.
    'canned' => [
        'received' => ['label' => 'We received your request', 'body' => "Hi {first_name}, thank you for writing to us. We have your request and are looking into it now. We will reply here as soon as we have an update."],
        'more_info' => ['label' => 'We need more information', 'body' => "Hi {first_name}, to help with this we need a little more information. Could you tell us the date it happened and anything you noticed? You can add photos or a PDF to your reply."],
        'check_review' => ['label' => 'Check deposit under review', 'body' => "Hi {first_name}, your check deposit is in review. A person looks at every check, usually the same business day. Once it is approved the money appears in your account and you will see it in your activity."],
        'photo_retake' => ['label' => 'Please retake the check photos', 'body' => "Hi {first_name}, the photos of your check were hard to read. Please deposit it again with all four corners visible, in good light, with the back signed. Sorry for the extra step."],
        'id_review' => ['label' => 'Identity documents', 'body' => "Hi {first_name}, we review identity documents within one business day. If we need anything else, we will tell you here and on the Verify your identity page."],
        'resolved' => ['label' => 'Resolved, anything else?', 'body' => "Hi {first_name}, we believe this is taken care of, so we are marking it resolved. If something is still wrong, reply here and we will pick it right back up."],
        'call' => ['label' => 'Please call us', 'body' => "Hi {first_name}, for your protection we would like to talk this through by phone. Please call Customer Care at {phone}. We are open {hours}."],
    ],
];
