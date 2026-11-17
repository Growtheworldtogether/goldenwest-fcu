<?php

return [
    // Rates shown on the Rates page. APY = savings and certificates, APR = loans.
    'rates' => [
        'deposit' => [
            ['name' => 'Everyday Checking', 'rate' => '0.05%', 'detail' => 'APY on all balances', 'min' => '$0'],
            ['name' => 'Golden Savings', 'rate' => '3.55%', 'detail' => 'APY on balances of $1 or more', 'min' => '$0'],
            ['name' => 'Goal Savings', 'rate' => '3.75%', 'detail' => 'APY when you save toward a goal', 'min' => '$0'],
            ['name' => 'Money Market', 'rate' => '4.10%', 'detail' => 'APY on balances of $10,000 or more', 'min' => '$10,000'],
            ['name' => '12-Month Certificate', 'rate' => '4.30%', 'detail' => 'APY, fixed for the term', 'min' => '$500'],
            ['name' => '24-Month Certificate', 'rate' => '4.05%', 'detail' => 'APY, fixed for the term', 'min' => '$500'],
        ],
        'loan' => [
            ['name' => 'New Auto Loan', 'rate' => '5.49%', 'detail' => 'APR, up to 72 months', 'min' => '$5,000'],
            ['name' => 'Used Auto Loan', 'rate' => '6.19%', 'detail' => 'APR, up to 72 months', 'min' => '$5,000'],
            ['name' => 'Personal Loan', 'rate' => '8.99%', 'detail' => 'APR, 12 to 60 months', 'min' => '$1,000'],
            ['name' => 'Share-Secured Loan', 'rate' => '4.25%', 'detail' => 'APR, up to 60 months', 'min' => '$500'],
            ['name' => 'Business Term Loan', 'rate' => '8.25%', 'detail' => 'APR, 12 to 84 months', 'min' => '$10,000'],
        ],
    ],

    // Searchable page index, also used for the sitemap.
    'pages' => [
        ['Personal', 'Checking and savings accounts', '/accounts', 'checking savings money market certificate account open debit card overdraft'],
        ['Personal', 'Loans', '/loans', 'loan auto car personal borrow apply payment apr'],
        ['Personal', 'Rates', '/rates', 'rates apy apr certificate interest'],
        ['Personal', 'Digital banking', '/digital-banking', 'online mobile app bill pay transfer deposit zelle alerts login'],
        ['Business', 'Business banking', '/business', 'business checking lending cash management payroll merchant'],
        ['Help', 'Security & Fraud Center', '/security', 'fraud scam phishing report lost stolen card identity password'],
        ['Help', 'Help Center', '/help', 'help faq password reset contest decision dispute held transaction'],
        ['Help', 'Contact Us', '/contact', 'contact phone email customer care message'],
        ['Help', 'Schedule an Appointment', '/appointments', 'appointment meet branch banker schedule'],
        ['About', 'About Golden West', '/about', 'about history story members since 1958 leadership'],
        ['About', 'Community', '/community', 'community reinvestment giving volunteer scholarship'],
        ['About', 'Careers', '/careers', 'careers jobs hiring work benefits'],
        ['About', 'Insights & Stories', '/learn', 'articles learn insights guides tips'],
        ['Membership', 'Join Golden West', '/join', 'join membership open eligibility become a member'],
        ['Membership', 'Log in', '/login', 'login sign in online banking'],
        ['Legal', 'Privacy', '/privacy', 'privacy data information sharing'],
        ['Legal', 'Terms of Use', '/terms', 'terms use website agreement'],
        ['Legal', 'Disclosures', '/disclosures', 'disclosures fees funds availability'],
        ['Legal', 'Accessibility', '/accessibility', 'accessibility ada screen reader'],
    ],
];
