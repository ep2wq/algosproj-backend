<?php

// Only the rules the public endpoints use (register, login, submit); every other message
// falls back to the framework's English. Attribute names are the public form fields.
return [
    'accepted' => 'يجب قبول :attribute.',
    'boolean' => 'يجب أن تكون قيمة :attribute إما true أو false.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صحيحًا.',
    'in' => 'قيمة :attribute المحددة غير صالحة.',
    'max' => [
        'string' => 'يجب ألا يزيد طول :attribute على :max من الأحرف.',
    ],
    'min' => [
        'string' => 'يجب ألا يقل طول :attribute عن :min من الأحرف.',
    ],
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'unique' => 'القيمة المدخلة في :attribute مستخدمة من قبل.',

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'language' => 'لغة البرمجة',
        'code' => 'الكود',
        'remember' => 'تذكرني',
        'accepted_terms' => 'شروط الاستخدام وسياسة الخصوصية',
    ],
];
