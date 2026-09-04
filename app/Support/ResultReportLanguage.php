<?php

namespace App\Support;

/**
 * The language a printed report is produced in.
 *
 * English and Urdu, and the labels for both live here rather than in the
 * Blade views so the two can never drift apart: a heading added in one
 * language is a missing key in the other, which shows up immediately
 * instead of silently printing an English word on an Urdu report.
 *
 * It began with the result reports and still carries their name. The
 * admission test passed students notice shares it deliberately: same two
 * languages, same two engines, same letterhead, so a second language system
 * would only be a second place for the two languages to fall out of step.
 *
 * Only labels and headings are translated. The data itself - a student's
 * name, a class name, a grade letter, a date - is printed as it is stored.
 * Translating stored data would mean inventing a second copy of it.
 *
 * Urdu is written right to left and in a script dompdf cannot shape, so a
 * report in Urdu is rendered by a different engine. Which one is decided
 * from the language here, and the direction and font below are what the
 * layout reads.
 */
class ResultReportLanguage
{
    public const ENGLISH = 'en';

    public const URDU = 'ur';

    /**
     * The languages a report may be printed in.
     *
     * @var array<int, string>
     */
    public const LANGUAGES = [
        self::ENGLISH,
        self::URDU,
    ];

    /**
     * How each language names itself, for the selector on the page.
     *
     * @var array<string, string>
     */
    public const NAMES = [
        self::ENGLISH => 'English',
        self::URDU => 'اردو',
    ];

    /**
     * Reduce a requested language to one that exists, or to English.
     *
     * A report has to be printed in something, so an absent or unrecognised
     * value falls back rather than failing: a hand-edited query string
     * gets an English report, not an error page.
     */
    public static function normalize(mixed $language): string
    {
        return in_array($language, self::LANGUAGES, true) ? $language : self::ENGLISH;
    }

    /**
     * Resolve a requested language against a configured default.
     *
     * An explicit choice always wins. The reports pages offer English and
     * Urdu side by side, and a link that says English must produce an
     * English report whatever the institution has set as its default.
     *
     * Only when nothing recognisable was asked for does the default apply -
     * which is where the Settings preference gets its say. The default is
     * itself normalised, so a stored value this class does not know still
     * ends at English rather than at nothing.
     *
     * This stays here rather than in the Settings model on purpose: which
     * languages exist, and which one an unrecognised value falls back to,
     * are this class's business and must not be decided in two places.
     */
    public static function resolve(mixed $requested, mixed $default = null): string
    {
        if (in_array($requested, self::LANGUAGES, true)) {
            return $requested;
        }

        return self::normalize($default);
    }

    /**
     * Determine whether a language is written right to left.
     */
    public static function isRtl(string $language): bool
    {
        return $language === self::URDU;
    }

    /**
     * Get the text direction the layout should set.
     */
    public static function direction(string $language): string
    {
        return self::isRtl($language) ? 'rtl' : 'ltr';
    }

    /**
     * Get the font family the report should be set in.
     *
     * XB Riyaz ships with mPDF and carries the Arabic-script glyphs and the
     * OpenType tables Urdu needs for its letters to join. DejaVu Sans, which
     * the English report uses, has no Arabic script at all - an Urdu report
     * set in it would be a page of empty boxes.
     */
    public static function fontFamily(string $language): string
    {
        return self::isRtl($language)
            ? '"xbriyaz", "DejaVu Sans", sans-serif'
            : '"DejaVu Sans", sans-serif';
    }

    /**
     * Get every label a result report prints, in one language.
     *
     * @return array<string, string>
     */
    public static function labels(string $language): array
    {
        $labels = self::isRtl($language) ? self::urdu() : self::english();

        // English is the fallback for anything a translation has not
        // covered, so a missing key prints a readable word rather than the
        // key itself.
        return $labels + self::english();
    }

    /**
     * Get a closure the views use to look a label up.
     *
     * @return \Closure(string): string
     */
    public static function translator(string $language): \Closure
    {
        $labels = self::labels($language);

        return fn (string $key): string => $labels[$key] ?? $key;
    }

    /**
     * @return array<string, string>
     */
    private static function english(): array
    {
        return [
            'report_title' => 'Madrassa Result Report',
            'student_information' => 'Student Information',
            'student' => 'Student',
            'father_name' => 'Father Name',
            'registration_no' => 'Registration No.',
            'roll_no' => 'Roll No.',
            'session' => 'Session',
            'department' => 'Department',
            'class' => 'Class',
            'section' => 'Section',
            'programme' => 'Programme',
            'track' => 'Track',

            'result' => 'Result',
            'term' => 'Term',
            'test' => 'Test',
            'test_type' => 'Test Type',
            'first_term' => 'First Term',
            'final_term' => 'Final Term',
            'total_marks' => 'Total Marks',
            'obtained_marks' => 'Obtained Marks',
            'percentage' => 'Percentage',
            'grade' => 'Grade',
            'status' => 'Status',
            'result_date' => 'Result Date',
            'passed' => 'Passed',
            'failed' => 'Failed',
            'not_entered' => 'Not Entered',

            'attendance_progress' => 'Attendance & Progress Summary',
            'attendance' => 'Attendance',
            'working_days' => 'Working Days',
            'present_days' => 'Present Days',
            'absent_days' => 'Absent Days',
            'attendance_percentage' => 'Attendance %',
            'recorded_days' => 'Recorded Days',
            'prepared_lessons' => 'Prepared Lessons',
            'unprepared_lessons' => 'Unprepared Lessons',
            'unprepared_sabqi' => 'Unprepared Sabqi',
            'manzil' => 'Manzil',
            'manzil_days' => 'Manzil Days',
            'latest_record' => 'Latest Record',

            'track_record' => 'Track Record',
            'period' => 'Period',
            'track_class' => 'Track / Class',
            'no_section' => 'No Section',
            'ongoing' => 'Ongoing',
            'no_track_record' => 'No Madrassa placement is recorded for this session.',
            'not_recorded' => 'Not Recorded',
            'none' => 'None',

            'monthly_report' => 'Monthly Report',
            'month' => 'Month',
            'total_days' => 'Total Days',
            'present' => 'Present',
            'absent' => 'Absent',
            'prepared' => 'Prepared',
            'unprepared' => 'Unprepared',

            'all_sessions' => 'All Sessions',
            'all_terms' => 'All Terms',
            'search' => 'Search',
            'no_students' => 'No Madrassa students match these filters.',
            'madrassa_only' => 'Madrassa enrollments only. School enrollments are not reported here.',
            'capped_note' => 'This report covers the first students matching the filters. Narrow by class or section to print a complete group.',
            'generated' => 'generated',
            'page' => 'Page',
            'no_session_note' => 'No academic session is selected, so there are no months to report.',
            'attendance_note' => 'Madrassa registers only. The madrassa sits three registers a day, so Present and Absent count register marks; the percentage is Present over what has actually been recorded.',
            'month_note' => 'Total Days counts the teaching days this student was enrolled for, weekends excluded. A session still running is counted only as far as today.',
            'na' => 'N/A',

            // The admission test passed students notice. It is not a result
            // report, but it is printed by the same engines, in the same two
            // languages, from the same institution letterhead - so its
            // wording belongs in the one place both languages are kept
            // together rather than in a second system that could drift.
            'passed_students_title' => 'Admission Test Passed Students',
            'passed_students_note' => 'The candidates below have passed the admission test. Admission is completed at the office; passing the test is not by itself an admission.',
            'sr_no' => 'Sr. No.',
            'application_no' => 'Application No.',
            'student_name' => 'Student Name',
            'student_type' => 'Student Type',
            'gender' => 'Gender',
            'test_result' => 'Test Result',
            'result_passed' => 'PASSED',
            'total_passed' => 'Total Passed',
            'no_passed_students' => 'No passed students found.',
            'passed_capped_note' => 'Showing :shown of :total passed applicants. Narrow by department or class to print a smaller notice.',
            'generated_on' => 'Generated on',
            'academic_session' => 'Academic Session',
            'admission_status' => 'Admission Status',
            // "Not Approved" is the absence of an approval, not a refusal.
            // A candidate who passed the test and is waiting on a seat has
            // not been rejected, and the notice must not read as though they
            // had been.
            'approved' => 'Approved',
            'not_approved' => 'Not Approved',
            'no_academic_session' => 'No academic session is set, so there is no intake to report. Set a current academic session, or choose one from the admission filters.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function urdu(): array
    {
        return [
            'report_title' => 'مدرسہ نتیجہ رپورٹ',
            'student_information' => 'طالب علم کی معلومات',
            'student' => 'طالب علم',
            'father_name' => 'والد کا نام',
            'registration_no' => 'رجسٹریشن نمبر',
            'roll_no' => 'رول نمبر',
            'session' => 'تعلیمی سال',
            'department' => 'شعبہ',
            'class' => 'درجہ',
            'section' => 'سیکشن',
            'programme' => 'پروگرام',
            'track' => 'شعبہ جات',

            'result' => 'نتیجہ',
            'term' => 'ٹرم',
            'test' => 'امتحان',
            'test_type' => 'امتحان کی قسم',
            'first_term' => 'پہلا ٹرم',
            'final_term' => 'آخری ٹرم',
            'total_marks' => 'کل نمبر',
            'obtained_marks' => 'حاصل کردہ نمبر',
            'percentage' => 'فیصد',
            'grade' => 'گریڈ',
            'status' => 'حالت',
            'result_date' => 'نتیجہ کی تاریخ',
            'passed' => 'کامیاب',
            'failed' => 'ناکام',
            'not_entered' => 'درج نہیں',

            'attendance_progress' => 'حاضری اور کارکردگی کا خلاصہ',
            'attendance' => 'حاضری',
            'working_days' => 'کل ایام',
            'present_days' => 'حاضر ایام',
            'absent_days' => 'غیر حاضر ایام',
            'attendance_percentage' => 'حاضری فیصد',
            'recorded_days' => 'درج شدہ ایام',
            'prepared_lessons' => 'تیار سبق',
            'unprepared_lessons' => 'غیر تیار سبق',
            'unprepared_sabqi' => 'غیر تیار سبقی',
            'manzil' => 'منزل',
            'manzil_days' => 'منزل ایام',
            'latest_record' => 'آخری اندراج',

            'track_record' => 'تعلیمی سفر',
            'period' => 'مدت',
            'track_class' => 'شعبہ / درجہ',
            'no_section' => 'کوئی سیکشن نہیں',
            'ongoing' => 'جاری',
            'no_track_record' => 'اس تعلیمی سال کے لیے کوئی مدرسہ داخلہ درج نہیں۔',
            'not_recorded' => 'درج نہیں',
            'none' => 'کچھ نہیں',

            'monthly_report' => 'ماہانہ رپورٹ',
            'month' => 'مہینہ',
            'total_days' => 'کل ایام',
            'present' => 'حاضر',
            'absent' => 'غیر حاضر',
            'prepared' => 'تیار',
            'unprepared' => 'غیر تیار',

            'all_sessions' => 'تمام تعلیمی سال',
            'all_terms' => 'تمام ٹرم',
            'search' => 'تلاش',
            'no_students' => 'ان شرائط کے مطابق کوئی مدرسہ طالب علم موجود نہیں۔',
            'madrassa_only' => 'صرف مدرسہ کے داخلے۔ اسکول کے داخلے اس رپورٹ میں شامل نہیں۔',
            'capped_note' => 'یہ رپورٹ شرائط کے مطابق ابتدائی طلبہ پر مشتمل ہے۔ مکمل فہرست کے لیے درجہ یا سیکشن منتخب کریں۔',
            'generated' => 'تیار شدہ',
            'page' => 'صفحہ',
            'no_session_note' => 'کوئی تعلیمی سال منتخب نہیں، اس لیے مہینوں کی رپورٹ دستیاب نہیں۔',
            'attendance_note' => 'صرف مدرسہ کے رجسٹر۔ مدرسہ میں روزانہ تین حاضریاں ہوتی ہیں، اس لیے حاضر اور غیر حاضر رجسٹر کے اندراجات شمار کرتے ہیں۔',
            'month_note' => 'کل ایام میں وہ تدریسی دن شمار ہوتے ہیں جن میں طالب علم داخل تھا، ہفتہ اور اتوار کے علاوہ۔ جاری تعلیمی سال آج تک شمار ہوتا ہے۔',
            'na' => 'دستیاب نہیں',

            // The admission test passed students notice.
            'passed_students_title' => 'داخلہ ٹیسٹ میں کامیاب طلبہ',
            'passed_students_note' => 'درج ذیل امیدوار داخلہ ٹیسٹ میں کامیاب ہوئے ہیں۔ داخلہ دفتر میں مکمل ہوگا؛ صرف ٹیسٹ میں کامیابی داخلہ نہیں ہے۔',
            'sr_no' => 'نمبر شمار',
            'application_no' => 'درخواست نمبر',
            'student_name' => 'طالب علم کا نام',
            'student_type' => 'طالب علم کی قسم',
            'gender' => 'جنس',
            'test_result' => 'ٹیسٹ کا نتیجہ',
            'result_passed' => 'کامیاب',
            'total_passed' => 'کل کامیاب',
            'no_passed_students' => 'کوئی کامیاب طالب علم موجود نہیں۔',
            'passed_capped_note' => ':total کامیاب امیدواروں میں سے :shown دکھائے جا رہے ہیں۔ مختصر فہرست کے لیے شعبہ یا درجہ منتخب کریں۔',
            'generated_on' => 'تاریخ اجرا',
            'academic_session' => 'تعلیمی سال',
            'admission_status' => 'داخلہ کی حالت',
            // "ابھی منظور نہیں" - not approved yet. Deliberately not
            // "غیر منظور", which reads as refused.
            'approved' => 'منظور شدہ',
            'not_approved' => 'ابھی منظور نہیں',
            'no_academic_session' => 'کوئی تعلیمی سال مقرر نہیں، اس لیے کوئی داخلہ رپورٹ دستیاب نہیں۔ براہ کرم موجودہ تعلیمی سال مقرر کریں یا داخلہ فلٹر سے منتخب کریں۔',
        ];
    }
}
