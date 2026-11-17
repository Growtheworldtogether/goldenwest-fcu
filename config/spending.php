<?php

// Spending categories members see in Insights, and how a card merchant is placed in one.
return [
    'categories' => [
        'groceries' => 'Groceries', 'dining' => 'Dining and coffee', 'fuel' => 'Fuel and transport', 'shopping' => 'Shopping',
        'bills' => 'Bills and utilities', 'loans' => 'Loan payments', 'health' => 'Health', 'entertainment' => 'Entertainment',
        'cash_fees' => 'Cash and fees', 'other' => 'Other',
    ],
    // Lowercase words in a merchant name that point to a category.
    'merchant_words' => [
        'groceries' => ['grocery', 'market', 'foods', 'supermarket', 'safeway', 'kroger', 'costco', 'trader joe', 'aldi', 'publix'],
        'dining' => ['coffee', 'cafe', 'restaurant', 'pizza', 'grill', 'kitchen', 'starbucks', 'chipotle', 'mcdonald', 'burger', 'taco', 'diner'],
        'fuel' => ['fuel', 'gas ', 'petrol', 'transit', 'parking', 'shell', 'chevron', 'exxon', 'uber', 'lyft'],
        'shopping' => ['clothing', 'books', 'store', 'outlet', 'shop', 'amazon', 'target', 'home depot', 'walmart', 'best buy', 'ikea'],
        'bills' => ['internet', 'electric', 'water', 'insurance', 'phone', 'wireless', 'pacific gas', 'pg&e', 'utility'],
        'health' => ['pharmacy', 'clinic', 'dental', 'health', 'cvs', 'walgreens'],
        'entertainment' => ['cinema', 'theater', 'games', 'music', 'stream', 'netflix', 'spotify', 'hulu'],
    ],
    'biller_categories' => ['utilities' => 'Utilities', 'credit_card' => 'Credit card', 'housing' => 'Rent or mortgage', 'insurance' => 'Insurance', 'phone_internet' => 'Phone and internet', 'other' => 'Other'],
    'biller_to_spend' => ['utilities' => 'bills', 'credit_card' => 'bills', 'housing' => 'bills', 'insurance' => 'bills', 'phone_internet' => 'bills', 'other' => 'other'],
];
