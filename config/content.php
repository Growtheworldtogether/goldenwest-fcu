<?php

// Editable public-page content. Each field's default is what the site shows before anyone edits it.
// Tokens in text: {name}, {short_name}, {phone}, {email}, {hours}, {tel} follow the Branding page.
return [
    'pages' => [
        'home' => [
            'label' => 'Home page',
            'url' => '/',
            'view' => 'home',
            'fields' => [
                'slides' => [
                    'label' => 'Hero slide',
                    'type' => 'list',
                    'min' => 1,
                    'max' => 5,
                    'fields' => [
                        'title' => [
                            'label' => 'headline',
                            'type' => 'text',
                            'max' => 70,
                        ],
                        'text' => [
                            'label' => 'text',
                            'type' => 'textarea',
                            'max' => 200,
                        ],
                        'button' => [
                            'label' => 'button',
                            'type' => 'button',
                        ],
                        'image' => [
                            'label' => 'photo',
                            'type' => 'image',
                            'optional' => true,
                        ],
                    ],
                    'default' => [
                        [
                            'title' => 'Banking built around your life.',
                            'text' => 'Open a checking or savings account in about ten minutes, with no opening deposit and no fee to join.',
                            'button' => [
                                'label' => 'Open an account',
                                'url' => '/join',
                            ],
                            'image' => null,
                        ],
                        [
                            'title' => 'Save toward what matters most.',
                            'text' => 'Set a goal with a target and a date, and watch every deposit move you closer to it.',
                            'button' => [
                                'label' => 'Explore savings',
                                'url' => '/accounts#savings',
                            ],
                            'image' => null,
                        ],
                        [
                            'title' => 'Borrow with the full picture.',
                            'text' => 'See your rate, every monthly payment and the total cost of your loan before you apply.',
                            'button' => [
                                'label' => 'Explore loans',
                                'url' => '/loans',
                            ],
                            'image' => null,
                        ],
                    ],
                ],
            ],
        ],
        'about' => [
            'label' => 'About',
            'url' => '/about',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Owned by the people we serve',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Since 1958, {short_name} has put members first. We are a not-for-profit cooperative, so our earnings return to you.',
                ],
                'cta_title' => [
                    'label' => 'Call-to-action headline',
                    'type' => 'text',
                    'max' => 80,
                    'default' => 'Become a member',
                ],
                'cta_text' => [
                    'label' => 'Call-to-action text',
                    'type' => 'text',
                    'max' => 160,
                    'default' => 'Joining is free and takes about ten minutes.',
                    'optional' => true,
                ],
                'cta_button' => [
                    'label' => 'Call-to-action button',
                    'type' => 'button',
                    'default' => [
                        'label' => 'Open an account',
                        'url' => '/join',
                    ],
                ],
                'story' => [
                    'label' => 'Our story',
                    'type' => 'richtext',
                    'default_file' => 'about-story.html',
                    'max' => 6000,
                ],
            ],
        ],
        'accessibility' => [
            'label' => 'Accessibility',
            'url' => '/accessibility',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Accessibility',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'We want everyone to be able to bank with us.',
                ],
                'updated' => [
                    'label' => 'Last updated (date shown on the page)',
                    'type' => 'text',
                    'max' => 40,
                    'default' => '',
                    'optional' => true,
                ],
                'body' => [
                    'label' => 'Page text',
                    'type' => 'richtext',
                    'headings' => true,
                    'default_file' => 'accessibility-body.html',
                    'max' => 30000,
                ],
            ],
        ],
        'accounts' => [
            'label' => 'Checking & Savings',
            'url' => '/accounts',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Checking and savings',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Accounts that stay simple: no fee to join, no minimum balance on checking, and rates that reward you for saving.',
                ],
                'cta_title' => [
                    'label' => 'Call-to-action headline',
                    'type' => 'text',
                    'max' => 80,
                    'default' => 'Ready to get started?',
                ],
                'cta_text' => [
                    'label' => 'Call-to-action text',
                    'type' => 'text',
                    'max' => 160,
                    'default' => 'Open your account online in about ten minutes.',
                    'optional' => true,
                ],
                'cta_button' => [
                    'label' => 'Call-to-action button',
                    'type' => 'button',
                    'default' => [
                        'label' => 'Open an account',
                        'url' => '/join',
                    ],
                ],
            ],
        ],
        'appointments' => [
            'label' => 'Schedule an Appointment',
            'url' => '/appointments',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Schedule an appointment',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Meet with a banker about opening an account, a loan, or your business. We will call to confirm a time that works for you.',
                ],
            ],
        ],
        'business' => [
            'label' => 'Business',
            'url' => '/business',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Business banking for the West',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Checking, lending and cash management built for owners who would rather run a company than manage a bank.',
                ],
                'cta_title' => [
                    'label' => 'Call-to-action headline',
                    'type' => 'text',
                    'max' => 80,
                    'default' => 'Let us look at your numbers',
                ],
                'cta_text' => [
                    'label' => 'Call-to-action text',
                    'type' => 'text',
                    'max' => 160,
                    'default' => 'Schedule a no-obligation conversation with a business banker.',
                    'optional' => true,
                ],
                'cta_button' => [
                    'label' => 'Call-to-action button',
                    'type' => 'button',
                    'default' => [
                        'label' => 'Schedule a meeting',
                        'url' => '/appointments',
                    ],
                ],
            ],
        ],
        'careers' => [
            'label' => 'Careers',
            'url' => '/careers',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Build a career that matters',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Join a team of more than 2,400 people who help members reach their goals every day.',
                ],
                'cta_title' => [
                    'label' => 'Call-to-action headline',
                    'type' => 'text',
                    'max' => 80,
                    'default' => 'Ready to apply?',
                ],
                'cta_text' => [
                    'label' => 'Call-to-action text',
                    'type' => 'text',
                    'max' => 160,
                    'default' => 'Tell us about yourself and our recruiting team will be in touch.',
                    'optional' => true,
                ],
                'cta_button' => [
                    'label' => 'Call-to-action button',
                    'type' => 'button',
                    'default' => [
                        'label' => 'Contact recruiting',
                        'url' => '/contact',
                    ],
                ],
            ],
        ],
        'community' => [
            'label' => 'Community',
            'url' => '/community',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Investing where we live',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Strong communities make strong members. Here is how {short_name} gives back across the West.',
                ],
            ],
        ],
        'contact' => [
            'label' => 'Contact Us',
            'url' => '/contact',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Contact us',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'We are here to help. Call, send a message or schedule time with a banker.',
                ],
            ],
        ],
        'digital-banking' => [
            'label' => 'Digital banking',
            'url' => '/digital-banking',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Bank anywhere, any time',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Deposit a check, pay a bill, move money and lock a lost card from your phone or computer, day or night.',
                ],
            ],
        ],
        'disclosures' => [
            'label' => 'Disclosures',
            'url' => '/disclosures',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Disclosures',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Important information about fees, funds availability and rates.',
                ],
                'updated' => [
                    'label' => 'Last updated (date shown on the page)',
                    'type' => 'text',
                    'max' => 40,
                    'default' => 'November 17, 2026',
                    'optional' => true,
                ],
                'body' => [
                    'label' => 'Page text',
                    'type' => 'richtext',
                    'headings' => true,
                    'default_file' => 'disclosures-body.html',
                    'max' => 30000,
                ],
            ],
        ],
        'help' => [
            'label' => 'Help Center',
            'url' => '/help',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'How can we help?',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Find quick answers below, or reach Customer Care any time at {phone}.',
                ],
            ],
        ],
        'join' => [
            'label' => 'Join',
            'url' => '/join',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Become a member',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Joining is free and opening an account takes about ten minutes. No opening deposit is needed for checking.',
                ],
            ],
        ],
        'learn' => [
            'label' => 'Insights & Stories',
            'url' => '/learn',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Insights & stories',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Practical guidance on money, loans and staying safe, written by the people who help our members every day.',
                ],
            ],
        ],
        'loans' => [
            'label' => 'Loans',
            'url' => '/loans',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Loans with clear terms',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Fixed rates, fixed payments and no prepayment penalty. Know exactly what you will pay before you sign.',
                ],
                'cta_title' => [
                    'label' => 'Call-to-action headline',
                    'type' => 'text',
                    'max' => 80,
                    'default' => 'Check your options today',
                ],
                'cta_text' => [
                    'label' => 'Call-to-action text',
                    'type' => 'text',
                    'max' => 160,
                    'default' => 'Applying will not affect your credit score.',
                    'optional' => true,
                ],
                'cta_button' => [
                    'label' => 'Call-to-action button',
                    'type' => 'button',
                    'default' => [
                        'label' => 'Start an application',
                        'url' => '/join',
                    ],
                ],
            ],
        ],
        'privacy' => [
            'label' => 'Privacy',
            'url' => '/privacy',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Privacy',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'How we collect, use and protect your personal information.',
                ],
                'updated' => [
                    'label' => 'Last updated (date shown on the page)',
                    'type' => 'text',
                    'max' => 40,
                    'default' => 'November 17, 2026',
                    'optional' => true,
                ],
                'body' => [
                    'label' => 'Page text',
                    'type' => 'richtext',
                    'headings' => true,
                    'default_file' => 'privacy-body.html',
                    'max' => 30000,
                ],
            ],
        ],
        'rates' => [
            'label' => 'Rates',
            'url' => '/rates',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Today\'s rates',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => '',
                ],
                'cta_title' => [
                    'label' => 'Call-to-action headline',
                    'type' => 'text',
                    'max' => 80,
                    'default' => 'Put your money to work',
                ],
                'cta_text' => [
                    'label' => 'Call-to-action text',
                    'type' => 'text',
                    'max' => 160,
                    'default' => 'Open a savings account or certificate in about ten minutes.',
                    'optional' => true,
                ],
                'cta_button' => [
                    'label' => 'Call-to-action button',
                    'type' => 'button',
                    'default' => [
                        'label' => 'Open an account',
                        'url' => '/join',
                    ],
                ],
            ],
        ],
        'security' => [
            'label' => 'Security & Fraud Center',
            'url' => '/security',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Security & Fraud Center',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'Protecting your money is our most important job. Learn how we help, and how you can stay a step ahead of fraud.',
                ],
            ],
        ],
        'terms' => [
            'label' => 'Terms of Use',
            'url' => '/terms',
            'fields' => [
                'heading' => [
                    'label' => 'Page headline',
                    'type' => 'text',
                    'max' => 90,
                    'default' => 'Terms of Use',
                ],
                'intro' => [
                    'label' => 'Intro text under the headline',
                    'type' => 'textarea',
                    'max' => 300,
                    'default' => 'The terms that apply when you use our website and digital banking.',
                ],
                'updated' => [
                    'label' => 'Last updated (date shown on the page)',
                    'type' => 'text',
                    'max' => 40,
                    'default' => 'November 17, 2026',
                    'optional' => true,
                ],
                'body' => [
                    'label' => 'Page text',
                    'type' => 'richtext',
                    'headings' => true,
                    'default_file' => 'terms-body.html',
                    'max' => 30000,
                ],
            ],
        ],
    ],
];
