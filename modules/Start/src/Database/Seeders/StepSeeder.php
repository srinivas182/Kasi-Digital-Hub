<?php

declare(strict_types=1);

namespace Modules\Start\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Start\Models\Step;

/**
 * First draft of the formalisation steps (S16). DRAFT - to be checked by Ku Tirhisana's legal adviser
 * before launch; the KasiHub team edits these in the admin screen (no code change). Fees are never
 * hard-coded: every step says where to check the current fee.
 */
final class StepSeeder extends Seeder
{
    public const STEPS = [
        [
            'key' => 'choose_form', 'title' => 'Choose how your business is set up',
            'summary' => 'Decide whether you trade as a sole proprietor, register a private company, form a co-operative or a non-profit company. Our guide compares them in plain language.',
            'why' => 'The way you set up decides who owns the business, who is responsible for its debts, and which registrations you need.',
            'needs' => ['A few minutes to read the guide', 'Your co-owners, if you have any'], 'where' => 'Here in KasiStart, or with a facilitator at your hub.',
            'cost_note' => 'Free', 'duration' => '15 minutes', 'applies_to' => ['forms' => ['*']], 'document_type' => null,
        ],
        [
            'key' => 'cipc_register', 'title' => 'Register the business with CIPC',
            'summary' => 'Companies and co-operatives are registered with the Companies and Intellectual Property Commission (CIPC), usually online on BizPortal, where you can also reserve the name.',
            'why' => 'Registration makes the business a legal entity. Many customers, banks and funders ask for the registration certificate.',
            'needs' => ['Your green ID book or smart ID card', 'Name ideas for the business', 'Details of every director or member', 'An email address and cellphone number'],
            'where' => 'Online on BizPortal, or ask your hub for help with the online forms.', 'link' => 'https://www.bizportal.gov.za',
            'cost_note' => 'There is a registration fee - check the current fee on BizPortal.', 'duration' => 'Often a few days after you apply',
            'applies_to' => ['forms' => ['pty', 'coop', 'npc']], 'document_type' => 'cipc_certificate',
        ],
        [
            'key' => 'sars_tax', 'title' => 'Make sure the business is registered for tax',
            'summary' => 'Companies registered at CIPC are usually registered for income tax automatically - check on SARS eFiling. Sole proprietors declare business income on their own tax return. Very small businesses can ask SARS about turnover tax.',
            'why' => 'Being tax compliant lets you get a tax compliance status PIN, which many customers, tenders and funders ask for.',
            'needs' => ['Your ID', 'The CIPC registration number (for companies and co-operatives)', 'A SARS eFiling profile'],
            'where' => 'SARS eFiling online, or a SARS branch. Your hub can help you create an eFiling profile.', 'link' => 'https://www.sarsefiling.co.za',
            'cost_note' => 'Registering is free.', 'duration' => 'About an hour online', 'applies_to' => ['forms' => ['*']], 'document_type' => 'tax_registration',
        ],
        [
            'key' => 'bank_account', 'title' => 'Open a business bank account',
            'summary' => 'Keep business money separate from personal money. Banks have accounts for small businesses; compare monthly fees.',
            'why' => 'A separate account makes record keeping easier and is needed for most funding and for paying suppliers properly.',
            'needs' => ['Your ID', 'Proof of address', 'CIPC registration documents (for companies and co-operatives)'],
            'where' => 'At a bank branch or in the bank\'s app.', 'cost_note' => 'Monthly fees differ by bank - compare before you choose.',
            'duration' => 'Usually one visit', 'applies_to' => ['forms' => ['*']], 'document_type' => 'bank_confirmation',
        ],
        [
            'key' => 'bbbee_affidavit', 'title' => 'Get a B-BBEE sworn affidavit',
            'summary' => 'Small enterprises can confirm their B-BBEE status with a sworn affidavit instead of a verification certificate. KasiStart prepares the affidavit with your details; you sign it in front of a commissioner of oaths.',
            'why' => 'Larger companies and government often ask suppliers for their B-BBEE status.',
            'needs' => ['The pre-filled affidavit from KasiStart, printed', 'Your ID', 'A commissioner of oaths (for example at a police station)'],
            'where' => 'Print the affidavit at your hub; sign it in front of a commissioner of oaths.',
            'cost_note' => 'Commissioning is usually free.', 'duration' => 'One visit', 'applies_to' => ['forms' => ['sole', 'pty', 'coop']], 'document_type' => 'bbbee_affidavit',
        ],
        [
            'key' => 'uif_coida', 'title' => 'Register as an employer (UIF and COIDA)',
            'summary' => 'If you employ people, register with the Unemployment Insurance Fund (UIF) and the Compensation Fund (COIDA) at the Department of Employment and Labour.',
            'why' => 'It protects your workers if they lose their job or are injured at work, and it is required by law for employers.',
            'needs' => ['Your ID and business details', 'Details of your employees'], 'where' => 'Online on uFiling, or at a labour centre.', 'link' => 'https://www.ufiling.co.za',
            'cost_note' => 'Registering is free; contributions are paid monthly.', 'duration' => 'About an hour online',
            'applies_to' => ['forms' => ['*'], 'employees' => true], 'document_type' => 'uif_registration',
        ],
        [
            'key' => 'municipal_permit', 'title' => 'Check municipal permits and zoning',
            'summary' => 'Ask your municipality whether you need a trading permit, business licence or zoning approval for where and how you trade (for example street trading, a home business or a spaza).',
            'why' => 'Trading without the right permit can lead to fines or your stall being closed.',
            'needs' => ['Your ID', 'Where you trade (address or stand)', 'What you sell'], 'where' => 'Your local municipality\'s business or LED office.',
            'cost_note' => 'Some permits have a fee - ask the municipality.', 'duration' => 'Varies by municipality', 'applies_to' => ['forms' => ['*']], 'document_type' => 'trading_permit',
        ],
        [
            'key' => 'food_certificate', 'title' => 'Get a certificate of acceptability for food',
            'summary' => 'If you prepare or sell food, the municipality\'s environmental health office inspects the premises and issues a certificate of acceptability.',
            'why' => 'It shows customers and inspectors that your food is prepared safely, and it is required for food premises.',
            'needs' => ['Your ID', 'The address where food is prepared', 'Clean water, washing facilities and safe storage'],
            'where' => 'Your municipality\'s environmental health office.', 'cost_note' => 'Ask the municipality about any fee.', 'duration' => 'An inspection visit, then the certificate',
            'applies_to' => ['forms' => ['*'], 'sectors' => ['food']], 'document_type' => 'health_certificate',
        ],
    ];

    public function run(): void
    {
        foreach (self::STEPS as $position => $step) {
            Step::query()->firstOrCreate(['key' => $step['key']], [...$step, 'link' => $step['link'] ?? null, 'position' => $position]);
        }
    }
}
