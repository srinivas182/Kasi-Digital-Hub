<?php

declare(strict_types=1);

namespace Modules\Site\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Starter help articles (S21) - plain English, drafted from what the platform does; marked for Ku Tirhisana's review. */
final class HelpSeeder extends Seeder
{
    public const ARTICLES = [
        ['start', 'what-is-kasihub', 'What is KasiHub?', 'One free account for jobs, learning, starting a business and mentoring - online and at your hub.',
            "KasiHub is free for young people. With one account you can look for work, learn new skills, start or grow a business and get a mentor.\n\n## What you can do\n- Build a job profile and a CV, and apply for jobs\n- Take short courses and earn certificates\n- Register your business step by step\n- Get support from partners and a mentor\n\nYou can use KasiHub on your phone or at your local Kasi Digital Hub, where staff can help you."],
        ['start', 'create-account', 'Creating your account', 'You need a cellphone number. We send you a code, then you choose a 5-digit PIN.',
            "## Steps\n- Enter your cellphone number\n- Type the 6-digit code we send by SMS\n- Choose a 5-digit PIN you will remember\n- Tell us a little about yourself\n\nIf you are 16 or 17, a parent or guardian must agree before you can use everything."],
        ['start', 'using-a-hub', 'Getting help at a hub', 'Hub staff can help you sign up, build your CV, print documents and more.',
            "Kasi Digital Hubs are places in your community with computers, Wi-Fi and people who can help.\n\nHub facilitators can work with you on your account in a help session. Everything they do for you is recorded, and you can see it in your activity."],
        ['account', 'forgot-pin', 'I forgot my PIN', 'Use "Forgot PIN" on the sign-in page - we send a code to your phone.',
            "On the sign-in page, tap \"Forgot PIN\". We send a code to your cellphone. Type it in, then choose a new PIN.\n\nIf you no longer have your phone number, visit your hub with your ID. Staff can help you move your account to a new number."],
        ['account', 'locked-account', 'My account is locked', 'After too many wrong PINs we lock the account for a while to keep it safe.',
            "If you type the wrong PIN too many times, your account locks for a while. Wait and try again, or use \"Forgot PIN\".\n\nYou can also ask for help in the app. A support person will send a code to your phone - read it back to them so they know it's really you."],
        ['account', 'change-phone', 'Changing my phone number', 'Visit a hub with your ID - staff check it and send a code to your new number.',
            "To keep your account safe, changing your number needs two things:\n- Hub staff check your ID in person\n- You read back a code we send to your new number\n\nWe also send a message to your old number to say it was changed."],
        ['account', 'get-help', 'Asking KasiHub for help', 'Use "Get help" in the menu. We answer within 2 working days.',
            "Open the menu and choose \"Get help\". Choose a topic, describe the problem and send. You can add a screenshot.\n\nWe reply in the app and send you an SMS or WhatsApp message. If you feel unsafe, choose \"I feel unsafe\" - it goes straight to our safeguarding team."],
        ['work', 'job-profile', 'Building your job profile', 'Six short steps. Informal work counts - helping at a shop, babysitting, fixing phones.',
            "Your job profile tells employers what you can do. It has six short steps: about you, work experience, education, skills, what work you want, and languages.\n\nInformal work counts. If you helped at a spaza, did hair, or fixed phones, add it."],
        ['work', 'make-a-cv', 'Making a CV', 'KasiHub turns your profile into a CV you can download, share and verify.',
            "When your profile is done, choose a CV style and create it. Each CV has a code that employers can check online.\n\nWriting help can suggest better words - you decide what to use, and it never adds things you didn't do."],
        ['work', 'finding-jobs', 'Finding jobs that suit you', 'Jobs for you shows a match score with reasons and what you might be missing.',
            "\"Jobs for you\" compares your profile with job adverts. You see a score, the reasons, and any gaps.\n\nThe match never uses your name, age or other personal details. Turn on job alerts to get a message when a good match is posted."],
        ['work', 'applying', 'Applying and following your application', 'Apply with your CV and see every step - employers must tell you the outcome.',
            "Apply with a CV and answer any questions. You can follow each step: received, shortlisted, interview, offer.\n\nEmployers on KasiHub must give every applicant an outcome. If you are hired, confirm it - we check in after 30 and 90 days."],
        ['work', 'employers', 'For employers: posting jobs', 'Register your business, get verified, and post up to 3 adverts free.',
            "Register with your CIPC details (or as a community employer). After a quick check you can post adverts.\n\nEvery advert must show the pay, and pay must be at least the minimum wage. Asking for fees or unfair screening questions isn't allowed."],
        ['learn', 'courses', 'Taking a course', 'Enrol for free, learn on your phone, and see how much data each lesson uses.',
            "Choose a course in the catalogue and enrol. Lessons show how much data they use. Small video plays first to save data.\n\nYou can download a course and learn offline. Your progress is saved and sent when you are back online."],
        ['learn', 'certificates', 'Certificates', 'Finish a course to get a certificate with a code anyone can check.',
            "When you complete a course you get a certificate you can download and share. It appears on your KasiWork CV unless you hide it.\n\nFor accredited courses you get a statement of results; the official certificate comes from the QCTO or SETA."],
        ['learn', 'cohorts', 'Joining a group at the hub', 'Some courses run with sessions at the hub. Join with the code or QR.',
            "Some courses have hub sessions. Join the group with the code or QR on the poster at your hub.\n\nFor these courses, attending enough sessions is part of completing the course."],
        ['business', 'register-business', 'Getting your business registered', 'KasiStart shows the steps for your kind of business - and where to do each one.',
            "Tell KasiStart about your business. It shows the steps that apply to you, like registering with CIPC on BizPortal, tax with SARS, and municipal permits.\n\nThis is general guidance, not legal advice. Your hub can help you through the online forms."],
        ['business', 'business-plan', 'Writing a simple business plan', 'Six short sections, a price calculator and a one-page summary you can share.',
            "The plan builder asks about the need you meet, what you sell, prices and costs, finding customers, money needed and your next 3 months.\n\nThe calculator works out your price and how many items cover your monthly costs."],
        ['business', 'support-offers', 'Finding support for your business', 'See funding, equipment and training you qualify for - and send one referral.',
            "\"Support for you\" shows offers from partners and whether you qualify. If something is missing, it links to the step that fixes it.\n\nWhen you send a referral, you choose exactly what to share. You can withdraw at any time."],
        ['mentoring', 'get-a-mentor', 'Getting a mentor', 'Choose a vetted mentor for one goal and agree how often to meet.',
            "Choose your goal and see mentors who can help, with reasons. Send a short request. Once you both agree on the goals, you can book sessions.\n\nIf you are under 18, mentoring happens at your hub and your guardian is told."],
        ['mentoring', 'staying-safe', 'Staying safe with a mentor', 'Keep talking in KasiHub, never share passwords or money, and report anything that worries you.',
            "All mentors are vetted. Keep your conversations in KasiHub. Phone numbers and social media can't be shared before two sessions.\n\nUse \"Report a concern\" on any mentoring screen. A safeguarding officer looks at it within 24 hours - urgent concerns pause the mentorship straight away.\n\nIn an emergency, phone 10111."],
        ['hubs', 'hub-events', 'Hub events', 'Workshops, talks and help days at your hub - book a place and check in with the QR code.',
            "Your hub runs events like CV workshops, career talks and business help days. Book a place and you'll get a reminder.\n\nCheck in with the QR code at the door. Some courses count your attendance."],
        ['privacy', 'your-data', 'Your information and privacy', 'We only use your information to help you, and you control what is shared.',
            "KasiHub follows South Africa's POPI Act. You choose what to share, for example with employers or partners.\n\nFunders only ever see totals - never your name or details. Small numbers are hidden so nobody can be identified."],
        ['privacy', 'documents', 'Uploading documents', 'Your ID, certificates and letters are stored safely and checked by our team.',
            "Documents you upload are stored privately. Our team checks them and marks them verified.\n\nYou decide when to share a document, for example with a partner for a referral, and you can see when it was opened."],
        ['privacy', 'delete-account', 'Closing your account', 'Ask us to close your account at any time.',
            "You can ask us to close your account using \"Get help\". We remove your personal information, except what the law says we must keep for a while.\n\nIf you change your mind before it is closed, just tell us."],
    ];

    public function run(): void
    {
        foreach (self::ARTICLES as [$category, $slug, $title, $summary, $body]) {
            DB::table('help_articles')->insertOrIgnore(['slug' => $slug, 'category' => $category, 'title' => $title, 'summary' => $summary, 'body' => $body,
                'status' => 'published', 'needs_review' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
