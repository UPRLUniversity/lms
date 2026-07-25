<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Curated Nigerian reference data for realistic demo seeding — authentic first names
 * and surnames across the country's three largest ethnic groups (Yoruba, Igbo,
 * Hausa/Fulani), plus phone numbers in the local +234 format. Names are drawn from a
 * single ethnicity per person so pairings read coherently (e.g. "Adebayo Ogunleye",
 * "Chidi Okafor", "Ibrahim Bello") rather than as random mashups.
 *
 * Reference data, not generated lorem — so the People list, rosters, certificates,
 * announcements and grade book all read like a genuine Nigerian university roll.
 */
final class Nigeria
{
    /** @var array<string, array{male: array<int,string>, female: array<int,string>, surnames: array<int,string>}> */
    private const GROUPS = [
        'yoruba' => [
            'male' => [
                'Adebayo', 'Oluwaseun', 'Babatunde', 'Olamide', 'Ayodeji', 'Kayode', 'Oluwafemi',
                'Segun', 'Gbenga', 'Damilare', 'Rotimi', 'Kunle', 'Adewale', 'Tobiloba', 'Oladapo',
                'Ayomide', 'Boluwatife', 'Ifedayo', 'Olumide', 'Temitope',
            ],
            'female' => [
                'Adenike', 'Folake', 'Bukola', 'Oluwakemi', 'Titilayo', 'Yetunde', 'Funmilayo',
                'Omolara', 'Modupe', 'Abisola', 'Ronke', 'Simisola', 'Damilola', 'Ayotunde',
                'Iyabo', 'Morayo', 'Toluwani', 'Adeola', 'Bisola', 'Temiloluwa',
            ],
            'surnames' => [
                'Adeyemi', 'Ogunleye', 'Afolabi', 'Balogun', 'Adeleke', 'Bankole', 'Oladipo',
                'Ogundipe', 'Alabi', 'Ademola', 'Olaoye', 'Owoeye', 'Ogunbanjo', 'Fashola',
                'Adeniyi', 'Oyelaran', 'Akinyemi', 'Odukoya',
            ],
        ],
        'igbo' => [
            'male' => [
                'Chukwuemeka', 'Chidi', 'Emeka', 'Ikenna', 'Obinna', 'Nnamdi', 'Uchenna',
                'Chibuzo', 'Ifeanyi', 'Kelechi', 'Chinedu', 'Ebuka', 'Okechukwu', 'Chima',
                'Ugochukwu', 'Chukwuma', 'Nonso', 'Somtochukwu', 'Tochukwu', 'Chidera',
            ],
            'female' => [
                'Adaeze', 'Chioma', 'Ngozi', 'Amaka', 'Ifeoma', 'Chinwe', 'Nneka', 'Chidinma',
                'Ebele', 'Oluchi', 'Ijeoma', 'Chiamaka', 'Nkechi', 'Obiageli', 'Chidiogo',
                'Uchechi', 'Nwakaego', 'Ogechi', 'Munachi', 'Kamsiyochi',
            ],
            'surnames' => [
                'Okafor', 'Okonkwo', 'Nwosu', 'Eze', 'Okoye', 'Nwachukwu', 'Obi', 'Anyanwu',
                'Okoro', 'Nnaji', 'Ezenwa', 'Onyeka', 'Madu', 'Ibe', 'Nwankwo', 'Uzoma',
                'Chukwu', 'Onwuka',
            ],
        ],
        'hausa' => [
            'male' => [
                'Ibrahim', 'Musa', 'Sani', 'Abubakar', 'Yusuf', 'Aliyu', 'Bello', 'Umar',
                'Aminu', 'Suleiman', 'Nasir', 'Kabiru', 'Hassan', 'Habibu', 'Danladi', 'Garba',
                'Sadiq', 'Auwal', 'Murtala', 'Bashir',
            ],
            'female' => [
                'Aisha', 'Fatima', 'Zainab', 'Halima', 'Aminata', 'Hauwa', 'Maryam', 'Hadiza',
                'Rukayya', 'Balkisu', 'Zahra', 'Safiya', 'Ramatu', 'Firdausi', 'Bilkisu',
                'Amina', 'Hafsat', 'Zulaihat', 'Jamila', 'Rahmatu',
            ],
            'surnames' => [
                'Abubakar', 'Bello', 'Danjuma', 'Lawal', 'Gambo', 'Tanko', 'Shehu', 'Ahmadu',
                'Sanusi', 'Yaro', 'Maikudi', 'Dogara', 'Aliyu', 'Suleiman', 'Ibrahim', 'Usman',
                'Danbatta', 'Gwarzo',
            ],
        ],
    ];

    /**
     * A coherent full Nigerian name, e.g. "Chidi Okafor" or "Aisha Bello".
     */
    public static function fullName(): string
    {
        $group = self::GROUPS[Arr::random(array_keys(self::GROUPS))];
        $first = Arr::random(Arr::random([$group['male'], $group['female']]));

        return trim($first).' '.Arr::random($group['surnames']);
    }

    /**
     * A Nigerian mobile number in the local format, e.g. "+234 803 123 4567".
     */
    public static function phone(): string
    {
        $prefix = Arr::random(['803', '805', '806', '807', '810', '813', '814', '816', '703', '706', '810', '901', '902', '904', '905', '915']);

        return '+234 '.$prefix.' '.mt_rand(100, 999).' '.mt_rand(1000, 9999);
    }

    /**
     * A short, plausible academic title for an instructor.
     */
    public static function academicTitle(): string
    {
        return Arr::random(['Lecturer I', 'Lecturer II', 'Senior Lecturer', 'Associate Professor', 'Reader']);
    }

    /**
     * A slug-safe email local-part derived from a full name (kept @uprl.test in demos).
     */
    public static function emailFrom(string $fullName): string
    {
        return Str::of($fullName)->lower()->replace(' ', '.')->ascii()->value().'@uprl.test';
    }
}
